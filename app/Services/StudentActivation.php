<?php

namespace App\Services;

use App\Exceptions\AccountException;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\SendActionLink\FailedToSendActionLink;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Throwable;

class StudentActivation
{
    public function __construct(private Auth $auth, private StudentEnrollment $enrollment) {}

    public function send(string $email): void
    {
        $email = strtolower(trim($email));
        try {
            $invite = $this->enrollment->invitation($email);
            if (($invite['status'] ?? '') !== 'pending') {
                return;
            }
            try {
                $record = $this->auth->getUserByEmail($email);
                // Existing Unity accounts use login/recovery; never replace their credentials.
                if ($record->uid !== $invite['candidate_uid'] || $record->disabled) {
                    return;
                }
            } catch (UserNotFound) {
                $record = $this->auth->createUser(['uid' => $invite['candidate_uid'], 'email' => $email,
                    'displayName' => $invite['fullname'], 'emailVerified' => false]);
            }
            $token = bin2hex(random_bytes(32));
            $this->enrollment->issue($email, $invite['revision'], $record->uid, $token);
            // APP_URL is trusted configuration; never construct emailed URLs from the request Host.
            $url = rtrim(config('app.url'), '/').'/new-user/activate?'.http_build_query(['email' => $email, 'token' => $token]);
            $this->auth->sendEmailVerificationLink($email, ['url' => $url, 'handleCodeInApp' => false]);
        } catch (EmailExists) {
            // Another request/account won the race. Do not modify that account.
            return;
        } catch (FailedToSendActionLink $exception) {
            $this->emailFailure($exception);
        } catch (AccountException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->unavailable($exception);
        }
    }

    public function verify(string $email, #[\SensitiveParameter] string $token): array
    {
        $invite = $this->enrollment->proof($email, $token);
        try {
            $record = $this->auth->getUser($invite['activation_uid']);
            if ($record->disabled || ! $record->emailVerified || strtolower($record->email ?? '') !== $email) {
                throw new AccountException('Verify your email using the link in your inbox before setting a password.', 'email');
            }
        } catch (AccountException $exception) {
            throw $exception;
        } catch (UserNotFound) {
            throw new AccountException('This activation is no longer available. Contact your administrator.', 'email');
        } catch (Throwable $exception) {
            $this->unavailable($exception);
        }

        return ['fullname' => $invite['fullname'], 'email' => $email];
    }

    public function complete(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): void
    {
        $this->verify($email, $token);
        $invite = $this->enrollment->reserve($email, $token);
        try {
            $this->auth->updateUser($invite['activation_uid'], ['password' => $password]);
            $this->enrollment->finish($email, $token);
        } catch (Throwable $exception) {
            // Profile stays gated until completion; the same valid email proof can safely retry.
            try {
                $this->enrollment->release($email, $token);
            } catch (Throwable) {
                // A short reservation expires if the database is unavailable.
            }
            $this->unavailable($exception);
        }
    }

    private function unavailable(Throwable $exception): never
    {
        Log::warning('Student activation failed.', ['exception_type' => $exception::class]);
        throw new AccountException('Activation could not be completed. Please try again shortly.', status: 503);
    }

    private function emailFailure(FailedToSendActionLink $exception): never
    {
        // Firebase messages may contain request details. Only log recognized error codes.
        $code = strtok($exception->getMessage(), ' :');
        $known = ['UNAUTHORIZED_DOMAIN', 'INVALID_CONTINUE_URI', 'MISSING_CONTINUE_URI',
            'TOO_MANY_ATTEMPTS_TRY_LATER', 'QUOTA_EXCEEDED', 'OPERATION_NOT_ALLOWED'];
        $code = in_array($code, $known, true) ? $code : 'UNKNOWN_EMAIL_ERROR';
        Log::warning('Student activation email failed.', ['error_code' => $code]);
        $message = match ($code) {
            'UNAUTHORIZED_DOMAIN', 'INVALID_CONTINUE_URI', 'MISSING_CONTINUE_URI' => 'The activation email return URL is not configured correctly. Ask your administrator to check APP_URL and add its hostname in Firebase Authentication > Settings > Authorized domains.',
            'TOO_MANY_ATTEMPTS_TRY_LATER', 'QUOTA_EXCEEDED' => 'Firebase has temporarily limited verification emails. Please wait before requesting another email.',
            'OPERATION_NOT_ALLOWED' => 'Email verification is not available. Ask your administrator to check the Firebase Authentication email/password provider settings.',
            default => 'The verification email could not be sent. Please try again shortly or contact your administrator.',
        };

        throw new AccountException($message, 'email', 503);
    }
}

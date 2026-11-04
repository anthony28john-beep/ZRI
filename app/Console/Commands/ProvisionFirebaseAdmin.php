<?php

namespace App\Console\Commands;

use App\Services\FullNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Throwable;

class ProvisionFirebaseAdmin extends Command
{
    protected $signature = 'firebase:admin {email : Existing Firebase account email}';

    protected $description = 'Promote an existing Firebase account to administrator without exposing passwords';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }
        try {
            $record = app(Auth::class)->getUserByEmail($email);
            if ($record->disabled) {
                $this->error('Enable this account in Firebase before promoting it.');

                return self::FAILURE;
            }
            $reference = app(Database::class)->getReference('users/'.$record->uid);
            $profile = $reference->getValue();
            $name = app(FullNames::class)->forProfile($record->uid, is_array($profile) ? $profile : [], 'Administrator');
            // Patch only profile identity and role; retain any password-change requirement.
            $reference->update([
                'uid' => $record->uid, 'name' => $name,
                'email' => $record->email, 'role' => 'admin',
            ]);
            $this->info('Administrator access granted. Sign in and verify the account email to continue.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Unable to provision the administrator. Confirm the account exists and Firebase credentials are configured.');

            return self::FAILURE;
        }
    }
}

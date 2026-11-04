<?php

namespace App\Console\Commands;

use App\Services\FirebaseAccounts;
use Illuminate\Console\Command;
use Throwable;

class ReconcileFirebaseProfile extends Command
{
    protected $signature = 'firebase:reconcile {uid : Firebase Authentication user ID}';

    protected $description = 'Repair a missing profile as a student, or synchronize an existing profile email';

    public function handle(): int
    {
        try {
            $user = app(FirebaseAccounts::class)->identity($this->argument('uid'), repairMissing: true);
            $this->info('Profile reconciled. Current role: '.$user['role']);

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Unable to reconcile the profile. Check Firebase configuration and the account status.');

            return self::FAILURE;
        }
    }
}

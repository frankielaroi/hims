<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HimsRecoveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hims:recovery {username} {--unlock} {--reset-password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover or unlock a user account in case of lockout.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->argument('username');
        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->error("User {$username} not found.");

            return 1;
        }

        if ($this->option('unlock')) {
            $user->update([
                'status' => 'active',
                'login_attempts' => 0,
            ]);
            $this->info("User {$username} has been unlocked.");
        }

        if ($password = $this->option('reset-password')) {
            $user->update([
                'password' => Hash::make($password),
                'requires_password_change' => true,
                'status' => 'active',
            ]);
            $this->info("Password for {$username} has been reset. User will be forced to change it on next login.");
        }

        if (! $this->option('unlock') && ! $this->option('reset-password')) {
            $this->warn('No action specified. Use --unlock or --reset-password.');
        }

        return 0;
    }
}

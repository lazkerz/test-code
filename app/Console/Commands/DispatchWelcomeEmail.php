<?php

namespace App\Console\Commands;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Console\Command;

class DispatchWelcomeEmail extends Command
{
    protected $signature = 'app:send-welcome-email {user : User ID or email} {--sync : Run immediately instead of queueing}';

    protected $description = 'Dispatch the welcome email job for an existing user';

    public function handle(): int
    {
        $identifier = $this->argument('user');

        $user = User::query()
            ->where(is_numeric($identifier) ? 'id' : 'email', $identifier)
            ->first();

        if (! $user) {
            $this->error("User [{$identifier}] not found.");

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            SendWelcomeEmail::dispatchSync($user);
            $this->info("Welcome email sent to {$user->email}.");
        } else {
            SendWelcomeEmail::dispatch($user);
            $this->info("Welcome email job queued for {$user->email}.");
        }

        return self::SUCCESS;
    }
}

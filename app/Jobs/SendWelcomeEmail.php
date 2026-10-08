<?php

namespace App\Jobs;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $user)
    {
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        Mail::to($this->user)->send(new WelcomeMail($this->user));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Failed to send welcome email', [
            'user_id' => $this->user->id,
            'exception' => $e::class,
        ]);
    }
}

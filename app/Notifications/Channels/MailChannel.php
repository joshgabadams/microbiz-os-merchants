<?php

namespace App\Notifications\Channels;

use App\Services\Notification\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class MailChannel implements NotificationChannel
{
    public function send(Model $notifiable, string $template, array $payload = []): void
    {
        if (! $notifiable->email) {
            throw new \RuntimeException('Notifiable has no email address.');
        }

        Mail::raw(
            $payload['body'] ?? "Notification: {$template}",
            function ($message) use ($notifiable, $payload) {
                $message->to($notifiable->email)
                    ->subject($payload['subject'] ?? config('app.name'));
            }
        );
    }
}

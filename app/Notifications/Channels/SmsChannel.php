<?php

namespace App\Notifications\Channels;

use App\Services\Notification\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannel
{
    public function send(Model $notifiable, string $template, array $payload = []): void
    {
        $provider = config('microbiz.notifications.sms_provider', 'stub');

        if ($provider === 'stub') {
            Log::info('[SMS:stub] would send SMS', [
                'to' => $notifiable->phone ?? null,
                'template' => $template,
                'payload' => $payload,
            ]);

            return;
        }

        throw new \RuntimeException("SMS provider [{$provider}] is not yet implemented.");
    }
}

<?php

namespace App\Notifications\Channels;

use App\Services\Notification\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PushChannel implements NotificationChannel
{
    public function send(Model $notifiable, string $template, array $payload = []): void
    {
        $provider = config('microbiz.notifications.push_provider', 'stub');

        if ($provider === 'stub') {
            Log::info('[Push:stub] would send push notification', [
                'notifiable_id' => $notifiable->getKey(),
                'template' => $template,
                'payload' => $payload,
            ]);

            return;
        }

        throw new \RuntimeException("Push provider [{$provider}] is not yet implemented.");
    }
}

<?php

namespace App\Notifications\Channels;

use App\Services\Notification\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannel
{
    public function send(Model $notifiable, string $template, array $payload = []): void
    {
        $provider = config('microbiz.notifications.whatsapp_provider', 'stub');

        if ($provider === 'stub') {
            Log::info('[WhatsApp:stub] would send WhatsApp message', [
                'to' => $notifiable->phone ?? null,
                'template' => $template,
                'payload' => $payload,
            ]);

            return;
        }

        throw new \RuntimeException("WhatsApp provider [{$provider}] is not yet implemented.");
    }
}

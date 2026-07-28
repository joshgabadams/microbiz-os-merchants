<?php

namespace App\Services\Notification;

use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Channel-agnostic notification dispatch for MicroBiz cash operations.
 * Wired into the NotifyFinancialUsers listener so financial events
 * (deposits, withdrawals, float allocations, reversals) can alert the
 * right staff/customers without each service reimplementing delivery,
 * retries, or logging.
 */
class NotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Model $notifiable, string $channel, string $template, array $payload = []): NotificationLog
    {
        $log = NotificationLog::create([
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'channel' => $channel,
            'template' => $template,
            'status' => 'queued',
        ]);

        try {
            $driver = $this->resolveDriver($channel);
            $driver->send($notifiable, $template, $payload);

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    protected function resolveDriver(string $channel): NotificationChannel
    {
        return match ($channel) {
            'mail' => app(\App\Notifications\Channels\MailChannel::class),
            'sms' => app(\App\Notifications\Channels\SmsChannel::class),
            'push' => app(\App\Notifications\Channels\PushChannel::class),
            'whatsapp' => app(\App\Notifications\Channels\WhatsAppChannel::class),
            default => throw new \InvalidArgumentException("Unknown notification channel [{$channel}]."),
        };
    }
}

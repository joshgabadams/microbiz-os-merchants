<?php

namespace App\Services\Notification;

use Illuminate\Database\Eloquent\Model;

interface NotificationChannel
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Model $notifiable, string $template, array $payload = []): void;
}

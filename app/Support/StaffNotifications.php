<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

class StaffNotifications
{
    /**
     * @return array<string, mixed>
     */
    public static function map(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? 'Alert',
            'body' => $data['body'] ?? '',
            'href' => $data['href'] ?? null,
            'status' => $data['status'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Application\Rh;

use App\Models\SisNotification;
use App\Models\User;
use Illuminate\Support\Collection;

final class CreateSisNotificationAction
{
    public function execute(
        User|Collection|array $recipients,
        string $type,
        string $titre,
        string $message,
        ?array $meta = null,
    ): void {
        $users = $recipients instanceof Collection
            ? $recipients
            : collect(is_array($recipients) ? $recipients : [$recipients]);

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            SisNotification::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'titre' => $titre,
                'message' => $message,
                'meta' => $meta,
            ]);
        }
    }

    public function notifyRhUsers(string $type, string $titre, string $message, ?array $meta = null): void
    {
        $users = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['rh', 'super-admin']))
            ->get();

        $this->execute($users, $type, $titre, $message, $meta);
    }
}

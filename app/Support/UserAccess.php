<?php

namespace App\Support;

use App\Models\User;

class UserAccess
{
    public const SUPER_ADMIN_EMAIL = 'admin@example.com';

    public static function isSuperAdmin(?User $user): bool
    {
        return $user?->role === 'admin'
            && mb_strtolower(trim((string) $user->email)) === self::SUPER_ADMIN_EMAIL;
    }

    public static function banMessage(?User $user): string
    {
        $reason = trim((string) $user?->ban_reason);

        if ($reason === '') {
            return 'Аккаунт заблокирован.';
        }

        $labels = [
            'spam' => 'Спам',
            'abuse' => 'Оскорбления',
            'rules' => 'Нарушение правил',
            'security' => 'Безопасность',
        ];

        return 'Аккаунт заблокирован. Причина: '.($labels[$reason] ?? $reason).'.';
    }
}

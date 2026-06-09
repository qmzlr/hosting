<?php

namespace App\Support;

class EmailRules
{
    public const PATTERN = '/^[^\s@]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/';

    /**
     * @return array<int, string>
     */
    public static function base(): array
    {
        return ['email', 'regex:'.self::PATTERN, 'max:320'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.email' => 'Введите корректный email.',
            'email.regex' => 'Введите корректный email.',
        ];
    }
}

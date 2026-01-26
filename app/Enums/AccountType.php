<?php

namespace App\Enums;

enum AccountType: string
{
    case PROSPECT = 'prospect';
    case CLIENT = 'client';
    case AGENT = 'agent';
    case ADMIN = 'admin';
    case LEGAL = 'legal';
    case ACCOUNTANT = 'accountant';

    /**
     * Get the display name for the account type.
     */
    public function label(): string
    {
        return match($this) {
            self::PROSPECT => 'prospect',
            self::CLIENT => 'client',
            self::AGENT => 'agent',
            self::ADMIN => 'admin',
            self::LEGAL => 'legal',
            self::ACCOUNTANT => 'accountant',
        };
    }
}

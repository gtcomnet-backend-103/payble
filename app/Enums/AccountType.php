<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case RECEIVABLE = 'receivable';
    case BUSINESS_HOLDS = 'business_holds';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';
    case ADVANCE = 'advance';
}

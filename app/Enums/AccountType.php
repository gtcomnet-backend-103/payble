<?php

namespace App\Enums;

enum AccountType: string
{
    case CUSTOMER_WALLET = 'customer_wallet';
    case CUSTOMER_HOLDS = 'customer_holds';
    case BUSINESS_WALLET = 'business_wallet';
    case BUSINESS_HOLDS = 'business_holds';
    case PLATFORM_FEE_REVENUE = 'platform_fee_revenue';
    case PROVIDER_FEE_EXPENSE = 'provider_fee_expense';
    case PROVIDER_CLEARING = 'provider_clearing';
}


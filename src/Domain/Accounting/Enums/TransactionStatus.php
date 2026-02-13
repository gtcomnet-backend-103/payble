<?php

declare(strict_types=1);

namespace Domain\Accounting\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case Success = 'success';
    case FAILED = 'failed';
}

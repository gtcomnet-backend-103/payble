<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for actions that must be idempotent.
 * Implementations should ensure that calling execute multiple times with the same input
 * does not result in duplicate side effects.
 */
interface IdempotentAction {}

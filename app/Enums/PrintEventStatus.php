<?php

namespace App\Enums;

enum PrintEventStatus: string
{
    case PENDING  = 'pending';
    case RESERVED = 'reserved';
    case PRINTING = 'printing';
    case PRINTED  = 'printed';
    case FAILED   = 'failed';

    public function canTransitionTo(PrintEventStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING  => in_array($newStatus, [self::RESERVED, self::PRINTING]),
            self::RESERVED => in_array($newStatus, [self::PRINTING, self::PENDING]),  // PENDING allows un-reserve
            self::PRINTING => in_array($newStatus, [self::PRINTED, self::FAILED]),
            self::FAILED   => in_array($newStatus, [self::PENDING]),                  // Retry: failed → pending
            self::PRINTED  => false,                                                   // Terminal
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::PRINTED;
    }
}

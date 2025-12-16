<?php

namespace App\Enums;

enum ItemStatus : string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case CANCELLED = 'cancelled';
    case VOIDED = 'voided';
    case RETURNED = 'returned';

    public function canTransitionTo(ItemStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::PREPARING, self::CANCELLED, self::VOIDED, self::RETURNED]),
            self::PREPARING => in_array($newStatus, [self::READY, self::CANCELLED, self::VOIDED, self::RETURNED]),
            self::READY => in_array($newStatus, [self::SERVED, self::RETURNED, self::CANCELLED]),
            self::SERVED, self::CANCELLED, self::VOIDED, self::RETURNED => false,
        };
    }
}

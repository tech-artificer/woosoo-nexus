<?php

namespace App\Enums;

enum OrderStatus : string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case READY = 'ready';
    case SERVED = 'served';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case VOIDED = 'voided';
    case ARCHIVED = 'archived';

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING     => in_array($newStatus, [self::CONFIRMED, self::VOIDED, self::CANCELLED]),
            self::CONFIRMED   => in_array($newStatus, [self::IN_PROGRESS, self::COMPLETED, self::VOIDED]),
            self::IN_PROGRESS => in_array($newStatus, [self::READY, self::VOIDED]),
            self::READY       => in_array($newStatus, [self::SERVED, self::VOIDED]),
            self::SERVED      => in_array($newStatus, [self::COMPLETED, self::VOIDED]),
            self::COMPLETED,
            self::CANCELLED,
            self::VOIDED,
            self::ARCHIVED    => false, // terminal states - no transitions allowed
        };
    }
}

<?php

namespace App\Enums;


enum OrderStatus : string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    // case IN_PROGRESS = 'in_progress';
    // case READY = 'ready';
    // case SERVED = 'served';
    case COMPLETED = 'completed';
    // case CANCELLED = 'cancelled';
    case VOIDED = 'voided';
    // case ARCHIVED = 'archived';

    // Optional: Helper method to get valid transitions
    // Define allowed next statuses
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        // return match ($this) {
        //     self::PENDING => in_array($newStatus, [self::CONFIRMED, self::CANCELLED, self::VOIDED]),
        //     self::CONFIRMED => in_array($newStatus, [self::IN_PROGRESS, self::CANCELLED]),
        //     self::IN_PROGRESS => in_array($newStatus, [self::READY, self::CANCELLED]),
        //     self::READY => $newStatus === self::SERVED,
        //     self::SERVED, self::CANCELLED, self::SERVED => false, // Terminal states,
        //     self::SERVED => in_array($newStatus, [self::COMPLETED ,self::SERVED, self::CANCELLED]),
        // };
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::CONFIRMED, self::VOIDED]),
            self::CONFIRMED => in_array($newStatus, [self::COMPLETED, self::VOIDED]),
            // self::COMPLETED => in_array($newStatus, [self::ARCHIVED]),
            // self::IN_PROGRESS => in_array($newStatus, [self::READY, self::CANCELLED]),
            // self::READY => $newStatus === self::SERVED,
            // self::SERVED, self::CANCELLED, self::SERVED => false, // Terminal states,
            // self::SERVED => in_array($newStatus, [self::COMPLETED ,self::SERVED, self::CANCELLED]),
        };
    }
}

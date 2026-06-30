<?php

declare(strict_types=1);

namespace App\Broadcasting;

/**
 * NEX-CASE-013: canonical registry of broadcast event names.
 *
 * Authoritative source for the `broadcastAs` string of every order/session
 * broadcast. Tablet, KDS, admin, and print-bridge consumers mirror these
 * names via shared constants in their respective `events.ts` / `events.dart`
 * modules. Adding a name here is the contract change; consumers update from
 * this enum, not from string literals scattered across event classes.
 *
 * @see contracts/websocket-events.contract.md
 */
enum BroadcastEvent: string
{
    case OrderCreated = 'order.created';
    case OrderUpdated = 'order.updated';
    case OrderCompleted = 'order.completed';
    case OrderVoided = 'order.voided';
    case OrderCancelled = 'order.cancelled';
    case OrderDetailsUpdated = 'order.details.updated';
    case DiscountApplied = 'discount.applied';
    case OrderStartedFromPos = 'order.started-from-pos';
    case PrintRequested = 'order.print.requested';
    case ItemToggled = 'item.toggled';
    case SessionReset = 'session.reset';
}

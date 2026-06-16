<?php

use App\Enums\OrderStatus;

// --- P2 recall edge ---

test('served cannot transition to in_progress via canTransitionTo (recall is KDS-enforced in KdsController)', function () {
    expect(OrderStatus::SERVED->canTransitionTo(OrderStatus::IN_PROGRESS))->toBeFalse();
});

test('voided cannot transition to in_progress', function () {
    expect(OrderStatus::VOIDED->canTransitionTo(OrderStatus::IN_PROGRESS))->toBeFalse();
});

test('completed cannot transition to in_progress', function () {
    expect(OrderStatus::COMPLETED->canTransitionTo(OrderStatus::IN_PROGRESS))->toBeFalse();
});

test('cancelled cannot transition to in_progress', function () {
    expect(OrderStatus::CANCELLED->canTransitionTo(OrderStatus::IN_PROGRESS))->toBeFalse();
});

test('archived cannot transition to in_progress', function () {
    expect(OrderStatus::ARCHIVED->canTransitionTo(OrderStatus::IN_PROGRESS))->toBeFalse();
});

// --- Verify existing valid transitions are unchanged ---

test('confirmed can transition to in_progress', function () {
    expect(OrderStatus::CONFIRMED->canTransitionTo(OrderStatus::IN_PROGRESS))->toBeTrue();
});

test('pending can transition to confirmed', function () {
    expect(OrderStatus::PENDING->canTransitionTo(OrderStatus::CONFIRMED))->toBeTrue();
});

test('in_progress can transition to ready', function () {
    expect(OrderStatus::IN_PROGRESS->canTransitionTo(OrderStatus::READY))->toBeTrue();
});

test('ready can transition to served', function () {
    expect(OrderStatus::READY->canTransitionTo(OrderStatus::SERVED))->toBeTrue();
});

test('served can still transition to completed', function () {
    expect(OrderStatus::SERVED->canTransitionTo(OrderStatus::COMPLETED))->toBeTrue();
});

test('served can still transition to voided', function () {
    expect(OrderStatus::SERVED->canTransitionTo(OrderStatus::VOIDED))->toBeTrue();
});

// --- Guard: terminal states have no outgoing edges ---

test('completed has no outgoing transitions', function () {
    foreach (OrderStatus::cases() as $target) {
        expect(OrderStatus::COMPLETED->canTransitionTo($target))->toBeFalse();
    }
});

test('voided has no outgoing transitions', function () {
    foreach (OrderStatus::cases() as $target) {
        expect(OrderStatus::VOIDED->canTransitionTo($target))->toBeFalse();
    }
});

test('cancelled has no outgoing transitions', function () {
    foreach (OrderStatus::cases() as $target) {
        expect(OrderStatus::CANCELLED->canTransitionTo($target))->toBeFalse();
    }
});

test('archived has no outgoing transitions', function () {
    foreach (OrderStatus::cases() as $target) {
        expect(OrderStatus::ARCHIVED->canTransitionTo($target))->toBeFalse();
    }
});

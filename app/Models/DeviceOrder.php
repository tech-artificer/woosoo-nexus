<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\LocalBranchResolver;
use App\Models\Branch;
use App\Models\Krypton\Table;
use App\Models\Krypton\Order;
use App\Enums\OrderStatus;
use Illuminate\Support\Str; 
use Illuminate\Database\Eloquent\Builder;
use App\Models\ServiceRequest;
use App\Models\DeviceOrderItems;

class DeviceOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'device_orders';
    // Prevent accidental mass-assignment of legacy JSON columns
    // The `items` and `meta` JSON columns have been migrated out
    // to `device_order_items` and a computed accessor respectively.
    protected $guarded = ['items', 'meta'];
    protected $primaryKey = 'id';

    // protected $fillable = [
    //     'id',
    //     'branch_id',
    //     'device_id',
    //     'table_id',
    //     'order_id',
    //     'order_number',
    //     'terminal_session_id',
    //     'status',
    //     'items',
    //     'meta',
    // ];

    protected $hidden = [
        'updated_at'
    ];

    protected $casts = [
        'order_uuid' => 'string',
        'order_number' => 'string',
        'status' => OrderStatus::class,
        'total' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'guest_count' => 'integer',
        'is_printed' => 'boolean',
        'printed_at' => 'datetime',
        'printed_by' => 'string',
    ];

    /**
     * Return related device order items (local canonical source).
     */
    public function items() : HasMany
    {
        return $this->hasMany(DeviceOrderItems::class, 'order_id')->orderBy('index');
    }

    /**
     * Provide a computed `meta` attribute for backwards compatibility.
     * If POS order_check information exists, surface it under ['order_check'].
     */
    public function getMetaAttribute()
    {
        try {
            $orderCheck = $this->order?->orderCheck ?? null;
            return [ 'order_check' => $orderCheck ];
        } catch (\Throwable $_e) {
            return [];
        }
    }

    /**
     * Set the status attribute, accepting either an OrderStatus enum or a string value.
     */
    public function setStatusAttribute($newStatus): void
    {
        // Coerce string values to the OrderStatus enum
        if (is_string($newStatus)) {
            $newStatus = OrderStatus::from($newStatus);
        }

        if (!$newStatus instanceof OrderStatus) {
            $newStatus = OrderStatus::PENDING;
        }

        // If this is a new model (creating), skip transition validation
        if (!$this->exists) {
            $this->attributes['status'] = $newStatus->value;
            return;
        }

        $currentStatus = $this->status ?? OrderStatus::PENDING;
        if (is_string($currentStatus)) {
            $currentStatus = OrderStatus::from($currentStatus);
        }

        if (!$currentStatus->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Invalid status transition: {$currentStatus->value} → {$newStatus->value}"
            );
        }

        // Store the underlying value (string) so casting remains consistent
        $this->attributes['status'] = $newStatus->value;
    }

    // generateOrderNumber() removed 2026-04-07 — replaced with UUID-backed identity generation.
    // See RANPO_PRODUCTION_AUDIT_2026-04-07.md §P0 Race Condition.

    protected static function boot()
    {   
        parent::boot();

        static::creating(function ($model) {
            if (!empty($model->branch_id)) {
                return;
            }

            if (!empty($model->device_id)) {
                $deviceBranchId = Device::query()
                    ->whereKey($model->device_id)
                    ->value('branch_id');

                if (!empty($deviceBranchId)) {
                    $model->branch_id = (int) $deviceBranchId;
                    return;
                }
            }

            $model->branch_id = app(LocalBranchResolver::class)->requireId();
        });

        static::creating(function (DeviceOrder $model) {
            // P0 fix 2026-04-07: Assign a UUID for collision-free device order identity.
            if (empty($model->order_uuid)) {
                $model->order_uuid = (string) Str::uuid();
            }

            // Preserve explicitly supplied display order numbers in tests/admin fixtures.
            if ($model->order_number) {
                return;
            }

            $model->order_number = 'ORD-'
                . now()->format('Ymd')
                . '-'
                . strtoupper(substr((string) $model->order_uuid, -6));
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'device_order_id');
    }

    /**
     * Related print events for this device order.
     */
    public function printEvents(): HasMany
    {
        return $this->hasMany(\App\Models\PrintEvent::class, 'device_order_id', 'id');
    }

    /**
     * Latest print event for this device order (singular).
     * Used by PrintOrder/PrintRefill broadcasts to get print_event_id.
     */
    public function printEvent(): HasOne
    {
        return $this->hasOne(\App\Models\PrintEvent::class, 'device_order_id', 'id')
            ->latestOfMany();
    }

    public function scopeActiveOrder(Builder $query) {
        return $query->whereIn('status', [
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED,
            OrderStatus::IN_PROGRESS,
            OrderStatus::READY,
            OrderStatus::SERVED,
        ]);
    }

    /**
     * Scope to return completed / terminal orders
     */
    public function scopeCompletedOrder(Builder $query)
    {
        return $query->whereIn('status', [
            OrderStatus::COMPLETED,
            OrderStatus::VOIDED,
            OrderStatus::ARCHIVED,
        ]);
    }
}

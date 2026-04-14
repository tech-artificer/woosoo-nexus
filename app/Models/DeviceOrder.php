<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'deleted_at',
        'updated_at'
    ];

    protected $casts = [
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
        return $this->hasMany(DeviceOrderItems::class, 'order_id');
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

    // Mutator removed — validation moved to updating event hook in boot()
    // Laravel's enum casting handles type conversion automatically

    public static function generateOrderNumber($orderId): string
    {
        // Get the latest order number
        $latestOrder = static::latest()->first();

        $nextNumber = 1;
        if ($latestOrder) {
            // Extract the numeric part (assuming a format like ORD-000001)
            $lastNumber = (int) substr($latestOrder->order_number, 4); // "ORD-" is 4 chars
            $nextNumber = $lastNumber + 1;
        }

        // Format the number with leading zeros, e.g., ORD-000001
        return 'ORD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT) . '-' . $orderId;
    }

    protected static function boot()
    {   
        parent::boot();

        // Validate status transitions before saving (replaces mutator logic)
        static::updating(function (DeviceOrder $model) {
            // Only validate if status is being changed
            if ($model->isDirty('status')) {
                $oldStatus = $model->getOriginal('status');
                $newStatus = $model->getAttribute('status');
                
                // Convert to enums if needed
                if (is_string($oldStatus)) {
                    $oldStatus = OrderStatus::from($oldStatus);
                }
                if (is_string($newStatus)) {
                    $newStatus = OrderStatus::from($newStatus);
                } elseif (!$newStatus instanceof OrderStatus) {
                    throw new \InvalidArgumentException('Status must be an OrderStatus enum or valid string');
                }
                
                // Validate transition
                if (!$oldStatus->canTransitionTo($newStatus)) {
                    throw new \InvalidArgumentException(
                        "Invalid status transition: {$oldStatus->value} → {$newStatus->value}"
                    );
                }
            }
        });

        static::creating(function ($model) {
            // Auto-assign branch_id from first branch if not set
            // Skip in testing environment to avoid race conditions
            if (!$model->branch_id && !app()->environment('testing')) {
                $firstBranch = Branch::first();
                if ($firstBranch) {
                    $model->branch_id = $firstBranch->id;
                }
            }
        });

        static::creating(function ($model) {
            // Skip auto-generation if order_number already set (e.g., in tests)
            if ($model->order_number) {
                return;
            }

            // Skip if order_id is missing (required for generation)
            if (!$model->order_id) {
                return;
            }

            // Attempt to generate a unique order number
            // This loop handles potential race conditions by retrying
            $maxAttempts = 5; // Or more, depending on expected concurrency
            for ($i = 0; $i < $maxAttempts; $i++) {
                $orderNumber = static::generateOrderNumber($model->order_id);
                // Check if it already exists to avoid unique constraint violation
                if (!static::where('order_number', $orderNumber)->exists()) {
                    $model->order_number = $orderNumber;
                    return; // Number is unique, proceed with creation
                }
                // If it exists, retry with a potentially higher number in the next iteration
                // (though generateOrderNumber already gets the latest, this is a fallback)
            }
            throw new \Exception('Failed to generate a unique order number after multiple attempts.');
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

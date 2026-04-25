<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\DeviceOrder;
use App\Models\Device;
use App\Models\User;

class ServiceRequest extends Model
{
    protected $table = 'service_requests';
    protected $primaryKey = 'id';

    protected $fillable = [
       'device_order_id',
       'table_service_id',
       'order_id',
       'status',
       'priority',
       'acknowledged_at',
       'acknowledged_by',
       'completed_by',
       'assigned_device_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'table_service_name',
        'table_name',
        'description',
        'is_active',
        'is_archived',
    ];

    public function getTableServiceNameAttribute()
    {
        return $this->tableService?->name ?? null;
    }

    public function getTableNameAttribute()
    {
        return data_get($this->deviceOrder, 'table.name')
            ?? data_get($this->deviceOrder, 'device.name')
            ?? null;
    }

    public function getDescriptionAttribute()
    {
        return $this->attributes['description']
            ?? $this->tableService?->name
            ?? null;
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status ?? 'pending', ['pending', 'in_progress'], true);
    }

    public function getIsArchivedAttribute(): bool
    {
        return false;
    }

    /**
     * Scope: Pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Active requests (pending or in_progress)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    public function deviceOrder(): BelongsTo|null
    {
        return $this->belongsTo(DeviceOrder::class);
    }

    public function tableService(): BelongsTo|null
    {
        return $this->belongsTo(TableService::class);
    }

    public function assignedDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'assigned_device_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
    
}

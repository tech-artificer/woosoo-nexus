<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\DeviceOrder;

class ServiceRequest extends Model
{
    protected $table = 'service_requests';
    protected $primaryKey = 'id';

    protected $fillable = [
       'device_order_id',
       'table_service_id',
       'order_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $appends = ['table_service_name'];

    public function getTableServiceNameAttribute()
    {
        return $this->tableService->name;
    }

    public function deviceOrder(): BelongsTo
    {
        return $this->belongsTo(DeviceOrder::class);
    }

    public function tableService(): BelongsTo
    {
        return $this->belongsTo(TableService::class);
    }
    
}

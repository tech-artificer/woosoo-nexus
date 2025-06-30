<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Krypton\Table;

class Device extends Model
{
    use HasApiTokens;

    protected $table = 'devices';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'device_uuid',
        'branch_id',
        'name',
        'table_id',
        'is_active',
        'app_version',
        'last_ip_address',
        'last_seen_at',
    ];

    // protected $casts = [
    //     ''
    // ];

     protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Called when the model is being instantiated.
     * It is used to set the UUID automatically when creating a new device.
     * @return void
     * 
     * @var array
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->device_uuid)) {
                $model->device_uuid = (string) Str::uuid();
            }

            $model->branch_id = Branch::first()->id;
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DeviceOrder::class, 'device_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }
}

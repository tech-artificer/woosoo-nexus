<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'ip_address',
        'last_ip_address',
        'last_seen_at',
    ];

     protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
      'table_id' => 'integer',
      'is_active' => 'boolean',
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
        return $this->belongsTo(Table::class, 'table_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function registrationCode(): HasOne
    {
        return $this->hasOne(DeviceRegistrationCode::class);
    }



    # SCOPES
    public function scopeActive(Builder $query) 
    {
        return $query->where('is_active', true);
    }
}

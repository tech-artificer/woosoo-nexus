<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Krypton\Table;

class Device extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'devices';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'branch_id',
        'name',
        'table_id',
        'is_active',
        'status',
        'app_version',
        'ip_address',
        'last_ip_address',
        'last_seen_at',
    ];

    protected $guarded = [
        'device_uuid', // Immutable: assigned at creation, never changes
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
      'table_id' => 'integer',
      'is_active' => 'boolean',
      'last_seen_at' => 'datetime',
    ];

    /**
     * B2: Device identity immutability enforcement.
     * Called after the model is instantiated.
     * device_uuid is assigned once at creation and never updated.
     * 
     * @return void
     */
    protected static function booted()
    {
        parent::booted();

        // Assign UUID on creation if not already set
        static::creating(function ($model) {
            if (empty($model->device_uuid)) {
                $model->device_uuid = (string) Str::uuid();
            }

            if (empty($model->branch_id)) {
                $model->branch_id = Branch::first()->id;
            }
        });

        // Prevent device_uuid from being modified after creation (immutability guard)
        static::updating(function ($model) {
            if ($model->isDirty('device_uuid')) {
                throw new \Exception('Device UUID is immutable and cannot be modified after creation.');
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DeviceOrder::class, 'device_id');
    }

    public function table(): BelongsTo|null
    {
        return $this->belongsTo(Table::class, 'table_id', 'id');
    }

    public function branch(): BelongsTo|null
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function registrationCode(): HasOne
    {
        return $this->hasOne(DeviceRegistrationCode::class, 'used_by_device_id', 'id');
    }



    # SCOPES
    public function scopeActive(Builder $query) 
    {
        return $query->where('is_active', true);
    }
}

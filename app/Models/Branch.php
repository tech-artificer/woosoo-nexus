<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Branch extends Model
{
    use SoftDeletes;

    protected $table = 'branches';

    protected $fillable = [
        'branch_uuid',
        'name',
        'location',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
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
            if (empty($model->branch_uuid)) {
                $model->branch_uuid = (string) Str::uuid();
            }
        });
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function users(): HasMany|BelongsToMany
    {
        if (
            Schema::hasTable('branch_user') &&
            Schema::hasColumn('branch_user', 'branch_id') &&
            Schema::hasColumn('branch_user', 'user_id')
        ) {
            return $this->belongsToMany(User::class, 'branch_user', 'branch_id', 'user_id');
        }

        if (Schema::hasColumn('users', 'branch_id')) {
            return $this->hasMany(User::class, 'branch_id');
        }

        return $this->hasMany(User::class, 'id', 'id')->whereRaw('1 = 0');
    }
}

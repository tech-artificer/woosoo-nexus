<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;   
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens, HasRoles;

    // protected $with = ['roles'];
    protected $appends = ['role', 'status'];
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_uuid',
        'name',
        'email',
        'is_admin',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'updated_at',
        'email_verified_at',
        'pin'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot() : void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->user_uuid)) {
                $model->user_uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Returns the user's role as a string, or null if no role is assigned.
     * 
     * This accessor method returns the first role assigned to the user, or
     * null if no roles are assigned. The role is returned as a string, which
     * is the role name.
     * 
     * @return string|null
     */
    public function getRoleAttribute()
    {
        return $this->getRoleNames()->first(); // returns the first role or null
    }

    /**
     * Returns the user's status as 'Active' or 'Inactive'.
     *
     * This accessor method is used to determine if a user is active or inactive
     * based on the presence of the `deleted_at` timestamp. If the user has a
     * `deleted_at` timestamp, they are considered inactive, otherwise they are
     * active.
     *
     * @return string
     */
     public function getStatusAttribute()
    {   
        if( $this->deleted_at ) {
            return 'Inactive';
        }
        return 'Active';
         
    }

    # SCOPES
    public function scopeActive(Builder $query) 
    {
        return $query->where(['is_admin' => false])->withTrashed();
    }

}

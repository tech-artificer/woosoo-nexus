<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\AuditLog
 *
 * Append-only audit trail. Never update or soft-delete audit records.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'event',
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
        'request_id',
        'created_at',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
        'actor_id'   => 'integer',
        'subject_id' => 'integer',
    ];
}

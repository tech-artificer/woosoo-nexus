<?php

namespace App\Models\Krypton;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Terminal extends Model
{
    protected $connection = 'pos';
    protected $table = 'terminals';
    protected $primaryKey = 'id';

    protected $casts = [
        'id' => 'integer',
    ];

    public function scopePOS(Builder $query, $sessionId = 1)
    {
        return $query->where([
            'receipt_prefix' => 'POS1',
            'type' => 'terminal',
            'session_id' => $sessionId
        ]);
    }
}

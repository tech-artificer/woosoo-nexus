<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

/**
 * TableLink model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps.
 */
class TableLink extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_links';
    public $timestamps = false; // POS DB tables have no timestamp columns

    protected $fillable = [
        'order_id',
        'table_id',
        'primary_table_id',
        'is_active',
        'is_billing_table',
        'link_color',
    ];

    public function createLinkTable() {
        $details = $this->toArray();
        $numberOfParameters = count($details);
        $placeholders = implode(', ', array_fill(0, $numberOfParameters, '?'));
        $params = array_values($details);

        try {
            return self::fromQuery('CALL create_link_table(' . $placeholders . ')', $params);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('POS create_link_table failed', ['order_id' => $this->order_id ?? null, 'error' => $e->getMessage()]);
            return collect([]);
        }
    }

}

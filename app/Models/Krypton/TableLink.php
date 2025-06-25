<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class TableLink extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_links';

    public $timestamps = false;


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
        // Create an array of '?' strings, one for each parameter.
        $placeholdersArray = array_fill(0, $numberOfParameters, '?');
        // Join them with a comma and space to form the placeholder string.
        $placeholders = implode(', ', $placeholdersArray);
        // 2. Extract Values
        // array_values() extracts all the values from the associative array
        // and returns them as a new numerically indexed array.
        $params = array_values($details);

        // Now, call your fromQuery method with the generated placeholders and parameters
        return TableLink::fromQuery('CALL create_link_table(' . $placeholders . ')', $params);
    }

}

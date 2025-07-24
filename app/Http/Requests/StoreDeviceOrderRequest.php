<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {   
        return [
            /**
             * 
             * @var integer
             * @example 2            
             */
            'guest_count' => ['required', 'integer', 'min:1'],
            // /**
            //  * 
            //  * @var integer
            //  * @example 36
            //  */
            // 'table_id' => ['required', 'integer'],
            /**
             * 
             * @var float
             * @example 988
             */
            'subtotal' => ['required', 'numeric', 'min:0'],
            /**
             * 
             * @var float
             * @example 118.56
             */
            'tax' => ['required', 'numeric', 'min:0'],
            /**
             * 
             * @var float
             * @example 967.80
             */
            /**
             * 
             * @var float
             * @example 0.00
             */
            'discount' => ['required', 'numeric', 'min:0'],
            /**
             * 
             * @var float
             * @example 1106.56
             */
            'total' => ['required', 'numeric', 'min:0'],
            /**
             * 
             * @var array {menu_id, name, quantity, price, note, subtotal, ordered_menu_id, tax, discount}
             * @example  [{"menu_id":46,"name":"Classic Feast","quantity":2,"price":399,"note":"this is a note","subtotal":898.00,"tax":107.76,"discount":0.00},
             * {"menu_id":96,"name":"Coke Zero","quantity":2,"price":45,"note":"this is a note","subtotal":90.00,"tax":10.80,"discount":0.00}]
             */
            'items' => ['required', 'array'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.tax' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['required', 'numeric', 'min:0'],
        ];
    }
}

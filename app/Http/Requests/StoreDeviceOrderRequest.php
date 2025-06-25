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
            'guest_count' => ['required', 'integer'],
             /**
             * 
             * @var integer
             * @example Test
             */
            'note' => ['nullable', 'string'],
             /**
             * 
             * @var integer
             * @example 798.00    
             */
            'total_amount' => ['required', 'numeric'],
             /**
             * 
             * @var array {menu_id, name, quantity, price, notes, subtotal, ordered_menu_id, tax, discount}
             * @example  [{"menu_id":46, "ordered_menu_id" : null,"name":"Classic Feast","kitchen_name":"Classic Feast","receipt_name":"Classic Feast","quantity":2,"price":399.00,"original_price":399.00,"notes":"this is a note","subtotal":798.00,"ordered_menu_id":null,"tax":0.00,"discount":0.00,"index": 1},
             * {"menu_id":49, "ordered_menu_id" : 46,"name":"Plain Samgyupsal","kitchen_name":"Plain Samgyupsal","receipt_name":"P1","quantity":2,"price":0.00,"original_price":0.00,"notes":"this is a note","subtotal":0.00,"ordered_menu_id":46,"tax":0.00,"discount":0.00,"index":2},
             *  {"menu_id":50, "ordered_menu_id" : 46,"name":"Kajun Bulmat Samgyupsal","kitchen_name":"Kajun Bulmat Samgyupsal", "receipt_name":"P2","quantity":2,"price":0.00,"original_price":0.00,"notes":"this is a note","subtotal":0.00,"ordered_menu_id":46,"tax":0.00,"discount":0.00,"index": 3},
             *  {"menu_id":51, "ordered_menu_id" : 46,"name":"Yangyeom Samgyupsal","kitchen_name":"Yangyeom Samgyupsal", "receipt_name":"P3","quantity":2,"price":0.00,"original_price":0.00,"notes":"this is a note","subtotal":0.00,"ordered_menu_id":46,"tax":0.00,"discount":0.00,"index": 4},
             *  {"menu_id":52, "ordered_menu_id" : 46,"name":"Citrus Burst Pepper Samgyupsal","kitchen_name":"Citrus Burst Pepper Samgyupsal", "receipt_name":"P4","quantity":2,"price":0.00,"original_price":0.00,"notes":"this is a note","subtotal":0.00,"ordered_menu_id":46,"tax":0.00,"discount":0.00,"index": 5},
             *  {"menu_id":53, "ordered_menu_id" : 46,"name":"Hyangcho Samgyupsal","kitchen_name":"Hyangcho Samgyupsal", "receipt_name":"P5","quantity":2,"price":0.00,"original_price":0.00,"notes":"this is a note","subtotal":0.00,"ordered_menu_id":46,"tax":0.00,"discount":0.00,"index": 6}]
             */
            'items' => ['required', 'array'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.ordered_menu_id' => ['required', 'string'],
            'items.*.name' => ['required', 'string'],
            'items.*.receipt_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.subtotal' => ['required', 'numeric'],
            'items.*.ordered_menu_id' => ['nullable', 'integer'],
            'items.*.tax' => ['nullable', 'numeric'],
            'items.*.discount' => ['nullable', 'numeric'],

        // return [
        //     'total_amount' => ['nullable', 'numeric'],
        //     'note' => ['nullable', 'string'],
        //     'guest_count' => ['nullable', 'integer','min:1'],
        //     'items' => ['nullable', 'array'],
        //     'items.*.menu_id' => ['nullable', 'integer'],
        //     'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        //     'items.*.price' => ['nullable', 'numeric'],
        //     'items.*.note' => ['nullable', 'string'],
        //     'items.*.subtotal' => ['nullable', 'numeric'],
        //     'items.*.ordered_menu_id' => ['nullable', 'integer'],
        //     'items.*.tax' => ['nullable', 'numeric'],
        //     'items.*.discount' => ['nullable', 'numeric'],
        //     // 'order' => ['required', 'integer'],
        //     // 'menu_id' => ['required', 'integer', 'exists:menus,id'],
        //     // 'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
        //     // 'menu_item_modifiers' => ['required', 'array', 'exists:menu_item_modifiers,id'],
        //     // 'menu_item_modifiers.*' => ['required', 'integer', 'exists:menu_item_modifiers,id'],
        //     // 'quantity' => ['required', 'integer'],
        //     // 'price' => ['required', 'numeric'],
        //     // 'is_done' => ['required', 'boolean'],
        //     // 'is_cancelled' => ['required', 'boolean'],  
        //     // 'is_ready' => ['required', 'boolean'],
        ];
    }
}

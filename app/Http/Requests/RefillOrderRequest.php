<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Krypton\Menu as KryptonMenu;

/**
 * Validation for refill order requests.
 * 
 * Ensures:
 * - Only meats and sides categories are allowed
 * - Items exist in POS menu system
 * - Quantities are reasonable
 */
class RefillOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled at the controller level (device/session validation)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.menu_id' => ['nullable', 'integer', 'exists:krypton_woosoo.menu,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.index' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.seat_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one refill item is required.',
            'items.min' => 'At least one refill item is required.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
            'items.*.quantity.max' => 'Item quantity cannot exceed 50.',
            'items.*.menu_id.exists' => 'One or more menu items do not exist in the POS system.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Custom validation for refill-only items (meats/sides).
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            
            foreach ($items as $index => $item) {
                $name = trim(strval($item['name'] ?? ''));
                
                // Try to find the menu item in POS
                $menu = null;
                
                if (!empty($item['menu_id'])) {
                    // If menu_id provided, verify it exists
                    try {
                        $menu = KryptonMenu::find($item['menu_id']);
                    } catch (\Throwable $_e) {
                        $menu = null;
                    }
                } else {
                    // Try to find by name
                    try {
                        $menu = KryptonMenu::whereRaw('LOWER(receipt_name) = ?', [strtolower($name)])->first()
                            ?? KryptonMenu::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                    } catch (\Throwable $_e) {
                        $menu = null;
                    }
                }
                
                if (!$menu) {
                    $validator->errors()->add("items.{$index}.name", "Menu item not found: {$name}");
                    continue;
                }
                
                // Validate that item is in refillable categories (meats/sides)
                $refillableCategories = ['meats', 'sides']; // Adjust based on your POS category names
                $category = strtolower(trim($menu->category ?? ''));
                
                if (!empty($category) && !in_array($category, $refillableCategories, true)) {
                    $validator->errors()->add(
                        "items.{$index}.name",
                        "Item '{$name}' is not available for refill (only meats and sides can be refilled)."
                    );
                }
            }
        });
    }
}

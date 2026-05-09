<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
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
            'items'             => ['required', 'array', 'min:1'],
            'items.*.menu_id'   => ['required', 'integer', 'min:1'],
            'items.*.quantity'  => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.name'      => ['nullable', 'string', 'max:255'],
            'items.*.price'     => ['nullable', 'numeric', 'min:0'],
            'items.*.index'     => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.seat_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.note'      => ['nullable', 'string', 'max:255'],
            'session_id'        => ['nullable', 'string'],
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
                $menuId = (int) ($item['menu_id'] ?? 0);
                $label  = $item['name'] ?? "menu_id:{$menuId}";

                $menu = null;
                try {
                    $menu = KryptonMenu::find($menuId);
                } catch (\Throwable $_e) {
                    $menu = null;
                }

                if (!$menu) {
                    $validator->errors()->add("items.{$index}.menu_id", "Menu item not found: {$label}");
                    continue;
                }
                
                // Validate that item is in refillable groups (meats/sides only)
                // Check menu_group, not menu_category
                try {
                    $menu->load('group');
                    
                    // Refillable groups include: Meats, Sides, and specific meat types
                    $refillableGroups = [
                        'meats', 'sides', 'meat', 'side',
                        'meat beef', 'meat chicken', 'meat pork', 'meat seafood',
                        'pork', 'beef', 'chicken', 'seafood',
                        'vegetable', 'salad', // Add sides-like items
                    ];
                    
                    $groupName = $menu->group ? strtolower(trim($menu->group->name ?? '')) : '';
                    
                    if (!empty($groupName) && !in_array($groupName, $refillableGroups, true)) {
                        $validator->errors()->add(
                            "items.{$index}.menu_id",
                            "Item '{$label}' is not available for refill (only meats and sides can be refilled)."
                        );
                    } elseif (empty($groupName)) {
                        $validator->errors()->add(
                            "items.{$index}.menu_id",
                            "Item '{$label}' has no menu group and cannot be refilled."
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error('Refill validation error', ['error' => $e->getMessage(), 'item' => $label]);
                    $validator->errors()->add(
                        "items.{$index}.menu_id",
                        "Unable to verify refill eligibility for '{$label}'."
                    );
                }
            }
        });
    }
}

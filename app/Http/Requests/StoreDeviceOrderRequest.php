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
     * Strip any non-intent fields before validation so client-supplied pricing,
     * modifiers, and POS mapping never enter validated() output.
     *
     * Contract: contracts/tablet-api.contract.md (intent-only payload).
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(static fn ($item) => is_array($item))
            ->map(static fn (array $item) => [
                'menu_id' => $item['menu_id'] ?? null,
                'quantity' => $item['quantity'] ?? null,
            ])
            ->values()
            ->all();

        $this->replace([
            'guest_count' => $this->input('guest_count'),
            'package_id' => $this->input('package_id'),
            'items' => $items,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'package_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}

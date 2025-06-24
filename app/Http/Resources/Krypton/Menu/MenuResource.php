<?php

namespace App\Http\Resources\Krypton\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $placeholder = asset('images/menu-placeholder/1.jpg');
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kitchen_name' => $this->kitchen_name,
            'receipt_name' => $this->receipt_name,
            'price' => $this->price,
            'is_available' => $this->is_available,
            'is_modifier' => $this->is_modifier,
            'index' => $this->index,
            'category' => $this->category?->name,
            'course_type' => $this->course?->name,
            'group' => $this->group?->name,
            'image_url' => $this->image?->path
                                ? Storage::disk('public')->url($this->image?->path) 
                                : $placeholder,
            'modifiers' => $this->whenLoaded(
                'modifiers',  
                MenuResource::collection($this->modifiers) ?? []
            ),

        ];
    }
}

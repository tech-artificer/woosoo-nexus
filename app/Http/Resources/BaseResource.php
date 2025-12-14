<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BaseResource provides small helpers and a consistent extension point
 * for API Resources. Keep behavior minimal to avoid surprising changes
 * to existing responses while enabling standardization for new code.
 */
class BaseResource extends JsonResource
{
    protected function dateField(?\DateTimeInterface $value): ?string
    {
        return $value?->format(\DateTime::ATOM) ?? null;
    }

    protected function moneyField($value): mixed
    {
        if (is_null($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }
        return $value;
    }

    protected function relation($relation, $resourceClass)
    {
        return $this->whenLoaded($relation, function () use ($resourceClass, $relation) {
            $related = $this->{$relation};
            if (is_null($related)) {
                return null;
            }
            return $resourceClass::make($related);
        });
    }
}

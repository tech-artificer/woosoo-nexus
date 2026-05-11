<?php

namespace App\Exceptions;

use RuntimeException;

class MenuItemUnavailableException extends RuntimeException
{
    /**
     * @param  array<int, int|string>  $missingIds
     */
    public static function forMissingIds(array $missingIds): self
    {
        $normalizedIds = array_values(array_unique(array_map(static fn ($id) => (int) $id, $missingIds)));
        sort($normalizedIds);

        return new self('Krypton menu items not found: '.implode(', ', $normalizedIds));
    }
}

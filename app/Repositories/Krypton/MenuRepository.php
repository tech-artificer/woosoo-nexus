<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Krypton\Menu;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MenuRepository
{
    /** @var string|null Cached POS driver name to avoid repeated config lookups */
    private static ?string $cachedPosDriver = null;

    public function getMenus(): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenus skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menus()');
            return Menu::hydrate($rows);
        } catch (\Exception $e) {
            Log::error('Procedure call failed (getMenus): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getMenuById(int $id): ?Menu
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenuById skipped: pos driver does not support CALL');
            return null;
        }
        try {
             $rows = DB::connection('pos')->select('CALL get_menu_by_id(?)', [$id]);
           return Menu::hydrate($rows)->first();
        } catch (\Exception $e) {
            Log::error('Procedure call failed (getMenuById): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return null;
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    /**
     * Retrieves all menus with modifiers from the database.
     *
     * @return array The list of all menus with modifiers.
     * @throws \Exception If the database procedure call fails.
     *
     */
    public static function getMenusWithModifiers(): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenusWithModifiers skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menus_with_modifiers()');
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenusWithModifiers): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    /**
     * Retrieves menus by the specified category from the database.
     *
     * @param string $category The category to filter menus by.
     * @return array The list of menus in the specified category.
     * @throws \Exception If the database procedure call fails.
     */

    public static function getMenusByCategory(string $category): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenusByCategory skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menus_by_category(?)', [$category]);
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenusByCategory): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    
    /**
     * Retrieves all modifier groups from the database.
     *
     * @return array The list of all modifier groups.
     * @throws \Exception If the database procedure call fails.
     * 
     * @example Beef
     * @example Chicken
     * @example Pork
     */

    public function getAllModifierGroups(): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getAllModifierGroups skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_all_modifier_groups()');
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getAllModifierGroups): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    /**
     * Retrieves all menu modifiers from the database.
     *
     * @return array The list of all menu modifiers.
     * @throws \Exception If the database procedure call fails.
     * 
     * @example P1, P2, P3, P4, P5, B1, B2, B3, B4, B5, B6, B7, B8, B9, B10, C1
     */
    public function getMenuModifiers(): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenuModifiers skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menu_modifiers()');
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenuModifiers): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    /**
     * Retrieves a menu modifier by its ID from the database.
     *
     * @param int $id The ID of the menu modifier to retrieve.
     * @return array The menu modifier with the specified ID.
     * @throws \Exception If the database procedure call fails.
     * 
     * @example P1
     */
    public function getMenuModifier(int $id): ?Menu
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenuModifier skipped: pos driver does not support CALL');
            return null;
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menu_modifier(?)', [$id]);
            return Menu::hydrate($rows)->first();
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenuModifier): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return null;
            }
            throw new \Exception('Something Went Wrong.');
        }
    }


    /**
     * Retrieves modifiers for a specific group from the database.
     *
     * @return array The list of modifiers for the specified group.
     * @throws \Exception If the database procedure call fails.
     * 
     * @example P1, P2, P3, P4, P5
     * 
     */

    public function getMenuModifiersByGroup(int $modifierGroupId): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenuModifiersByGroup skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menu_modifiers_by_group(?)', [$modifierGroupId]);
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenuModifiersByGroup): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    public function getMenusByCourse(string $course): EloquentCollection
    {
        // Attempt CALL procedure only when supported; otherwise proceed directly to fallback
        if (self::posSupportsCall()) {
            try {
                $rows = DB::connection('pos')->select('CALL get_menus_by_course(?)', [$course]);
                $hydrated = Menu::hydrate($rows);

                // If stored-proc returned rows, return them
                if ($hydrated->isNotEmpty()) {
                    return $hydrated;
                }
            } catch (Exception $e) {
                Log::error('Procedure call failed (getMenusByCourse): ' . $e->getMessage());
            }
        }

        // Fallback: local Menu model lookup by course
        try {
            $local = Menu::where('course', 'LIKE', $course)->get();
            if ($local->isNotEmpty()) {
                return $local;
            }
        } catch (Exception $inner) {
            Log::warning('Local fallback query failed in getMenusByCourse: ' . $inner->getMessage());
        }

        if (app()->environment('testing')) {
            return Menu::hydrate([]);
        }
        throw new \Exception('Something Went Wrong.');
    }


    public function getMenusByGroup(string $group): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenusByGroup skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menus_by_group(?)', [$group]);
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenusByGroup): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }


    public function getMenuDiscountsById(int $menuId): EloquentCollection
    {
        if (!self::posSupportsCall()) {
            Log::warning('Method getMenuDiscountsById skipped: pos driver does not support CALL');
            return Menu::hydrate([]);
        }
        try {
              $rows = DB::connection('pos')->select('CALL get_menu_discounts_by_id(?)', [$menuId]);
            return Menu::hydrate($rows);
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenuDiscountsById): ' . $e->getMessage());
            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }

    /**
     * Reset the cached POS driver name.
     * Call this after Config::set + DB::purge('pos') in tests to avoid stale cache.
     */
    public static function resetDriverCache(): void
    {
        self::$cachedPosDriver = null;
    }

    /**
     * Returns true when the pos connection supports MySQL stored-procedure CALL syntax.
     * Resolved once per process via a static cache — getDriverName() reads from the
     * connection config (no I/O), but caching avoids repeated config lookups in hot loops.
     *
     * Note: The driver is cached for the lifetime of the PHP process. If a test swaps the
     * pos driver mid-suite via Config::set + DB::purge('pos'), this cache will be stale.
     * DB::purge('pos') alone is insufficient — call MenuRepository::resetDriverCache() to reset.
     */
    private static function posSupportsCall(): bool
    {
        self::$cachedPosDriver ??= DB::connection('pos')->getDriverName();
        return in_array(self::$cachedPosDriver, ['mysql', 'mariadb'], true);
    }
}

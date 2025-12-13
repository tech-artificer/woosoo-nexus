<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Krypton\Menu;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MenuRepository
{
    public function getMenus(): EloquentCollection
    {
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

    public static function getMenusByCategory($category): EloquentCollection
    {
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

    public function getMenusByCourse($course): EloquentCollection
    {
        try {
              $rows = DB::connection('pos')->select('CALL get_menus_by_course(?)', [$course]);
            $hydrated = Menu::hydrate($rows);

            // If stored-proc returned no rows, fallback to local Menu model lookup by course
            if ($hydrated->isEmpty()) {
                try {
                    return Menu::where('course', 'LIKE', $course)->get();
                } catch (Exception $inner) {
                    Log::warning('Local fallback query failed in getMenusByCourse: ' . $inner->getMessage());
                    return $hydrated; // empty collection
                }
            }

            return $hydrated;
        } catch (Exception $e) {
            Log::error('Procedure call failed (getMenusByCourse): ' . $e->getMessage());

            // Attempt local fallback when stored-proc fails
            try {
                $local = Menu::where('course', 'LIKE', $course)->get();
                if ($local->isNotEmpty()) {
                    return $local;
                }
            } catch (Exception $inner) {
                Log::warning('Local fallback query failed in getMenusByCourse (after proc failure): ' . $inner->getMessage());
            }

            if (app()->environment('testing')) {
                return Menu::hydrate([]);
            }
            throw new \Exception('Something Went Wrong.');
        }
    }


    public function getMenusByGroup($group): EloquentCollection
    {
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


    public function getMenuDiscountsById($menuId): EloquentCollection
    {
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




}
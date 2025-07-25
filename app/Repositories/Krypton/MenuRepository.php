<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Krypton\Menu;

class MenuRepository
{
    public function getMenus()
    {
        try {
            return Menu::fromQuery('CALL get_menus()');
        } catch (\Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getMenuById(int $id)
    {
        try {
           return Menu::fromQuery('CALL get_menu_by_id(?)', [$id]);
        } catch (\Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
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
    public static function getMenusWithModifiers()
    {
        try {
            return Menu::fromQuery('CALL get_menus_with_modifiers()');
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new Exception('Something Went Wrong.');
        }
    }

    /**
     * Retrieves menus by the specified category from the database.
     *
     * @param string $category The category to filter menus by.
     * @return array The list of menus in the specified category.
     * @throws \Exception If the database procedure call fails.
     */

    public static function getMenusByCategory($category)
    {
        try {
            return Menu::fromQuery('CALL get_menus_by_category(?)', [$category]);
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new Exception('Something Went Wrong.');
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

    public function getAllModifierGroups()
    {
        try {
            return Menu::fromQuery('CALL get_all_modifier_groups()');
        } catch (Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
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
    public function getMenuModifiers()
    {
        try {
            return Menu::fromQuery('CALL get_menu_modifiers()');
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
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
    public function getMenuModifier(int $id)
    {
        try {
            return Menu::fromQuery('CALL get_menu_modifier(?)', $id);
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
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

    public function getMenuModifiersByGroup(int $modifierGroupId)
    {
        try {
            return Menu::fromQuery('CALL get_menu_modifiers_by_group(?)', [$modifierGroupId]);
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new Exception('Something Went Wrong.');
        }
    }

    public function getMenusByCourse($course)
    {
        try {
            return Menu::fromQuery('CALL get_menus_by_course(?)', [$course]);
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new Exception('Something Went Wrong.');
        }
    }


    public function getMenusByGroup($group)
    {
        try {
            return Menu::fromQuery('CALL get_menus_by_group(?)', [$group]);
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new Exception('Something Went Wrong.');
        }
    }


     public function getMenuDiscountsById($menuId)
    {
        try {
            return Menu::fromQuery('CALL get_menu_discounts_by_id(?)', [$menuId]);
        } catch (Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new Exception('Something Went Wrong.');
        }
    }




}
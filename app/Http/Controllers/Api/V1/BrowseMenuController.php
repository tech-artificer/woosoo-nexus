<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Repositories\Krypton\MenuRepository;
use App\Http\Resources\Krypton\Menu\MenuResource;

use App\Models\Krypton\Menu;

class BrowseMenuController extends Controller
{   

    protected $menuRepository;

    public function __construct(MenuRepository $menuRepository) {
        $this->menuRepository = $menuRepository;
    }

    /**
     * Get all menus
     * 
     * @example menus
     * @return array
     * 
     */
    public function getMenus()
    {   
        // Meats, Sides, Drinks
        $menus = $this->menuRepository->getMenus();
        // $menus->fill(->get());
        return MenuResource::collection($menus);
        // return response()->json($this->menuRepository->getMenus());
    }

    
    /**
     * Get all modifier groups
     * 
     * Returns all modifier groups. Adding a query param of modifiers = 1 will include the modifiers in the response 
     * 
     * @param Request $request modifiers = 1
     * 
     * @description Get all modifier groups
     * 
     * @queryParam modifiers boolean Whether to include modifiers in the response. Defaults to false.
     * 
     */
    public function getAllModifierGroups(Request $request)
    {   

        $request->validate([
            /**
             * @example 1
            */
            'modifiers' => ['nullable','boolean'],
        ]);

        if ( $request->has('modifiers') && $request->modifiers == true ) {

            $modifierGroups = $this->menuRepository->getAllModifierGroups();

            foreach($modifierGroups as $modifierGroup) {
                $modifierGroup->modifiers = $this->menuRepository->getMenuModifiersByGroup($modifierGroup->id);
            }

            // return response()->json($modifierGroups);
            return MenuResource::collection($modifierGroups);
            
        }
    
        // return response()->json($this->menuRepository->getAllModifierGroups());
        return MenuResource::collection($this->menuRepository->getAllModifierGroups());
    }


   
    /**
     * Get all menu modifiers.
     *
     * List of all menu modifiers like P1, P2, P3, P4, P5.
     * 
     * @example P1, P2, P3, P4, P5, B1, B2, B3, B4, B5, B6, B7, B8, B9, B10, C1
     * 
     */
    public function getMenuModifiers() 
    {
        return MenuResource::collection($this->menuRepository->getMenuModifiers());
        // return response()->json($this->menuRepository->getMenuModifiers());
    }

    /**
     * Get menu with modifiers [Set Meal].
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     */
    public function getMenusWithModifiers() 
    {   
        $menus = $this->menuRepository->getMenusWithModifiers();

        foreach($menus as $menu) {
            $details = Menu::findOrFail($menu->id);
            $menu->fill($details->toArray()); 
            $menu->modifiers = Menu::getModifiers($menu->id);
        }

        return MenuResource::collection($menus);
        
    }

    /**
     * Get all menus for the given course.
     * 
     * @queryParam course string The course name
     * 
     * @param Request $request course = starter
     * 
     * @example starter, main course, salad and soup, dessert
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByCourse(Request $request) 
    {   
        $request->validate([
            /**
             * @queryParam course string The course name
             * @example starter
            */
            'course' => ['required','string'],
        ]);

        $menus = $this->menuRepository->getMenusByCourse($request->course);

        return MenuResource::collection($menus);
    }

    /**
     * Get all menus for the given category.
     *
     * @param Request $request category = beverage
     * 
     * @example category = beverage
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByCategory(Request $request) 
    {   
        $request->validate([
            /**
             * @example beverage
            */
            'category' => ['required','string'],
        ]);

        $menus = $this->menuRepository->getMenusByCategory($request->category);

        return MenuResource::collection($menus);
    }
  
}

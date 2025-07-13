<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Repositories\Krypton\MenuRepository;
use App\Http\Resources\MenuResource;
use App\Http\Resources\MenuModifierResource;
use App\Models\Krypton\Menu;

class BrowseMenuApiController extends Controller
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
        $menus = $this->menuRepository->getMenus();
        return MenuResource::collection($menus);
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
            'modifiers' => ['nullable', 'boolean'],
        ]);


        $allModifierGroups = $this->menuRepository->getAllModifierGroups() ?? [];
        $menus = collect($allModifierGroups)->unique('id')->values() ?? [];
        
        if ( $request->has('modifiers') && $request->modifiers == true ) {
            foreach($menus as $menu) {
                $menu->load('modifiers');
                $menu->modifiers = $this->menuRepository->getMenuModifiersByGroup($menu->id);
            }
        }
        
        return MenuResource::collection($menus) ?? [];
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
        $menuModifierIds = $this->menuRepository->getMenuModifiers()->pluck('id');
        $menus = Menu::whereIn('id', $menuModifierIds)->get();
        return MenuModifierResource::collection($menus);
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
     
        if( $menus ) {
            $menus = Menu::whereIn('id', [46, 47, 48])->with('modifiers')->get();
        }

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
        
        $menusByCourse = $this->menuRepository->getMenusByCourse($request->course)->pluck('id') ?? [];
        $menus = Menu::whereIn('id', $menusByCourse)->get();

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

        $menusByCategory = $this->menuRepository->getMenusByCategory($request->category)->pluck('id') ?? [];
        $menus = Menu::whereIn('id', $menusByCategory)->get();

        return MenuResource::collection($menus);
    }
  
}

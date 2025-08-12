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
    public function getMenus(Request $request)
    {   
        $request->validate([
            /**
             * @example 1
            */
            'menu_id' => ['nullable', 'integer'],
        ]);

        if(  $request->has('menu_id') ) {
            $menu = Menu::with(['modifiers', 'image'])->where('id', $request->menu_id)->first();
            return new MenuResource($menu);
        }

        $menus = $this->menuRepository->getMenus();
        
        return MenuResource::collection($menus->load(['modifiers','image'])) ?? [];
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
        $menus = collect($allModifierGroups->load(['image']))->unique('id')->values() ?? [];
      
        if ( $request->has('modifiers') && $request->modifiers == true ) {
            foreach($menus as $menu) {
                $menu->load(['image']);
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
        $menus = Menu::with(['image'])->whereIn('id', $menuModifierIds)->get();
        return MenuModifierResource::collection($menus);
    }

    /**
     * Get menu with modifiers [Set Meal].
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @example P1, P2, P3, P4, P5
     * 
     */
    public function getMenusWithModifiers(Request $request) 
    {   
        $request->validate([
            /**
             * @example 1
            */
            'menu_id' => ['nullable', 'integer'],
        ]);

        $menus = $this->menuRepository->getMenusWithModifiers();

        if( $request->has('menu_id') ) {
            
            $menu = Menu::with(['modifiers', 'image'])->where('id', $request->menu_id)->first();
            return new MenuResource($menu);
        }

        if( $menus->isEmpty() ) {
            $menus = Menu::with(['modifiers', 'image'])->whereIn('id', [46, 47, 48])->get();
        }else{

            $menusWithModifiers = Menu::with(['modifiers', 'image'])->whereIn('id', $menus->pluck('id'))->get();

            foreach($menusWithModifiers as $menu) {
                $menu->modifiers = $menu->getModifiers($menu->id);
            }

            return MenuResource::collection($menusWithModifiers);
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
        $menus = Menu::with(['image'])->whereIn('id', $menusByCourse)->get();

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
        $menus = Menu::with(['image'])->whereIn('id', $menusByCategory)->get();

        return MenuResource::collection($menus);
    }

    /**
     * Get all menus for the given group.
     *
     * @param Request $request group = Sides
     * 
     * @example group = Sides
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByGroup(Request $request) 
    {   
        $request->validate([
            /**
             * @example Sides
            */
            'group' => ['required','string'],
        ]);

        $menusByGroup = $this->menuRepository->getMenusByGroup($request->group)->pluck('id') ?? [];
        $menus = Menu::with(['image'])->whereIn('id', $menusByGroup)->get();

        return MenuResource::collection($menus);
    }
  
}

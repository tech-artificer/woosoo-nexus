<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\MenuResource;
use App\Models\Krypton\MenuCourse;
use App\Models\Krypton\Menu;
use App\Models\Krypton\MenuGroup;
use App\Models\Krypton\MenuCategory;
use App\Repositories\Krypton\MenuRepository;
use Illuminate\Database\Eloquent\Builder;
use GuzzleHttp\Client;
use App\Services\ApiService;

class MenuBundleController extends Controller
{
    
    protected ApiService $apiService;

    // Inject the service into the constructor
    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }
    /**
     * Get All Menu Selections
     *
     * This endpoint will return a list of all menu items available for selection.
     *
     * @see \App\Repositories\Krypton\MenuRepository::getMenusWithModifiers()
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {   
        // Packages
        $packages =  $this->apiService->get('/api/menus/with-modifiers');
        // $packages = MenuRepository::getMenusWithModifiers();

        // if ($packages->isEmpty()) {
        //     $packages = Menu::with('modifiers')
        //         ->whereIn('id', [46, 47, 48])
        //         ->get();
        // }
        // // Load modifiers
        // $packages->load('modifiers');

        // $sides = $this->getMenusByGroup('Sides');
        // $desserts = $this->getMenusByCategory('Dessert');
        // $beverages = $this->getMenusByCategory('Beverage');

        return response()->json([
            'packages' =>  $packages,

            // 'sides' => [],  
            // 'desserts' => [],
            // 'alacarte' => [],
            // 'beverages' => [],


            // 'promos' => [],  // Placeholder for future promos logic
            // 'packages' => MenuResource::collection($packages),
            // 'sides' => MenuResource::collection($sides),
            // 'desserts' => MenuResource::collection($desserts),
            // 'alacarte' => [], // Placeholder for alacarte items
            // 'beverages' => MenuResource::collection($beverages),
        ]);
    }


    /**
     * Retrieve all menus belonging to a specific category.
     *
     * @param string $categoryName The name of the category to filter menus by.
     * @return \Illuminate\Support\Collection A collection of menus in the specified category.
     */
    private function getMenusByCategory(string $categoryName)
    {
        return Menu::whereHas('category', function (Builder $query) use ($categoryName) {
            $query->where('name', $categoryName);
        })->get();
    }

    /**
     * Retrieve menus by group name.
     *
     * @param string $groupName The group name to filter menus by.
     *
     * @return \Illuminate\Database\Eloquent\Collection<\App\Models\Krypton\Menu>
     */
    private function getMenusByGroup(string $groupName)
    {
        return Menu::whereHas('group', function (Builder $query) use ($groupName) {
            $query->where('name', $groupName);
        })->get();
    }
}

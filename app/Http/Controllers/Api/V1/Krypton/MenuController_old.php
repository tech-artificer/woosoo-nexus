<?php

namespace App\Http\Controllers\Api\V1\Krypton;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Resources\Krypton\Menu\MenuResource;
use App\Models\Krypton\Menu;
use App\Models\MenuImage;
use App\Http\Requests\FilterMenuRequest;

class MenuController extends Controller
{
    /**
     * Returns a list of all menu items
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(FilterMenuRequest $request)
    {  
        // $menus = Menu::query()
        //     ->with(['category', 'course', 'group', 'image'])
        //     ->filter($request->validated())
        //     ->available()
        //     ->priced()
        //     ->orderBy('name', 'asc')
        //     ->get();

        // return MenuResource::collection($menus);
    }

    /**
     * Returns a list of set menu items with modifiers
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    // public function setMeals(Request $request)
    // {
    //     $request->validate([
    //         'menu_id' => ['nullable', 'integer'],
    //     ]);

    //     $modifiersBySet = Menu::getModifiers();

    //     if( $request->has('menu_id') ) {
    //         $menu = Menu::findOrfail($request->menu_id);
    //         return new MenuResource($menu->load('modifiers'));
            
    //     }else{

    //         $menus = Menu::setMeals()
    //         ->with(['category', 'course', 'group', 'image', 'modifiers'])
    //         ->available()
    //         ->priced()
    //         ->orderBy('name', 'asc')
    //         ->get();
      
    //         foreach ($menus as $menu) {
    //             $modifierCodes = $modifiersBySet[$menu->id] ?? [];
    //             $menu->modifiers = Menu::available()
    //                                 ->whereIn('receipt_name', $modifierCodes)
    //                                 ->get();
    //         }
    //         return MenuResource::collection($menus);
    //     }

    
    // }

}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Krypton\Menu;
use Illuminate\Support\Str;
use App\Models\MenuImage;

class MenuController extends Controller
{
    public function index() 
    {

        $menus = Menu::with(['image'])->orderBy('name')->get();

        $menus = $menus->map(function ($menu) {
            return [
                'id' => $menu->id,
                'name' => $menu->name,
                'img_url' => $menu->image_url,
                // 'img_path' => $menu->image_url,
                // 'image' => MenuImage::where('menu_id', $menu->id)->first(), 
                'group' => $menu->group->name ?? null,
                'category' => $menu->category->name ?? null,
                'course' => $menu->course->name ?? null,
                'name' => $menu->name,
                'kitchen_name' => $menu->kitchen_name,
                'receipt_name' => $menu->receipt_name,
                'price' => $menu->price,
                'cost' => $menu->cost,
                'description' => $menu->description,
                'index' => $menu->index,
                'is_taxable' => $menu->is_taxable,
                'is_available' => $menu->is_available,
                'is_modifier' => $menu->is_modifier,
                'is_discountable' => $menu->is_discountable,
                'is_locked' => $menu->is_locked,
                'quantity' => $menu->quantity,
                'in_stock' => $menu->in_stock,
                'is_modifier_only' => $menu->is_modifier_only,
            ];
        });

        return Inertia::render('Menus', [
            'title' => 'Menus',
            'description' => 'List of Menus',
            'menus' => $menus,
        ]);
    }

    // public function edit(Menu $menu) 
    // {
    //     return Inertia::render('menus/Edit', [
    //         'title' => 'Menu',
    //         'description' => 'Menu',
    //         'menu' => new MenuResource($menu),
    //     ]);
    // }

    public function uploadImage(Request $request, Menu $menu) 
    {
        $validated = $request->validate([
            'image' => ['required'],
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            $oldPath = optional($menu->image)->path;
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            // Save new image
            $file = $request->file('image');
            $slug = Str::slug($menu->name);
            $filename = "{$slug}." . $file->getClientOriginalExtension();
            $path = $file->storeAs('menu/images', $filename, 'public');

            MenuImage::updateOrCreate(
                ['menu_id' => $menu->id],
                ['path' => $path]
            );
        }

        return back()->with(['success' => true]);
        // return redirect()->route('menus')->with(['success' => true]);

        // return response()->json([
        //     'menu' => [
        //         'id' => $menu->id,
        //         'name' => $menu->name,
        //         'price' => $menu->price,
        //         'img_url' => Storage::url($path),
        //     ],
        // ]);

        // // Delete existing image if it exists
        // if ($menu->image) {
        //     Storage::delete($menu->image->path);
        //     $menu->image->delete();
        // }

        // // Store the new image
        // $path = $request->file('image')->store('menu_images', 'public');

        // // Create or update the MenuImage record
        // MenuImage::create([
        //     'menu_id' => $menu->id,
        //     'path' => $path,
        // ]);

        // return response()->json([
        //     'menu' => [
        //         'id' => $menu->id,
        //         'name' => $menu->name,
        //         'price' => $menu->price,
        //         'img_url' => Storage::url($path),
        //     ],
        // ]);
    }
}

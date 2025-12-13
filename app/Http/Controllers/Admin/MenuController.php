<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Krypton\Menu;
use App\Services\StatsService;
use Illuminate\Support\Str;
use App\Models\MenuImage;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    public function index() 
    {
        $menus = Menu::with(['image', 'category', 'group', 'course'])->orderBy('name')->get();

        $menus = $menus->map(function ($menu) {
            return [
                'id' => $menu->id,
                'name' => $menu->name,
                'img_url' => $menu->image_url,
                'group' => $menu->group->name ?? null,
                'category' => $menu->category->name ?? null,
                'course' => $menu->course->name ?? null,
                'kitchen_name' => $menu->kitchen_name,
                'receipt_name' => $menu->receipt_name,
                'price' => $menu->price,
                'cost' => $menu->cost,
                'description' => $menu->description,
                'is_taxable' => $menu->is_taxable,
                'is_available' => $menu->is_available,
                'is_modifier' => $menu->is_modifier,
                'is_discountable' => $menu->is_discountable,
                'is_modifier_only' => $menu->is_modifier_only,
            ];
        });

        $spark = StatsService::sparklineForModel(Menu::class, 7, ['created_at', 'created_on']);

        $stats = [
            [ 'title' => 'Total Menus', 'value' => $menus->count(), 'subtitle' => 'All menu items', 'variant' => 'primary', 'sparkline' => $spark ],
            [ 'title' => 'Available', 'value' => $menus->where('is_available', true)->count(), 'subtitle' => 'Currently available', 'variant' => 'accent' ],
        ];

        return Inertia::render('Menus/Index', [
            'title' => 'Menus',
            'description' => 'List of Menus',
            'menus' => $menus,
            'stats' => $stats,
        ]);
    }

    public function bulkToggleAvailability(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:menus,id'],
            'is_available' => ['required', 'boolean'],
        ]);

        $updated = Menu::whereIn('id', $validated['ids'])
            ->update(['is_available' => $validated['is_available']]);

        $status = $validated['is_available'] ? 'enabled' : 'disabled';
        
        return redirect()->back()->with('success', "{$updated} menu(s) {$status} successfully.");
    }

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

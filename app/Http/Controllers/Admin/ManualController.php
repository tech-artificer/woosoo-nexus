<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ManualController extends Controller
{
    /**
     * System sections for organizing guides
     */
    private array $sections = [
        'admin' => [
            'title' => 'Admin Dashboard',
            'description' => 'Manage orders, users, devices, menus, and permissions from the web dashboard.',
        ],
        'tablet' => [
            'title' => 'Tablet Ordering (PWA)',
            'description' => 'Kiosk app for guests to browse menus and place orders at their table.',
        ],
        'relay' => [
            'title' => 'Printer Relay Device',
            'description' => 'Flutter app to connect Bluetooth thermal printers to the ordering system.',
        ],
    ];

    /**
     * Guide definitions - markdown content loaded from files
     */
    private array $guides = [
        'requirements' => [
            'section' => 'admin',
            'title' => 'System Requirements & Setup',
            'summary' => 'What you need to run the admin dashboard.',
        ],
        'login' => [
            'section' => 'admin',
            'title' => 'How to Log In',
            'summary' => 'Access the admin dashboard with your credentials.',
        ],
        'add-user' => [
            'section' => 'admin',
            'title' => 'How to Add a New User',
            'summary' => 'Create staff accounts and assign roles.',
        ],
        'register-device' => [
            'section' => 'admin',
            'title' => 'How to Register a Device',
            'summary' => 'Add tablets or relay devices to the system.',
        ],
        'manage-orders' => [
            'section' => 'admin',
            'title' => 'How to Manage Orders',
            'summary' => 'View, complete, print, and void orders.',
        ],
        'menu-availability' => [
            'section' => 'admin',
            'title' => 'How to Toggle Menu Availability',
            'summary' => 'Mark items as sold out or back in stock.',
        ],
        'tablet-requirements' => [
            'section' => 'tablet',
            'title' => 'System Requirements & Deployment',
            'summary' => 'Hardware, network, and browser requirements for the tablet kiosk.',
        ],
        'tablet-place-order' => [
            'section' => 'tablet',
            'title' => 'How to Place an Order (Guest Flow)',
            'summary' => 'Complete end-to-end ordering process from welcome to submission.',
        ],
        'tablet-navigation' => [
            'section' => 'tablet',
            'title' => 'How to Navigate the Menu',
            'summary' => 'Browse categories, view items, and understand the interface.',
        ],
        'tablet-settings' => [
            'section' => 'tablet',
            'title' => 'How to Access Settings',
            'summary' => 'Configure the tablet (PIN-protected).',
        ],
        'relay-requirements' => [
            'section' => 'relay',
            'title' => 'System Requirements & Setup',
            'summary' => 'Device, printer, and network requirements.',
        ],
        'relay-install' => [
            'section' => 'relay',
            'title' => 'How to Install the Relay App',
            'summary' => 'Download, install, and launch the printer relay app.',
        ],
        'relay-connect-printer' => [
            'section' => 'relay',
            'title' => 'How to Connect a Bluetooth Printer',
            'summary' => 'Pair and test the thermal printer connection.',
        ],
        'relay-check-status' => [
            'section' => 'relay',
            'title' => 'How to Check Connection Status',
            'summary' => 'Verify the relay is online and receiving print jobs.',
        ],
        'relay-troubleshoot' => [
            'section' => 'relay',
            'title' => 'How to Troubleshoot Printing Issues',
            'summary' => 'Fix common connection and printing problems.',
        ],
    ];

    /**
     * Load markdown content for a guide
     */
    private function loadMarkdown(string $guideId, string $section): string
    {
        // Map guide IDs to filenames
        $filename = str_replace(['admin-', 'tablet-', 'relay-'], '', $guideId);
        $filePath = resource_path("docs/guides/{$section}/{$filename}.md");
        
        if (File::exists($filePath)) {
            return File::get($filePath);
        }
        
        return "# Content Coming Soon\n\nThis guide is currently being prepared. Check back soon!";
    }

    public function index()
    {
        $guides = collect($this->guides)->map(function (array $guide, string $id) {
            $section = $guide['section'] ?? 'admin';
            
            return [
                'id' => $id,
                'section' => $section,
                'title' => $guide['title'],
                'summary' => $guide['summary'],
                'markdown' => $this->loadMarkdown($id, $section),
            ];
        })->values();

        return Inertia::render('Admin/Manual', [
            'sections' => $this->sections,
            'guides' => $guides,
        ]);
    }

    /**
     * Show the edit form for a guide
     */
    public function edit(string $id)
    {
        if (! isset($this->guides[$id])) {
            abort(404, 'Guide not found');
        }

        $guide = $this->guides[$id];
        $section = $guide['section'];
        
        return Inertia::render('Admin/ManualEdit', [
            'guide' => [
                'id' => $id,
                'section' => $section,
                'title' => $guide['title'],
                'summary' => $guide['summary'],
                'content' => $this->loadMarkdown($id, $section),
            ],
            'sections' => $this->sections,
        ]);
    }

    /**
     * Update guide content
     */
    public function update(Request $request, string $id)
    {
        // Validate guide ID
        if (! isset($this->guides[$id])) {
            return back()->withErrors(['guide' => 'Invalid guide ID']);
        }

        // Validate content
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:50000'], // 50KB limit
        ]);

        $guide = $this->guides[$id];
        $section = $guide['section'];
        $filename = str_replace(['admin-', 'tablet-', 'relay-'], '', $id);
        $filePath = resource_path("docs/guides/{$section}/{$filename}.md");

        // Ensure directory exists
        $directory = dirname($filePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Save content
        File::put($filePath, $validated['content']);

        return redirect()->route('manual.index')
            ->with('success', 'Guide updated successfully');
    }

    /**
     * Upload image for TipTap editor
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'], // 2MB
        ]);

        $targetDir = public_path('docs/guide-images');
        
        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // Generate unique filename
        $file = $request->file('image');
        $filename = time() . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        
        // Move file
        $file->move($targetDir, $filename);

        // Return URL for TipTap
        return response()->json([
            'url' => asset('docs/guide-images/' . $filename),
        ]);
    }
}

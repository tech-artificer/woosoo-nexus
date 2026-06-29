<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\Admin\AdminShellBadgeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            // Single-branch deployment: surface the one registered branch so the shell
            // can show its name where the static "HQ" label used to sit.
            'branch' => fn () => Branch::query()->first()?->only(['id', 'name', 'location']),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                // Override url with the actual request host (set via URL::forceRootUrl in
                // AppServiceProvider) so client-side route() calls generate same-origin URLs
                // regardless of whether the Pi is reached via IP or hostname.
                'url' => rtrim(url('/'), '/'),
                'location' => url()->current(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'navBadges' => fn () => $request->user()?->can('admin')
                ? app(AdminShellBadgeService::class)->counts()
                : ['orders' => 0, 'devices' => 0],
            'flash' => [
                'warning' => fn () => $request->session()->get('warning'),
                'success' => fn () => $request->session()->get('success'),
                'security_code_reveal' => fn () => $request->session()->get('security_code_reveal'),
            ],
        ];
    }
}

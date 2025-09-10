<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;

use App\Models\User;
use App\Helpers\AppEnvironment;
use App\Observers\OrderUpdateLogObserver;
use App\Models\OrderUpdateLog;
use App\Services\Krypton\KryptonContextService;
use App\Services\Krypton\OrderService;

// Spatie Roles/Permissions
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KryptonContextService::class, fn () => new KryptonContextService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(KryptonContextService $contextService): void
    {
        // Always return plain JSON (no "data" wrapper)
        JsonResource::withoutWrapping();

        // Share context-based sessions (from your Krypton service)
        Inertia::share($contextService->getCurrentSessions());

        // Roles & Permissions (moved to dedicated private method for clarity)
        $this->shareRolesAndPermissions();

        // API Docs (Scramble config)
        Scramble::configure()
            ->routes(fn (Route $route) => Str::startsWith($route->uri, 'api/'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

        // 🔹 Gates
        Gate::define('viewPulse', fn (User $user) => $user->is_admin);

        // 🔹 Observers
        OrderUpdateLog::observe(OrderUpdateLogObserver::class);
    }

    /**
     * Share roles and permissions with Inertia.
     *
     * This is broken out for readability & maintainability.
     */
    private function shareRolesAndPermissions(): void
    {
        $roles = Role::with(['permissions'])->withCount('users')->get(['id', 'name']);

        $allPermissions = Permission::all()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name, // keep original for saving
                'label' => $this->humanizePermission($p->name),
            ];
        });

        $groupedPermissions = $allPermissions->groupBy(fn ($permission) => explode('.', $permission['name'])[0])
            ->map(fn ($group) => $group->values());

        $assignedPermissions = $roles->mapWithKeys(function ($role) {
            return [$role->name => $role->permissions->pluck('name')];
        });

        Inertia::share([
            'server' => AppEnvironment::isCloud(),
            'roles' => $roles,
            'permissions' => $allPermissions,
            'groupedPermissions' => $groupedPermissions,
            'assignedPermissions' => $assignedPermissions,
        ]);
    }

    protected function humanizePermission(string $name) : string
    {
        $parts = explode('.', $name);

        if (count($parts) === 3) {
            [$entity, $anotherEntity, $action] = $parts;
            return ucfirst($action) . ' ' . ucfirst($anotherEntity) . ' ' . ucfirst($entity);
        }

        if (count($parts) === 2) {
            [$entity, $action] = $parts;
            return ucfirst($action) . ' ' . ucfirst($entity);
        }

        // fallback: just prettify words
        return ucfirst(str_replace('.', ' ', $name));
    }
}

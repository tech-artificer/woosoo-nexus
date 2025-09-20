<?php

namespace App\Providers;

use App\Models\User;
use App\Models\OrderUpdateLog;
use App\Observers\OrderUpdateLogObserver;
use App\Services\Krypton\KryptonContextService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Illuminate\Routing\Route;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Str;
// use Illuminate\Routing\Route;
// use App\Services\Krypton\OrderService;
// Spatie Roles/Permissions
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Cache;


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

        try {
            // Share context-based sessions (from your Krypton service)
            Inertia::share(app(KryptonContextService::class)->getCurrentSessions());
            // Roles & Permissions (moved to dedicated private method for clarity)
            $this->shareRolesAndPermissions();
        } catch (\Throwable $e) {
            \Log::warning("Skipping context share: " . $e->getMessage());
        }
        
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
        try {
            if (!DB::connection('mysql')->getPdo() || !Schema::hasTable('roles')) {
                return;
            }

            $data = Cache::remember('roles_permissions', now()->addHour(), function () {
                $roles = Role::with(['permissions'])->withCount('users')->get(['id', 'name']);
                $allPermissions = Permission::all()->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'label' => $this->humanizePermission($p->name),
                ]);
                $groupedPermissions = $allPermissions->groupBy(fn ($p) => explode('.', $p['name'])[0])->map(fn ($g) => $g->values());
                $assignedPermissions = $roles->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')]);

                return compact('roles', 'allPermissions', 'groupedPermissions', 'assignedPermissions');
            });

            Inertia::share($data);
        } catch (\Throwable $e) {
            \Log::warning("Skipping role/permission sharing: " . $e->getMessage());
        }
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

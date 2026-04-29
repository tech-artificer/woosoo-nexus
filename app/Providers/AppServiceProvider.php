<?php

namespace App\Providers;

use App\Models\DeviceOrder;
use App\Models\OrderUpdateLog;
use App\Models\User;
use App\Observers\DeviceOrderObserver;
use App\Observers\OrderUpdateLogObserver;
use App\Services\CertificatePathResolver;
use App\Services\Krypton\KryptonContextService;
use App\Services\LocalBranchResolver;
use App\Services\PosConnectionService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
// use Illuminate\Routing\Route;
// use App\Services\Krypton\OrderService;
// Spatie Roles/Permissions
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
// use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KryptonContextService::class, fn () => new KryptonContextService);
        $this->app->singleton(CertificatePathResolver::class, fn () => new CertificatePathResolver);
        $this->app->singleton(LocalBranchResolver::class, fn () => new LocalBranchResolver);
        $this->app->singleton(PosConnectionService::class, fn () => new PosConnectionService);
        // Register test-only service provider when running tests so we can
        // bind POS/Krypton repositories to fakes for isolation.
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            $this->app->register(TestServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(KryptonContextService $contextService, PosConnectionService $posConnection): void
    {
        // Force HTTPS URL generation when APP_URL is configured for HTTPS.
        // This ensures Ziggy, route(), asset(), and all URL helpers produce
        // https:// URLs even when the inner nginx→PHP-FPM chain does not
        // forward X-Forwarded-Proto in a form that TrustProxies can detect.
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Apply DB-stored POS credentials to the 'pos' connection (overrides .env defaults).
        $posConnection->applyFromDatabase();

        RateLimiter::for('device-order-create', function (Request $request) {
            $deviceId = $request->user('device')?->id ?? $request->user()?->id;
            $tokenKey = $request->bearerToken();

            return Limit::perMinute(10)->by(
                $deviceId
                    ? 'device:'.$deviceId
                    : ($tokenKey ? 'token:'.sha1($tokenKey) : $request->fingerprint())
            );
        });

        // Always return plain JSON (no "data" wrapper)
        JsonResource::withoutWrapping();

        try {
            // Share context-based sessions (from your Krypton service)
            Inertia::share(app(KryptonContextService::class)->getCurrentSessions());
            // Roles & Permissions (moved to dedicated private method for clarity)
            $this->shareRolesAndPermissions();
        } catch (\Throwable $e) {
            Log::warning('Skipping context share: '.$e->getMessage());
        }

        // API docs: disable runtime docs routes in production to avoid
        // request-time generation failures on constrained or unstable datasources.
        if (app()->environment('production')) {
            Scramble::ignoreDefaultRoutes();
        } else {
            Scramble::configure()
                ->routes(fn (Route $route) => Str::startsWith($route->uri, 'api/'))
                ->withDocumentTransformers(function (OpenApi $openApi) {
                    $openApi->secure(SecurityScheme::http('bearer'));
                });
        }

        // 🔹 Gates
        Gate::define('viewPulse', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            return (bool) ($user->is_admin || $user->can('view pulse'));
        });
        // Backwards-compatible shorthand used by middleware: `can:admin`
        Gate::define('admin', fn (User $user) => $user->is_admin);

        // 🔹 Observers
        OrderUpdateLog::observe(OrderUpdateLogObserver::class);
        DeviceOrder::observe(DeviceOrderObserver::class);
    }

    /**
     * Share roles and permissions with Inertia.
     *
     * This is broken out for readability & maintainability.
     */
    private function shareRolesAndPermissions(): void
    {
        // Avoid attempting to connect to MySQL for roles/permissions when
        // running tests — this spawns noisy warnings and is unnecessary
        // for the test environment.
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            return;
        }
        try {
            if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
                return;
            }

            $roles = Role::with(['permissions'])->withCount('users')->get(['id', 'name']);
            $allPermissions = Permission::all()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'label' => $this->humanizePermission($p->name),
            ]);
            $groupedPermissions = $allPermissions->groupBy(fn ($p) => explode('.', $p['name'])[0])->map(fn ($g) => $g);
            $assignedPermissions = $roles->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')]);

            $data = compact('roles', 'allPermissions', 'groupedPermissions', 'assignedPermissions');

            Inertia::share($data);
        } catch (\Throwable $e) {
            Log::warning('Skipping role/permission sharing: '.$e->getMessage());
        }
    }

    protected function humanizePermission(string $name): string
    {
        $parts = explode('.', $name);

        if (count($parts) === 3) {
            [$entity, $anotherEntity, $action] = $parts;

            return ucfirst($action).' '.ucfirst($anotherEntity).' '.ucfirst($entity);
        }

        if (count($parts) === 2) {
            [$entity, $action] = $parts;

            return ucfirst($action).' '.ucfirst($entity);
        }

        // fallback: just prettify words
        return ucfirst(str_replace('.', ' ', $name));
    }
}

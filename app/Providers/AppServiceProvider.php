<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\JsonResource;

use Inertia\Inertia;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\EmployeeLog;
use App\Models\Krypton\Session;
use Illuminate\Support\Facades\View;

use App\Services\Krypton\KryptonContextService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KryptonContextService::class, function () {
        return new KryptonContextService();
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(KryptonContextService $posSessionService): void
    {

        JsonResource::withoutWrapping();

        Inertia::share($posSessionService->getCurrentSessions());

        // Inertia::share([
        //     // Example: Categories
        //     'session' => fn () => Session::orderBy('created_on', 'Desc')->first(),
        //     'terminalSession' => fn () => TerminalSession::orderBy('created_on', 'Desc')->first(),
        //     'employeeLogs' => fn () => EmployeeLog::orderBy('created_on', 'Desc')->first()
        // ]);

        Scramble::configure()
        ->routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/');
        });

        Scramble::configure()
        ->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->is_admin;
        });


    }
}

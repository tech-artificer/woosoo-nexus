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
use App\Models\Krypton\Terminal;
use App\Models\Krypton\CashTraySession;
use App\Models\Krypton\TerminalService;
use App\Models\Krypton\Revenue;
use Illuminate\Support\Facades\View;

use App\Services\Krypton\KryptonContextService;
use App\Models\OrderUpdateLog;
use App\Observers\OrderUpdateLogObserver;

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
    public function boot(KryptonContextService $contextService): void
    {   
        // const page = usePage()
        // _token: page.props.csrf_token, for client side validation
        JsonResource::withoutWrapping();

        Inertia::share($contextService->getCurrentSessions());

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


        OrderUpdateLog::observe(OrderUpdateLogObserver::class);

    }
}

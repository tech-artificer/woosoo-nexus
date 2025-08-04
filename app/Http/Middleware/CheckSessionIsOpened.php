<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Krypton\KryptonContextService;

class CheckSessionIsOpened
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
       
        
        // if( $request->is('api/*') ) {
        //     $kryptonContextService = new KryptonContextService();
        //     $kryptonContextService->checkSessionIsOpened();

        //     if( $kryptonContextService->isOpen == false ) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Session is closed.',
        //         ]);
        //     }
        // }


        return $next($request);
    }
}

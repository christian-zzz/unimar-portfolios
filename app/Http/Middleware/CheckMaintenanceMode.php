<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->role !== 'admin') {
            $maintenance = Cache::remember('maintenance_mode_flag', 5, function () {
                return AppSetting::find('maintenance_mode')?->value['enabled'] ?? false;
            });
            
            if ($maintenance) {
                return response()->json([
                    'message' => 'La plataforma está en mantenimiento. Intenta más tarde.'
                ], 503);
            }
        }
        
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow admin API requests and admin panel
        if ($request->is('admin/*') || $request->is('api/admin/*')) {
            return $next($request);
        }
        
        // Check if maintenance mode is enabled
        // Use verify to prevent errors if table doesn't exist yet
        if (Schema::hasTable('settings')) {
            $maintenance = Setting::where('key', 'maintenance_mode')->value('value');
            
            if ($maintenance === 'true' || $maintenance === '1') {
                // Return 503 Service Unavailable with the custom maintenance view
                return response()->view('errors.maintenance', [], 503);
            }
        }

        return $next($request);
    }
}

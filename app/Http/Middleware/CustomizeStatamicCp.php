<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class CustomizeStatamicCp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Config::set('statamic.cp.custom_cms_name', 'RV Waarloos');
        Config::set('statamic.cp.custom_logo_url', Vite::asset('resources/images/rv-text.svg'));

        return $next($request);
    }
}

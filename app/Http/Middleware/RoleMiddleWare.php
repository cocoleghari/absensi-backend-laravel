<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleWare
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        if (!$request->user()) {
            return response()->json(['
            message' => 'Unauthorized -  Silahkan login terlebih dahulu'
            ], 401);
        }

        if ($request->user()-> role !== $role) {
            return response()->json([
                'message' => 'Akses ditolak - Anda tidak memiliki hak akses sebagai '. $role
                ], 403);
        }
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Tablica admin_users nema is_admin flag — svaki njen redak je admin,
        // pa je provjera da token uopće pripada adminu, a ne nekom drugom modelu.
        if (! $request->user() instanceof AdminUser) {
            abort(403, 'Nemate administratorske ovlasti.');
        }

        return $next($request);
    }
}

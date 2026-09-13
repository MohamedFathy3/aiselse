<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate. This is a coarse first line of defense
 * (e.g. "only Admins may hit /api/v1/admin/*"). Fine-grained, per-record
 * authorization (ownership, field-level rules) still belongs in Policies -
 * this middleware does NOT replace them.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        if (! in_array($user->role, $allowed, true)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }

        return $next($request);
    }
}

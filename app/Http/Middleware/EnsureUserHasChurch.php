<?php

namespace App\Http\Middleware;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasChurch
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->current_church_id) {
            $church = $user->churches()->first();

            if (! $church) {
                // User has no churches, create a default one
                $church = Church::create([
                    'name' => $user->name."'s Church",
                    'description' => 'Default production workspace for '.$user->name.'.',
                ]);

                $church->users()->attach($user->id, [
                    'role' => 'Admin',
                    'modules' => ['racks', 'trainings', 'diagrams', 'shopping_lists', 'cables'],
                ]);
            }

            $user->update([
                'current_church_id' => $church->id,
            ]);
        }

        return $next($request);
    }
}

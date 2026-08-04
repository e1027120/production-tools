<?php

namespace App\Http\Controllers;

use App\Models\PaDesign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaDesignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('pa_systems')) {
            abort(403, 'You do not have access to this module.');
        }

        $designs = PaDesign::where('church_id', $user->current_church_id)
            ->with('creator')
            ->orderBy('id', 'desc')
            ->get();

        $mappedDesigns = $designs->map(function ($d) {
            $speakersCount = 0;
            $zones = $d->data['zones'] ?? [];
            foreach ($zones as $zone) {
                $speakersCount += intval($zone['qty'] ?? 0);
            }

            return [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'speakers_count' => $speakersCount,
                'zones_count' => count($zones),
                'created_by' => $d->creator->name,
                'created_at' => $d->created_at?->toIso8601String(),
            ];
        });

        // Overall stats
        $totalSpeakers = 0;
        foreach ($designs as $d) {
            $zones = $d->data['zones'] ?? [];
            foreach ($zones as $zone) {
                $totalSpeakers += intval($zone['qty'] ?? 0);
            }
        }

        return Inertia::render('pa/Index', [
            'designs' => $mappedDesigns,
            'stats' => [
                'total_designs' => $designs->count(),
                'total_speakers' => $totalSpeakers,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('pa_systems') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have access to this module.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $paDesign = PaDesign::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'data' => [
                'zones' => [],
                'selectedStrategy' => 'discrete',
                'headroomFactor' => 1.5,
            ],
            'created_by' => $user->id,
        ]);

        return redirect()->route('pa-systems.show', $paDesign);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, PaDesign $paSystem): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('pa_systems')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($paSystem->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        return Inertia::render('pa/Editor', [
            'design' => [
                'id' => $paSystem->id,
                'name' => $paSystem->name,
                'description' => $paSystem->description,
                'data' => $paSystem->data ?: [
                    'zones' => [],
                    'selectedStrategy' => 'discrete',
                    'headroomFactor' => 1.5,
                ],
                'created_by' => $paSystem->creator->name,
                'created_at' => $paSystem->created_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaDesign $paSystem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('pa_systems') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have access to this module.');
        }

        if ($paSystem->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'data' => ['required', 'array'],
        ]);

        $paSystem->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'data' => $validated['data'],
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PaDesign $paSystem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('pa_systems') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have access to this module.');
        }

        if ($paSystem->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $paSystem->delete();

        return redirect()->route('pa-systems.index');
    }
}

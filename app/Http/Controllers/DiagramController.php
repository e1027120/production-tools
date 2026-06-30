<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiagramController extends Controller
{
    /**
     * Display technical diagrams list.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams')) {
            abort(403, 'You do not have access to this module.');
        }

        $diagrams = Diagram::where('church_id', $user->current_church_id)
            ->with('creator')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($d) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'type' => $d->type,
                    'description' => $d->description,
                    'created_by' => $d->creator->name,
                    'created_at' => $d->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('diagrams/Index', [
            'diagrams' => $diagrams,
        ]);
    }

    /**
     * Store a newly created diagram context.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams')) {
            abort(403, 'You do not have access to this module.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:blueprint,drawing'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $diagram = Diagram::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'data' => $validated['type'] === 'drawing' ? [
                'elements' => [],
                'canvas' => [
                    'width' => 1200,
                    'height' => 800,
                    'background' => '#ffffff',
                    'showGrid' => true,
                ],
            ] : [
                'nodes' => [],
                'edges' => [],
            ],
            'created_by' => $user->id,
        ]);

        return redirect()->route('diagrams.show', $diagram);
    }

    /**
     * Display technical diagrams canvas editor.
     */
    public function show(Request $request, Diagram $diagram): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($diagram->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        if ($diagram->type === 'drawing') {
            return Inertia::render('diagrams/DrawingEditor', [
                'diagram' => $diagram,
            ]);
        }

        return Inertia::render('diagrams/Editor', [
            'diagram' => $diagram,
        ]);
    }

    /**
     * Update technical diagram layout JSON structure.
     */
    public function update(Request $request, Diagram $diagram): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($diagram->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'data' => ['required', 'array'],
        ]);

        $diagram->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'data' => $validated['data'],
        ]);

        return redirect()->back();
    }

    /**
     * Delete diagram.
     */
    public function destroy(Request $request, Diagram $diagram): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($diagram->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $diagram->delete();

        return redirect()->route('diagrams.index');
    }
}

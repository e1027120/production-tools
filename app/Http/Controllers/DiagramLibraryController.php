<?php

namespace App\Http\Controllers;

use App\Models\DiagramLibraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagramLibraryController extends Controller
{
    /**
     * Display a listing of the library items.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams')) {
            abort(403, 'You do not have access to this module.');
        }

        $items = DiagramLibraryItem::where('church_id', $user->current_church_id)
            ->with('creator')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'elements' => $item->elements,
                    'created_by' => $item->creator->name,
                    'created_at' => $item->created_at?->toIso8601String(),
                ];
            });

        return response()->json($items);
    }

    /**
     * Store a newly created library item in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'elements' => ['required', 'array'],
        ]);

        $item = DiagramLibraryItem::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'elements' => $validated['elements'],
            'created_by' => $user->id,
        ]);

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'elements' => $item->elements,
            'created_by' => $user->name,
            'created_at' => $item->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Remove the specified library item from storage.
     */
    public function destroy(Request $request, DiagramLibraryItem $diagramLibraryItem): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('diagrams') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        if ($diagramLibraryItem->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized operation.');
        }

        $diagramLibraryItem->delete();

        return response()->json(['success' => true]);
    }
}

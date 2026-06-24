<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Rack;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class RackController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()->hasModuleAccess('racks')) {
            abort(403, 'You do not have access to this module.');
        }

        $racks = Rack::where('church_id', $request->user()->current_church_id)
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('racks/Index', [
            'racks' => $racks
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->hasModuleAccess('racks')) {
            abort(403, 'You do not have access to this module.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:4', 'max:48'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $rack = Rack::create([
            'name' => $validated['name'],
            'size' => $validated['size'],
            'description' => $validated['description'] ?? null,
            'devices' => [],
            'church_id' => $request->user()->current_church_id,
        ]);

        return redirect()->route('racks.show', $rack);
    }

    public function show(Request $request, Rack $rack): Response
    {
        if (! $request->user()->hasModuleAccess('racks')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($rack->church_id !== $request->user()->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        return Inertia::render('racks/Builder', [
            'rack' => $rack,
            'catalog' => \App\Models\CatalogDevice::whereNull('church_id')
                ->orWhere('church_id', $request->user()->current_church_id)
                ->orderBy('brand')
                ->orderBy('name')
                ->get()
        ]);
    }

    public function update(Request $request, Rack $rack): RedirectResponse
    {
        if (! $request->user()->hasModuleAccess('racks')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($rack->church_id !== $request->user()->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:4', 'max:48'],
            'description' => ['nullable', 'string', 'max:1000'],
            'devices' => ['nullable', 'array'],
        ]);

        $rack->update([
            'name' => $validated['name'],
            'size' => $validated['size'],
            'description' => $validated['description'] ?? null,
            'devices' => $validated['devices'] ?? [],
        ]);

        return redirect()->route('racks.show', $rack);
    }

    public function destroy(Request $request, Rack $rack): RedirectResponse
    {
        if (! $request->user()->hasModuleAccess('racks')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($rack->church_id !== $request->user()->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $rack->delete();

        return redirect()->route('racks.index');
    }
}

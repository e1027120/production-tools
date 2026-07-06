<?php

namespace App\Http\Controllers;

use App\Models\CatalogDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CatalogDeviceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->hasModuleAccess('racks') || ! $request->user()->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:Audio,Video,Projection,Network,Power'],
            'u_height' => ['required', 'integer', 'min:1', 'max:16'],
            'power_consumption' => ['required', 'integer', 'min:0'],
            'weight' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['church_id'] = $request->user()->current_church_id;

        CatalogDevice::create($validated);

        return redirect()->back();
    }
}

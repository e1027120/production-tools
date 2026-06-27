<?php

namespace App\Http\Controllers;

use App\Models\CablePlan;
use App\Models\CableType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CablePlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        $plans = CablePlan::where('church_id', $user->current_church_id)
            ->with('creator')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'floor_plan_path' => $plan->floor_plan_path,
                    'floor_plan_url' => $plan->floor_plan_path ? Storage::disk('public')->url($plan->floor_plan_path) : null,
                    'scale_pixels' => $plan->scale_pixels,
                    'scale_distance' => $plan->scale_distance,
                    'scale_unit' => $plan->scale_unit,
                    'slack_percent' => $plan->slack_percent,
                    'room_height' => $plan->room_height,
                    'cables_count' => count($plan->cables ?? []),
                    'created_by' => $plan->creator->name,
                    'created_at' => $plan->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('cables/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = CablePlan::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'scale_pixels' => 100.0,
            'scale_distance' => 5.0,
            'scale_unit' => 'm',
            'slack_percent' => 10.0,
            'room_height' => 3.5,
            'cables' => [],
            'created_by' => $user->id,
        ]);

        return redirect()->route('cable-plans.show', $plan);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, CablePlan $cablePlan): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($cablePlan->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $cablePlan->load('creator');

        return Inertia::render('cables/Editor', [
            'plan' => [
                'id' => $cablePlan->id,
                'name' => $cablePlan->name,
                'description' => $cablePlan->description,
                'floor_plan_path' => $cablePlan->floor_plan_path,
                'floor_plan_url' => $cablePlan->floor_plan_path ? Storage::disk('public')->url($cablePlan->floor_plan_path) : null,
                'scale_pixels' => $cablePlan->scale_pixels,
                'scale_distance' => $cablePlan->scale_distance,
                'scale_unit' => $cablePlan->scale_unit,
                'slack_percent' => $cablePlan->slack_percent,
                'room_height' => $cablePlan->room_height,
                'cables' => $cablePlan->cables ?? [],
                'totals_by_type' => $cablePlan->totals_by_type,
                'created_by' => $cablePlan->creator->name,
                'created_at' => $cablePlan->created_at?->toIso8601String(),
            ],
            'cableTypes' => CableType::getForChurch($user->current_church_id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CablePlan $cablePlan): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($cablePlan->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scale_pixels' => ['required', 'numeric', 'min:1'],
            'scale_distance' => ['required', 'numeric', 'min:0.1'],
            'scale_unit' => ['required', 'string', 'in:m,ft'],
            'slack_percent' => ['required', 'numeric', 'min:0'],
            'room_height' => ['required', 'numeric', 'min:0'],
            'cables' => ['nullable', 'array'],
        ]);

        $cablePlan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'scale_pixels' => $validated['scale_pixels'],
            'scale_distance' => $validated['scale_distance'],
            'scale_unit' => $validated['scale_unit'],
            'slack_percent' => $validated['slack_percent'],
            'room_height' => $validated['room_height'],
            'cables' => $validated['cables'] ?? [],
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, CablePlan $cablePlan): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($cablePlan->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        if ($cablePlan->floor_plan_path) {
            Storage::disk('public')->delete($cablePlan->floor_plan_path);
        }

        $cablePlan->delete();

        return redirect()->route('cable-plans.index');
    }

    /**
     * Upload a floor plan image file.
     */
    public function upload(Request $request, CablePlan $cablePlan): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($cablePlan->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'floor_plan' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:5120'], // Max 5MB
        ]);

        if ($cablePlan->floor_plan_path) {
            Storage::disk('public')->delete($cablePlan->floor_plan_path);
        }

        $path = $request->file('floor_plan')->store('floorplans', 'public');

        $cablePlan->update([
            'floor_plan_path' => $path,
        ]);

        return redirect()->back();
    }

    /**
     * Store a newly created cable type.
     */
    public function typesStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables')) {
            abort(403, 'You do not have access to this module.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cable_types')->where('church_id', $user->current_church_id),
            ],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'price_per_m' => ['required', 'numeric', 'min:0'],
        ]);

        CableType::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'color' => $validated['color'],
            'price_per_m' => $validated['price_per_m'],
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified cable type.
     */
    public function typesUpdate(Request $request, CableType $cableType): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables') || $cableType->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cable_types')
                    ->where('church_id', $user->current_church_id)
                    ->ignore($cableType->id),
            ],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'price_per_m' => ['required', 'numeric', 'min:0'],
        ]);

        $oldName = $cableType->name;
        $newName = $validated['name'];

        $cableType->update([
            'name' => $newName,
            'color' => $validated['color'],
            'price_per_m' => $validated['price_per_m'],
        ]);

        if ($oldName !== $newName) {
            $plans = CablePlan::where('church_id', $user->current_church_id)->get();
            foreach ($plans as $plan) {
                $cables = $plan->cables ?? [];
                $changed = false;
                foreach ($cables as &$cable) {
                    if (($cable['type'] ?? '') === $oldName) {
                        $cable['type'] = $newName;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $plan->update(['cables' => $cables]);
                }
            }
        }

        return redirect()->back();
    }

    /**
     * Remove the specified cable type.
     */
    public function typesDestroy(Request $request, CableType $cableType): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('cables') || $cableType->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $cableType->delete();

        return redirect()->back();
    }
}

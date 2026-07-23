<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceLog;
use App\Models\ServiceReminder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    /**
     * Display a listing of the assets with filters and metrics.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets')) {
            abort(403, 'You do not have access to this module.');
        }

        $query = Asset::where('church_id', $user->current_church_id);

        // Apply filters
        if ($request->filled('review_ids')) {
            $ids = explode(',', $request->review_ids);
            $query->whereIn('id', $ids);
        }

        if ($request->filled('category') && $request->category !== 'All') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('brand', 'like', $search)
                    ->orWhere('model', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('location', 'like', $search);
            });
        }

        $assets = $query->with('creator')
            ->orderBy('name', 'asc')
            ->get();

        // Calculate statistics
        $statsQuery = Asset::where('church_id', $user->current_church_id);
        $totalCount = $statsQuery->count();
        $totalValue = $statsQuery->sum('purchase_price');
        $maintenanceCount = $statsQuery->where('status', 'Maintenance')->count();
        $remindersCount = ServiceReminder::where('church_id', $user->current_church_id)
            ->where('status', 'Active')
            ->where('next_due_date', '<=', now()->addDays(7))
            ->count();

        return Inertia::render('assets/Index', [
            'assets' => $assets,
            'stats' => [
                'total_count' => $totalCount,
                'total_value' => (float) $totalValue,
                'maintenance_count' => $maintenanceCount,
                'reminders_count' => $remindersCount,
            ],
            'filters' => $request->only(['category', 'status', 'search', 'review_ids']),
        ]);
    }

    /**
     * Store a newly created asset in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:Audio,Video,Lighting,IT,Instrument,Stage,Other'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:Active,Maintenance,Retired,In Storage'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        Asset::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'category' => $validated['category'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
            'location' => $validated['location'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_price' => $validated['purchase_price'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->route('assets.index');
    }

    /**
     * Display the specified asset details, logs, and reminders.
     */
    public function show(Request $request, Asset $asset): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($asset->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized access.');
        }

        $asset->load(['creator']);

        $logs = MaintenanceLog::where('asset_id', $asset->id)
            ->with('creator')
            ->orderBy('service_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $reminders = ServiceReminder::where('asset_id', $asset->id)
            ->with('creator')
            ->orderBy('next_due_date', 'asc')
            ->get();

        return Inertia::render('assets/Show', [
            'asset' => $asset,
            'logs' => $logs,
            'reminders' => $reminders,
        ]);
    }

    /**
     * Update the specified asset in storage.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        if ($asset->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:Audio,Video,Lighting,IT,Instrument,Stage,Other'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:Active,Maintenance,Retired,In Storage'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset->update($validated);

        return redirect()->back();
    }

    /**
     * Remove the specified asset from storage.
     */
    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        if ($asset->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized access.');
        }

        $asset->delete();

        return redirect()->route('assets.index');
    }

    /**
     * Add a maintenance log to the asset.
     */
    public function storeLog(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        if ($asset->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'service_type' => ['required', 'string', 'in:Routine,Repair,Calibration,Upgrade,Emergency'],
            'status' => ['required', 'string', 'in:Scheduled,In Progress,Completed,Cancelled'],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'service_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        MaintenanceLog::create([
            'church_id' => $user->current_church_id,
            'asset_id' => $asset->id,
            'service_type' => $validated['service_type'],
            'status' => $validated['status'],
            'performed_by' => $validated['performed_by'] ?? null,
            'cost' => $validated['cost'],
            'service_date' => $validated['service_date'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->back();
    }

    /**
     * Add a service reminder to the asset.
     */
    public function storeReminder(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        if ($asset->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'in:Monthly,Quarterly,Semi-Annually,Annually,One-time'],
            'next_due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        ServiceReminder::create([
            'church_id' => $user->current_church_id,
            'asset_id' => $asset->id,
            'title' => $validated['title'],
            'frequency' => $validated['frequency'],
            'next_due_date' => $validated['next_due_date'],
            'status' => 'Active',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->back();
    }

    /**
     * Mark a service reminder as complete.
     */
    public function completeReminder(Request $request, Asset $asset, ServiceReminder $reminder): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('assets') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized operation.');
        }

        if ($asset->church_id !== $user->current_church_id || $reminder->asset_id !== $asset->id) {
            abort(403, 'Unauthorized access.');
        }

        // 1. Create a corresponding completed maintenance log
        MaintenanceLog::create([
            'church_id' => $user->current_church_id,
            'asset_id' => $asset->id,
            'service_type' => 'Routine',
            'status' => 'Completed',
            'performed_by' => 'Automated Reminder Complete',
            'cost' => 0,
            'service_date' => now(),
            'notes' => 'Completed service reminder: '.$reminder->title.($reminder->notes ? "\nOriginal notes: ".$reminder->notes : ''),
            'created_by' => $user->id,
        ]);

        // 2. Advance the next due date if recurring, or set to Completed if one-time
        if ($reminder->frequency === 'One-time') {
            $reminder->update([
                'status' => 'Completed',
            ]);
        } else {
            $date = Carbon::parse($reminder->next_due_date);
            switch ($reminder->frequency) {
                case 'Monthly':
                    $date->addMonth();
                    break;
                case 'Quarterly':
                    $date->addMonths(3);
                    break;
                case 'Semi-Annually':
                    $date->addMonths(6);
                    break;
                case 'Annually':
                    $date->addYear();
                    break;
            }

            $reminder->update([
                'next_due_date' => $date->toDateString(),
            ]);
        }

        return redirect()->back();
    }
}

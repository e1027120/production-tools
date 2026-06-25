<?php

use App\Http\Controllers\CablePlanController;
use App\Http\Controllers\CatalogDeviceController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\DiagramController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\TrainingController;
use App\Models\CablePlan;
use App\Models\CatalogDevice;
use App\Models\Diagram;
use App\Models\Rack;
use App\Models\ShoppingList;
use App\Models\Training;
use App\Models\TrainingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified', 'church'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $churchId = $request->user()->current_church_id;

        // 1. Racks Module
        $racks = Rack::where('church_id', $churchId)->orderBy('id', 'desc')->get();
        $totalRacks = $racks->count();
        $totalDevices = 0;
        $totalPower = 0;
        foreach ($racks as $rack) {
            $devs = $rack->devices ?? [];
            $totalDevices += count($devs);
            foreach ($devs as $dev) {
                $totalPower += $dev['power_consumption'] ?? 0;
            }
        }
        $latestRacks = $racks->take(3)->map(function ($rack) {
            $totalRackPower = 0;
            foreach ($rack->devices ?? [] as $dev) {
                $totalRackPower += $dev['power_consumption'] ?? 0;
            }

            return [
                'id' => $rack->id,
                'name' => $rack->name,
                'size' => $rack->size,
                'devices_count' => count($rack->devices ?? []),
                'power' => $totalRackPower,
            ];
        })->values();

        $totalCatalogDevices = CatalogDevice::whereNull('church_id')
            ->orWhere('church_id', $churchId)
            ->count();

        // 2. Shopping Lists Module
        $shoppingLists = ShoppingList::where('church_id', $churchId)
            ->with('items')
            ->orderBy('id', 'desc')
            ->get();
        $totalShoppingLists = $shoppingLists->count();
        $sharedShoppingLists = $shoppingLists->whereNotNull('share_token')->count();
        $totalBudgetPrice = $shoppingLists->sum(fn ($list) => $list->items->sum('total_price'));
        $latestShoppingLists = $shoppingLists->take(3)->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'items_count' => $list->items->count(),
                'total_price' => $list->items->sum('total_price'),
                'is_shared' => ! empty($list->share_token),
            ];
        })->values();

        // 3. Trainings Module
        $trainings = Training::where('church_id', $churchId)
            ->withCount('steps')
            ->orderBy('id', 'desc')
            ->get();
        $totalTrainings = $trainings->count();
        $pendingAssignments = TrainingAssignment::where('status', 'pending')
            ->whereHas('training', function ($q) use ($churchId) {
                $q->where('church_id', $churchId);
            })
            ->count();
        $latestTrainings = $trainings->take(3)->map(function ($t) {
            return [
                'id' => $t->id,
                'title' => $t->title,
                'ministry' => $t->ministry,
                'steps_count' => $t->steps_count,
            ];
        })->values();

        // 4. Technical Diagrams
        $diagrams = Diagram::where('church_id', $churchId)
            ->orderBy('id', 'desc')
            ->get();
        $totalDiagrams = $diagrams->count();
        $latestDiagrams = $diagrams->take(3)->map(function ($d) {
            return [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
            ];
        })->values();

        // 5. Cable Calculator Module
        $cablePlans = CablePlan::where('church_id', $churchId)->orderBy('id', 'desc')->get();
        $totalCablePlans = $cablePlans->count();
        $totalCableRuns = $cablePlans->sum(fn ($p) => count($p->cables ?? []));
        $totalCablesLength = 0.0;
        foreach ($cablePlans as $plan) {
            foreach ($plan->cables ?? [] as $cable) {
                $totalCablesLength += $plan->calculateCableLength($cable);
            }
        }
        $latestCablePlans = $cablePlans->take(3)->map(function ($p) {
            $planLength = 0.0;
            foreach ($p->cables ?? [] as $cable) {
                $planLength += $p->calculateCableLength($cable);
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'runs_count' => count($p->cables ?? []),
                'length' => $planLength,
                'unit' => $p->scale_unit,
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalRacks' => $totalRacks,
                'totalDevices' => $totalDevices,
                'totalPower' => $totalPower,
                'totalCatalogDevices' => $totalCatalogDevices,
                'totalShoppingLists' => $totalShoppingLists,
                'sharedShoppingLists' => $sharedShoppingLists,
                'totalBudgetPrice' => $totalBudgetPrice,
                'totalTrainings' => $totalTrainings,
                'pendingAssignments' => $pendingAssignments,
                'totalDiagrams' => $totalDiagrams,
                'totalCablePlans' => $totalCablePlans,
                'totalCableRuns' => $totalCableRuns,
                'totalCablesLength' => $totalCablesLength,
            ],
            'latestRacks' => $latestRacks,
            'latestShoppingLists' => $latestShoppingLists,
            'latestTrainings' => $latestTrainings,
            'latestDiagrams' => $latestDiagrams,
            'latestCablePlans' => $latestCablePlans,
        ]);
    })->name('dashboard');

    // Church Switcher & Settings Management
    Route::post('churches/{church}/switch', [ChurchController::class, 'switch'])->name('churches.switch');
    Route::post('churches', [ChurchController::class, 'store'])->name('churches.store');
    Route::get('church/settings', [ChurchController::class, 'settings'])->name('church.settings');
    Route::put('church/settings', [ChurchController::class, 'update'])->name('church.update');
    Route::post('church/users', [ChurchController::class, 'addUser'])->name('church.users.add');
    Route::put('church/users/{member}', [ChurchController::class, 'updateUser'])->name('church.users.update');
    Route::delete('church/users/{member}', [ChurchController::class, 'removeUser'])->name('church.users.remove');

    Route::resource('racks', RackController::class);
    Route::post('catalog-devices', [CatalogDeviceController::class, 'store'])->name('catalog-devices.store');

    // Trainings Module
    Route::resource('trainings', TrainingController::class)->except(['update']);
    Route::post('trainings/{training}', [TrainingController::class, 'update'])->name('trainings.update');
    Route::post('trainings/{training}/assign', [TrainingController::class, 'assign'])->name('trainings.assign');
    Route::get('trainings/{training}/play', [TrainingController::class, 'play'])->name('trainings.play');
    Route::post('trainings/{training}/submit', [TrainingController::class, 'submit'])->name('trainings.submit');
    Route::post('trainings/{training}/toggle-share', [TrainingController::class, 'toggleShare'])->name('trainings.toggle-share');

    // Technical Diagrams Module
    Route::resource('diagrams', DiagramController::class);

    // Shopping List Module
    Route::resource('shopping-lists', ShoppingListController::class);
    Route::post('shopping-lists/{shopping_list}/items', [ShoppingListController::class, 'addItem'])->name('shopping-lists.items.add');
    Route::put('shopping-lists/{shopping_list}/items/{item}', [ShoppingListController::class, 'updateItem'])->name('shopping-lists.items.update');
    Route::delete('shopping-lists/{shopping_list}/items/{item}', [ShoppingListController::class, 'removeItem'])->name('shopping-lists.items.remove');
    Route::post('shopping-lists/{shopping_list}/toggle-share', [ShoppingListController::class, 'toggleShare'])->name('shopping-lists.toggle-share');
    Route::post('shopping-lists/{shopping_list}/share-email', [ShoppingListController::class, 'shareEmail'])->name('shopping-lists.share-email');
    Route::post('shopping-lists/{shopping_list}/remove-shared-email', [ShoppingListController::class, 'removeSharedEmail'])->name('shopping-lists.remove-shared-email');

    // Cable Calculator Module
    Route::resource('cable-plans', CablePlanController::class);
    Route::post('cable-plans/{cable_plan}/upload', [CablePlanController::class, 'upload'])->name('cable-plans.upload');
});

// Public Guest Route for Shared Shopping Lists
Route::get('shopping-lists/shared/{token}', [ShoppingListController::class, 'viewShared'])->name('shopping-lists.shared.view');

// Public Guest Route for Shared Trainings / User Manuals Checklist
Route::get('trainings/shared/{token}', [TrainingController::class, 'viewShared'])->name('trainings.shared.view');

require __DIR__.'/settings.php';

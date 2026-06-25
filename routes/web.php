<?php

use App\Http\Controllers\CatalogDeviceController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\DiagramController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\TrainingController;
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
        $racks = Rack::where('church_id', $request->user()->current_church_id)->get();
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

        $totalCatalogDevices = CatalogDevice::whereNull('church_id')
            ->orWhere('church_id', $request->user()->current_church_id)
            ->count();

        // Shopping List Module Stats
        $shoppingLists = ShoppingList::where('church_id', $request->user()->current_church_id)
            ->with('items')
            ->get();

        $totalShoppingLists = $shoppingLists->count();
        $sharedShoppingLists = $shoppingLists->whereNotNull('share_token')->count();
        $totalBudgetPrice = $shoppingLists->sum(fn ($list) => $list->items->sum('total_price'));

        // Trainings Module Stats
        $totalTrainings = Training::where('church_id', $request->user()->current_church_id)->count();
        $pendingAssignments = TrainingAssignment::where('status', 'pending')
            ->whereHas('training', function ($q) use ($request) {
                $q->where('church_id', $request->user()->current_church_id);
            })
            ->count();

        // Technical Diagrams Stats
        $totalDiagrams = Diagram::where('church_id', $request->user()->current_church_id)->count();

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
            ],
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
});

// Public Guest Route for Shared Shopping Lists
Route::get('shopping-lists/shared/{token}', [ShoppingListController::class, 'viewShared'])->name('shopping-lists.shared.view');

require __DIR__.'/settings.php';

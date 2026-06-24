<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified', 'church'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $racks = \App\Models\Rack::where('church_id', $request->user()->current_church_id)->get();
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

        $totalCatalogDevices = \App\Models\CatalogDevice::whereNull('church_id')
            ->orWhere('church_id', $request->user()->current_church_id)
            ->count();

        return \Inertia\Inertia::render('Dashboard', [
            'stats' => [
                'totalRacks' => $totalRacks,
                'totalDevices' => $totalDevices,
                'totalPower' => $totalPower,
                'totalCatalogDevices' => $totalCatalogDevices,
            ]
        ]);
    })->name('dashboard');

    // Church Switcher & Settings Management
    Route::post('churches/{church}/switch', [App\Http\Controllers\ChurchController::class, 'switch'])->name('churches.switch');
    Route::post('churches', [App\Http\Controllers\ChurchController::class, 'store'])->name('churches.store');
    Route::get('church/settings', [App\Http\Controllers\ChurchController::class, 'settings'])->name('church.settings');
    Route::put('church/settings', [App\Http\Controllers\ChurchController::class, 'update'])->name('church.update');
    Route::post('church/users', [App\Http\Controllers\ChurchController::class, 'addUser'])->name('church.users.add');
    Route::put('church/users/{member}', [App\Http\Controllers\ChurchController::class, 'updateUser'])->name('church.users.update');
    Route::delete('church/users/{member}', [App\Http\Controllers\ChurchController::class, 'removeUser'])->name('church.users.remove');

    Route::resource('racks', App\Http\Controllers\RackController::class);
    Route::post('catalog-devices', [App\Http\Controllers\CatalogDeviceController::class, 'store'])->name('catalog-devices.store');

    // Trainings Module
    Route::resource('trainings', App\Http\Controllers\TrainingController::class)->except(['update']);
    Route::post('trainings/{training}', [App\Http\Controllers\TrainingController::class, 'update'])->name('trainings.update');
    Route::post('trainings/{training}/assign', [App\Http\Controllers\TrainingController::class, 'assign'])->name('trainings.assign');
    Route::get('trainings/{training}/play', [App\Http\Controllers\TrainingController::class, 'play'])->name('trainings.play');
    Route::post('trainings/{training}/submit', [App\Http\Controllers\TrainingController::class, 'submit'])->name('trainings.submit');

    // Technical Diagrams Module
    Route::resource('diagrams', App\Http\Controllers\DiagramController::class);
});

require __DIR__.'/settings.php';

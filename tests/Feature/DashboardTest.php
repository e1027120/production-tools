<?php

use App\Models\User;
use App\Models\Rack;
use App\Models\CatalogDevice;
use App\Models\Church;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard and see accurate stats', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // Create some catalog devices
    CatalogDevice::factory()->count(5)->create(['church_id' => $church->id]);

    // Create racks and devices
    Rack::factory()->create([
        'church_id' => $church->id,
        'devices' => [
            [
                'id' => 'dev_1',
                'brand' => 'Behringer',
                'name' => 'X32 Rack',
                'type' => 'Audio',
                'u_height' => 3,
                'power_consumption' => 120,
                'weight' => 6.5,
            ],
            [
                'id' => 'dev_2',
                'brand' => 'UniFi',
                'name' => 'Dream Machine Pro',
                'type' => 'Network',
                'u_height' => 1,
                'power_consumption' => 50,
                'weight' => 3.9,
            ]
        ]
    ]);

    Rack::factory()->create([
        'church_id' => $church->id,
        'devices' => [
            [
                'id' => 'dev_3',
                'brand' => 'Behringer',
                'name' => 'S16 Stage Box',
                'type' => 'Audio',
                'u_height' => 2,
                'power_consumption' => 45,
                'weight' => 4.6,
            ]
        ]
    ]);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    // Assert that the computed statistics match expected values
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('stats.totalRacks', 2)
        ->where('stats.totalDevices', 3)
        ->where('stats.totalPower', 215) // 120 + 50 + 45 = 215
        ->where('stats.totalCatalogDevices', 5)
    );
});
<?php

use App\Models\User;
use App\Models\Rack;
use App\Models\Church;

test('guests are redirected to the login page when accessing racks', function () {
    $response = $this->get(route('racks.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view the racks list', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $rack = Rack::factory()->create(['church_id' => $church->id]);

    $response = $this->get(route('racks.index'));
    $response->assertOk();
    $response->assertSee($rack->name);
});

test('authenticated users can store a new rack', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('racks.store'), [
        'name' => 'Studio A Main Rack',
        'size' => 24,
        'description' => 'Main audio distribution rack.',
    ]);

    $rack = Rack::where('name', 'Studio A Main Rack')->first();
    expect($rack)->not->toBeNull();
    $response->assertRedirect(route('racks.show', $rack));
});

test('authenticated users can view a specific rack builder', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $rack = Rack::factory()->create(['church_id' => $church->id]);

    $response = $this->get(route('racks.show', $rack));
    $response->assertOk();
});

test('authenticated users can update a rack configuration and device list', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $rack = Rack::factory()->create(['church_id' => $church->id]);

    $devices = [
        [
            'id' => 'dev_99',
            'brand' => 'Behringer',
            'name' => 'X32 Rack',
            'type' => 'Audio',
            'u_height' => 3,
            'position' => 1,
            'power_consumption' => 120,
            'heat_dissipation' => 410,
            'weight' => 6.5
        ]
    ];

    $response = $this->put(route('racks.update', $rack), [
        'name' => 'Updated Rack Name',
        'size' => 16,
        'description' => 'Updated description.',
        'devices' => $devices
    ]);

    $response->assertRedirect(route('racks.show', $rack));

    $rack->refresh();
    expect($rack->name)->toBe('Updated Rack Name');
    expect($rack->size)->toBe(16);
    expect($rack->devices)->toHaveCount(1);
    expect($rack->devices[0]['name'])->toBe('X32 Rack');
});

test('authenticated users can delete a rack', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $rack = Rack::factory()->create(['church_id' => $church->id]);

    $response = $this->delete(route('racks.destroy', $rack));
    $response->assertRedirect(route('racks.index'));

    $this->assertDatabaseMissing('racks', [
        'id' => $rack->id
    ]);
});

test('guests are redirected to the login page when creating a catalog device', function () {
    $response = $this->post(route('catalog-devices.store'), [
        'brand' => 'Allen & Heath',
        'name' => 'SQ-5',
        'type' => 'Audio',
        'u_height' => 4,
        'power_consumption' => 90,
        'weight' => 10.5,
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can store a new catalog device', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('catalog-devices.store'), [
        'brand' => 'Allen & Heath',
        'name' => 'SQ-5',
        'type' => 'Audio',
        'u_height' => 4,
        'power_consumption' => 90,
        'weight' => 10.5,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('catalog_devices', [
        'brand' => 'Allen & Heath',
        'name' => 'SQ-5',
        'type' => 'Audio',
        'u_height' => 4,
        'power_consumption' => 90,
        'weight' => 10.5,
    ]);
});

test('catalog device validation constraints are enforced', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('catalog-devices.store'), [
        'brand' => '',
        'name' => '',
        'type' => 'InvalidType',
        'u_height' => 0,
        'power_consumption' => -5,
        'weight' => -1.2,
    ]);

    $response->assertSessionHasErrors([
        'brand',
        'name',
        'type',
        'u_height',
        'power_consumption',
        'weight',
    ]);
});

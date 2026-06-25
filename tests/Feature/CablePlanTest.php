<?php

use App\Models\CablePlan;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('users without cables module access are blocked', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);

    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['racks'], // no cables access
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->get(route('cable-plans.index'));
    $response->assertStatus(403);
});

test('users with cables module can manage cable plans', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);

    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['cables'],
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. View Index
    $response = $this->get(route('cable-plans.index'));
    $response->assertOk();

    // 2. Store Plan
    $response = $this->post(route('cable-plans.store'), [
        'name' => 'Sanctuary Conduit Mapping',
        'description' => 'FOH mixer to stage boxes and lighting bridge',
    ]);

    $plan = CablePlan::where('name', 'Sanctuary Conduit Mapping')->first();
    expect($plan)->not->toBeNull();
    $response->assertRedirect(route('cable-plans.show', $plan));

    // 3. Load Editor
    $response = $this->get(route('cable-plans.show', $plan));
    $response->assertOk();

    // 4. Update Plan with custom cables drawn and calibration settings
    $cablesData = [
        [
            'id' => 'cable_1',
            'name' => 'FOH Snake Link',
            'type' => 'Audio XLR',
            'color' => '#ff0000',
            'points' => [
                ['x' => 100, 'y' => 100, 'z' => 1.2], // start at wall outlet
                ['x' => 100, 'y' => 100, 'z' => 6.0], // go up to ceiling tray
                ['x' => 400, 'y' => 100, 'z' => 6.0], // run horizontally across tray
                ['x' => 400, 'y' => 200, 'z' => 0.0], // drop to stage floor box
            ],
            'notes' => 'Route through conduit pipe A',
        ],
    ];

    $response = $this->put(route('cable-plans.update', $plan), [
        'name' => 'Sanctuary Conduit Mapping (Revised)',
        'description' => 'Revised blueprint design',
        'scale_pixels' => 150.0,
        'scale_distance' => 15.0, // 1px = 0.1 units (meters/feet)
        'scale_unit' => 'm',
        'slack_percent' => 10.0, // 10% slack
        'room_height' => 3.5,
        'cables' => $cablesData,
    ]);

    $response->assertRedirect();
    $plan->refresh();
    expect($plan->name)->toBe('Sanctuary Conduit Mapping (Revised)');
    expect($plan->scale_unit)->toBe('m');
    expect($plan->slack_percent)->toBe(10.0);
    expect($plan->room_height)->toBe(3.5);
    expect($plan->cables)->toHaveCount(1);

    // Verify 2D + 2x room height length computation
    // dx1 = 0, dy1 = 0. scaled_d = 0. segment_d = 0
    // dx2 = (400-100)*0.1 = 30, dy2 = 0. segment_d = 30
    // dx3 = 0, dy3 = (200-100)*0.1 = 10. segment_d = 10
    // total raw horizontal = 40.0
    // plus 2 * room_height (3.5) = 47.0
    // with 10% slack = 47.0 * 1.1 = 51.70
    expect($plan->totals_by_type['Audio XLR'])->toBeGreaterThan(51.6);
    expect($plan->totals_by_type['Audio XLR'])->toBeLessThan(51.8);

    // 5. Delete Plan
    $response = $this->delete(route('cable-plans.destroy', $plan));
    $response->assertRedirect(route('cable-plans.index'));
    expect(CablePlan::find($plan->id))->toBeNull();
});

test('floor plan uploader stores images correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $plan = CablePlan::create([
        'church_id' => $church->id,
        'name' => 'Sanctuary plan',
        'created_by' => $user->id,
    ]);

    $file = UploadedFile::fake()->image('floor_plan.png', 1200, 800);

    $response = $this->post(route('cable-plans.upload', $plan), [
        'floor_plan' => $file,
    ]);

    $response->assertRedirect();
    $plan->refresh();
    expect($plan->floor_plan_path)->not->toBeNull();
    Storage::disk('public')->assertExists($plan->floor_plan_path);
});

test('cable plans are isolated between church workspaces', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $churchA = Church::create(['name' => 'Church A']);
    $churchB = Church::create(['name' => 'Church B']);

    $churchA->users()->attach($userA->id, ['role' => 'Admin']);
    $churchB->users()->attach($userB->id, ['role' => 'Admin']);

    $userA->update(['current_church_id' => $churchA->id]);
    $userB->update(['current_church_id' => $churchB->id]);

    $planA = CablePlan::create([
        'church_id' => $churchA->id,
        'name' => 'Church A Layout Plan',
        'created_by' => $userA->id,
    ]);

    // User B tries to view Church A plan
    $this->actingAs($userB);
    $response = $this->get(route('cable-plans.show', $planA));
    $response->assertStatus(403);
});

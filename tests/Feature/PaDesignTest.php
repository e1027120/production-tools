<?php

use App\Models\Church;
use App\Models\PaDesign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users without pa_systems module access are blocked', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);

    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['racks'], // no pa_systems access
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->get('/pa-systems');
    $response->assertStatus(403);
});

test('users with pa_systems module can manage pa designs', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);

    $church->users()->attach($user->id, [
        'role' => 'Manager',
        'modules' => ['pa_systems'],
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. View Index
    $response = $this->get('/pa-systems');
    $response->assertOk();

    // 2. Store Design
    $response = $this->post('/pa-systems', [
        'name' => 'Main Auditorium Array Config',
        'description' => 'Left/Right hangs plus front fills and sub bass clusters.',
    ]);

    $design = PaDesign::first();
    $response->assertRedirect("/pa-systems/{$design->id}");
    expect($design->name)->toBe('Main Auditorium Array Config');
    expect($design->data)->toBe([
        'zones' => [],
        'selectedStrategy' => 'discrete',
        'headroomFactor' => 1.5,
    ]);

    // 3. View individual Editor Page
    $response = $this->get("/pa-systems/{$design->id}");
    $response->assertOk();

    // 4. Update PA Design
    $updatedData = [
        'name' => 'Main Auditorium Array Config v2',
        'description' => 'Updated Left/Right hangs with more delay fills.',
        'data' => [
            'zones' => [
                [
                    'id' => 'zone_123',
                    'name' => 'FOH Mains',
                    'type' => 'array',
                    'qty' => 6,
                    'impedance' => 8,
                    'power_rms' => 500,
                    'sensitivity' => 99,
                    'wiring' => 'parallel',
                    'target_distance' => 15,
                ]
            ],
            'selectedStrategy' => 'biamp',
            'headroomFactor' => 1.5,
        ]
    ];

    $response = $this->put("/pa-systems/{$design->id}", $updatedData);
    $response->assertRedirect("/pa-systems/{$design->id}");

    $design->refresh();
    expect($design->name)->toBe('Main Auditorium Array Config v2');
    expect($design->data['selectedStrategy'])->toBe('biamp');
    expect($design->data['zones'])->toHaveCount(1);
    expect($design->data['zones'][0]['name'])->toBe('FOH Mains');

    // 5. Delete PA Design
    $response = $this->delete("/pa-systems/{$design->id}");
    $response->assertRedirect('/pa-systems');

    expect(PaDesign::count())->toBe(0);
});
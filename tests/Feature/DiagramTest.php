<?php

use App\Models\User;
use App\Models\Church;
use App\Models\Diagram;

test('users without diagrams module access are blocked', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Methodist Church']);
    
    // User role with NO diagrams module
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['racks'], // only racks
    ]);
    
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->get(route('diagrams.index'));
    $response->assertStatus(403);
});

test('users with diagrams module can list and create diagrams', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Methodist Church']);
    
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['diagrams'],
    ]);
    
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. Can view index
    $response = $this->get(route('diagrams.index'));
    $response->assertOk();

    // 2. Can create diagram
    $response = $this->post(route('diagrams.store'), [
        'name' => 'FOH Audio Setup',
        'description' => 'FOH mixer outputs to main speakers',
    ]);

    $diagram = Diagram::where('name', 'FOH Audio Setup')->first();
    expect($diagram)->not->toBeNull();
    $response->assertRedirect(route('diagrams.show', $diagram));

    // 3. Can load editor
    $response = $this->get(route('diagrams.show', $diagram));
    $response->assertOk();

    // 4. Can save diagram structure
    $response = $this->put(route('diagrams.update', $diagram), [
        'name' => 'FOH Audio Setup (Updated)',
        'description' => 'New description',
        'data' => [
            'nodes' => [
                ['id' => '1', 'type' => 'custom', 'position' => ['x' => 10, 'y' => 20], 'data' => ['label' => 'Mixer']]
            ],
            'edges' => []
        ]
    ]);
    $response->assertRedirect();

    $diagram->refresh();
    expect($diagram->name)->toBe('FOH Audio Setup (Updated)');
    expect($diagram->description)->toBe('New description');
    expect($diagram->data['nodes'][0]['data']['label'])->toBe('Mixer');

    // 5. Can delete diagram
    $response = $this->delete(route('diagrams.destroy', $diagram));
    $response->assertRedirect(route('diagrams.index'));
    expect(Diagram::find($diagram->id))->toBeNull();
});

test('diagrams are isolated between church workspaces', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $churchA = Church::create(['name' => 'Church A']);
    $churchB = Church::create(['name' => 'Church B']);

    $churchA->users()->attach($userA->id, ['role' => 'Admin']);
    $churchB->users()->attach($userB->id, ['role' => 'Admin']);

    $userA->update(['current_church_id' => $churchA->id]);
    $userB->update(['current_church_id' => $churchB->id]);

    // Create diagram in Church A
    $diagramA = Diagram::create([
        'church_id' => $churchA->id,
        'name' => 'Church A Layout',
        'created_by' => $userA->id,
        'data' => ['nodes' => [], 'edges' => []],
    ]);

    // User B tries to view Church A diagram
    $this->actingAs($userB);
    $response = $this->get(route('diagrams.show', $diagramA));
    $response->assertStatus(403);

    // User B tries to update Church A diagram
    $response = $this->put(route('diagrams.update', $diagramA), [
        'name' => 'Hacked Name',
        'data' => ['nodes' => [], 'edges' => []],
    ]);
    $response->assertStatus(403);
    expect($diagramA->refresh()->name)->toBe('Church A Layout');
});

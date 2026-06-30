<?php

use App\Models\Church;
use App\Models\Diagram;
use App\Models\User;

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
        'type' => 'blueprint',
        'description' => 'FOH mixer outputs to main speakers',
    ]);

    $diagram = Diagram::where('name', 'FOH Audio Setup')->first();
    expect($diagram)->not->toBeNull();
    expect($diagram->type)->toBe('blueprint');
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
                ['id' => '1', 'type' => 'custom', 'position' => ['x' => 10, 'y' => 20], 'data' => ['label' => 'Mixer']],
            ],
            'edges' => [],
        ],
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
        'type' => 'blueprint',
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

test('users can create and edit free drawings', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Methodist Church']);
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['diagrams'],
    ]);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. Create Free Drawing
    $response = $this->post(route('diagrams.store'), [
        'name' => 'Sanctuary Stage Free Drawing',
        'type' => 'drawing',
        'description' => 'Layout map of sanctuary stage',
    ]);

    $diagram = Diagram::where('name', 'Sanctuary Stage Free Drawing')->first();
    expect($diagram)->not->toBeNull();
    expect($diagram->type)->toBe('drawing');
    $response->assertRedirect(route('diagrams.show', $diagram));

    // 2. Load DrawingEditor view
    $response = $this->get(route('diagrams.show', $diagram));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('diagrams/DrawingEditor'));

    // 3. Save drawing properties
    $response = $this->put(route('diagrams.update', $diagram), [
        'name' => 'Sanctuary Stage (Updated)',
        'description' => 'Updated desc',
        'data' => [
            'elements' => [
                [
                    'id' => '1',
                    'type' => 'rectangle',
                    'x' => 150,
                    'y' => 150,
                    'width' => 100,
                    'height' => 100,
                    'fillColor' => '#ffffff',
                    'strokeColor' => '#000000',
                    'strokeWidth' => 2,
                    'strokeStyle' => 'solid',
                ],
            ],
            'canvas' => [
                'width' => 1000,
                'height' => 600,
                'background' => '#efefef',
                'showGrid' => true,
            ],
        ],
    ]);
    $response->assertRedirect();

    $diagram->refresh();
    expect($diagram->name)->toBe('Sanctuary Stage (Updated)');
    expect($diagram->description)->toBe('Updated desc');
    expect($diagram->data['elements'][0]['type'])->toBe('rectangle');
    expect($diagram->data['canvas']['background'])->toBe('#efefef');
});

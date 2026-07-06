<?php

use App\Models\Church;
use App\Models\DiagramLibraryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('managers and admins can manage diagram library items', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);
    $church->users()->attach($user->id, [
        'role' => 'Manager',
        'modules' => ['diagrams'],
    ]);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. Initially index is empty
    $response = $this->get(route('diagram-library-items.index'));
    $response->assertOk();
    $response->assertJsonCount(0);

    // 2. Can store library item
    $elements = [
        [
            'id' => 'element_1',
            'type' => 'rectangle',
            'x' => 10,
            'y' => 10,
            'width' => 100,
            'height' => 100,
            'fillColor' => '#ffffff',
            'strokeColor' => '#000000',
        ],
    ];

    $response = $this->post(route('diagram-library-items.store'), [
        'name' => 'Custom Dual Speaker Group',
        'description' => 'FOH audio speaker stacking template',
        'elements' => $elements,
    ]);

    $response->assertStatus(201);
    $item = DiagramLibraryItem::where('name', 'Custom Dual Speaker Group')->first();
    expect($item)->not->toBeNull();
    expect($item->elements)->toHaveCount(1);
    expect($item->church_id)->toBe($church->id);

    // 3. Can see in index list
    $response = $this->get(route('diagram-library-items.index'));
    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['name' => 'Custom Dual Speaker Group']);

    // 4. Can delete library item
    $response = $this->delete(route('diagram-library-items.destroy', $item));
    $response->assertOk();
    expect(DiagramLibraryItem::find($item->id))->toBeNull();
});

test('diagram library items are isolated between church workspaces', function () {
    $userA = User::factory()->create();
    $churchA = Church::create(['name' => 'Church A']);
    $churchA->users()->attach($userA->id, ['role' => 'Admin', 'modules' => ['diagrams']]);
    $userA->update(['current_church_id' => $churchA->id]);

    $userB = User::factory()->create();
    $churchB = Church::create(['name' => 'Church B']);
    $churchB->users()->attach($userB->id, ['role' => 'Admin', 'modules' => ['diagrams']]);
    $userB->update(['current_church_id' => $churchB->id]);

    $itemA = DiagramLibraryItem::create([
        'church_id' => $churchA->id,
        'name' => 'Church A Template',
        'elements' => [['id' => '1', 'type' => 'circle']],
        'created_by' => $userA->id,
    ]);

    // User B tries to view library
    $this->actingAs($userB);
    $response = $this->get(route('diagram-library-items.index'));
    $response->assertOk();
    $response->assertJsonCount(0); // cannot see Church A template

    // User B tries to delete Church A item
    $response = $this->delete(route('diagram-library-items.destroy', $itemA));
    $response->assertStatus(403);
});

test('users with User role cannot modify diagram library items', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary Chapel']);
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['diagrams'],
    ]);
    $user->update(['current_church_id' => $church->id]);
    $item = DiagramLibraryItem::create([
        'church_id' => $church->id,
        'name' => 'Existing template',
        'elements' => [['id' => '1', 'type' => 'circle']],
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    // Try store
    $response = $this->post(route('diagram-library-items.store'), [
        'name' => 'Unauthorized Template',
        'elements' => [['id' => '1', 'type' => 'circle']],
    ]);
    $response->assertStatus(403);

    // Try destroy
    $response = $this->delete(route('diagram-library-items.destroy', $item));
    $response->assertStatus(403);
});

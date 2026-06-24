<?php

use App\Models\User;
use App\Models\Church;
use App\Models\Rack;
use App\Models\CatalogDevice;

test('newly registered users get a default church and are set as admin', function () {
    $action = new \App\Actions\Fortify\CreateNewUser();
    $newUser = $action->create([
        'name' => 'Pastor Dave',
        'email' => 'dave@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($newUser->current_church_id)->not->toBeNull();
    
    $church = $newUser->currentChurch;
    expect($church->name)->toBe("Pastor Dave's Church");

    $pivot = $newUser->currentChurchMember();
    expect($pivot->role)->toBe('Admin');
    expect($pivot->modules)->toContain('racks');
});

test('users can switch between their churches', function () {
    $user = User::factory()->create();
    $church1 = Church::create(['name' => 'First Methodist']);
    $church2 = Church::create(['name' => 'First Baptist']);

    $church1->users()->attach($user->id, ['role' => 'Admin']);
    $church2->users()->attach($user->id, ['role' => 'Manager']);

    $user->update(['current_church_id' => $church1->id]);
    $this->actingAs($user);

    $response = $this->post(route('churches.switch', $church2));
    $response->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->current_church_id)->toBe($church2->id);
});

test('users cannot switch to a church they do not belong to', function () {
    $user = User::factory()->create();
    $church1 = Church::create(['name' => 'First Methodist']);
    $church2 = Church::create(['name' => 'First Baptist']);

    $church1->users()->attach($user->id, ['role' => 'Admin']);

    $user->update(['current_church_id' => $church1->id]);
    $this->actingAs($user);

    $response = $this->post(route('churches.switch', $church2));
    $response->assertStatus(403);
});

test('racks are scoped to the active church', function () {
    $user = User::factory()->create();
    $church1 = Church::create(['name' => 'Church 1']);
    $church2 = Church::create(['name' => 'Church 2']);

    $church1->users()->attach($user->id, ['role' => 'Admin']);
    $church2->users()->attach($user->id, ['role' => 'Admin']);

    // Create racks in both churches
    $rack1 = Rack::factory()->create(['name' => 'Rack In Church 1', 'church_id' => $church1->id]);
    $rack2 = Rack::factory()->create(['name' => 'Rack In Church 2', 'church_id' => $church2->id]);

    $user->update(['current_church_id' => $church1->id]);
    $this->actingAs($user);

    // Get racks index, should only see rack1
    $response = $this->get(route('racks.index'));
    $response->assertOk();
    $response->assertSee('Rack In Church 1');
    $response->assertDontSee('Rack In Church 2');
});

test('admin role permissions are enforced', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // Admin can rename church details
    $response = $this->put(route('church.update'), [
        'name' => 'New Name',
        'description' => 'New Desc',
    ]);
    $response->assertRedirect();
    $church->refresh();
    expect($church->name)->toBe('New Name');
});

test('manager role cannot rename church but can manage members', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Church']);
    $church->users()->attach($user->id, ['role' => 'Manager']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // Manager cannot rename church details
    $response = $this->put(route('church.update'), [
        'name' => 'New Name',
    ]);
    $response->assertStatus(403);
});

test('standard user module access is gated', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Church']);
    
    // User role with racks module
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['racks'],
    ]);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // Should have access
    $response = $this->get(route('racks.index'));
    $response->assertOk();

    // Detach and re-attach WITHOUT racks module
    $church->users()->detach($user->id);
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => [],
    ]);

    $user->refresh();

    // Should be blocked
    $response = $this->get(route('racks.index'));
    $response->assertStatus(403);
});

test('admin can create a new church, which automatically switches context', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Initial Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('churches.store'), [
        'name' => 'Newly Created Church',
        'description' => 'A description of the new church',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user->refresh();
    $newChurch = Church::where('name', 'Newly Created Church')->first();

    expect($newChurch)->not->toBeNull();
    expect($user->current_church_id)->toBe($newChurch->id);
    expect($newChurch->users()->where('users.id', $user->id)->first()->pivot->role)->toBe('Admin');
    expect($newChurch->users()->where('users.id', $user->id)->first()->pivot->modules)->toContain('racks');
});

test('non-admins cannot create a new church', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Initial Church']);
    $church->users()->attach($user->id, ['role' => 'Manager']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('churches.store'), [
        'name' => 'Attempted Church',
    ]);

    $response->assertStatus(403);
    expect(Church::where('name', 'Attempted Church')->exists())->toBeFalse();
});


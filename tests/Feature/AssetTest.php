<?php

use App\Models\Asset;
use App\Models\Church;
use App\Models\MaintenanceLog;
use App\Models\ServiceReminder;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('managers and admins can manage assets, logs, and reminders', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Calvary']);
    $church->users()->attach($user->id, [
        'role' => 'Manager',
        'modules' => ['assets'],
    ]);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. Index initially empty
    $response = $this->get(route('assets.index'));
    $response->assertOk();

    // 2. Can create asset
    $response = $this->post(route('assets.store'), [
        'name' => 'Audio Console X32',
        'category' => 'Audio',
        'brand' => 'Behringer',
        'model' => 'X32',
        'serial_number' => 'BEH-X32-9923',
        'status' => 'Active',
        'location' => 'Sanctuary FOH',
        'purchase_date' => '2026-01-15',
        'purchase_price' => 2499.00,
        'notes' => '32 channel digital sound board',
    ]);
    $response->assertRedirect(route('assets.index'));

    $asset = Asset::where('name', 'Audio Console X32')->first();
    expect($asset)->not->toBeNull();
    expect($asset->church_id)->toBe($church->id);
    expect($asset->purchase_price)->toEqual(2499.00);

    // 3. Can view asset details
    $response = $this->get(route('assets.show', $asset));
    $response->assertOk();

    // 4. Can update asset specs
    $response = $this->put(route('assets.update', $asset), [
        'name' => 'Audio Console X32 Updated',
        'category' => 'Audio',
        'brand' => 'Behringer',
        'model' => 'X32',
        'serial_number' => 'BEH-X32-9923',
        'status' => 'Maintenance',
        'location' => 'FOH Loft',
        'purchase_date' => '2026-01-15',
        'purchase_price' => 2499.00,
        'notes' => 'Updated specs description',
    ]);
    $response->assertRedirect();
    $asset->refresh();
    expect($asset->name)->toBe('Audio Console X32 Updated');
    expect($asset->status)->toBe('Maintenance');

    // 5. Can log maintenance
    $response = $this->post(route('assets.logs.store', $asset), [
        'service_type' => 'Repair',
        'status' => 'Completed',
        'performed_by' => 'Sound Tech Pro Inc',
        'cost' => 150.00,
        'service_date' => '2026-07-23',
        'notes' => 'Fixed fader slider #4 and cleaned circuit dust.',
    ]);
    $response->assertRedirect();

    $log = MaintenanceLog::where('asset_id', $asset->id)->first();
    expect($log)->not->toBeNull();
    expect($log->cost)->toEqual(150.00);

    // 6. Can schedule service reminder
    $response = $this->post(route('assets.reminders.store', $asset), [
        'title' => 'Clean Projector Fans',
        'frequency' => 'Quarterly',
        'next_due_date' => '2026-10-23',
        'notes' => 'Remove filter and clean with compressed air.',
    ]);
    $response->assertRedirect();

    $reminder = ServiceReminder::where('asset_id', $asset->id)->first();
    expect($reminder)->not->toBeNull();
    expect($reminder->frequency)->toBe('Quarterly');

    // 7. Can complete service reminder (triggering auto-log and date advance)
    $response = $this->post(route('assets.reminders.complete', [$asset, $reminder]));
    $response->assertRedirect();

    $reminder->refresh();
    // Next due date for Quarterly should advance by 3 months (from 2026-10-23 to 2026-01-23 next year, or 2027)
    // 2026-10-23 + 3 months = 2027-01-23
    expect($reminder->next_due_date->toDateString())->toBe('2027-01-23');

    // Verify a corresponding Completed MaintenanceLog was created
    $newLog = MaintenanceLog::where('service_type', 'Routine')
        ->where('performed_by', 'Automated Reminder Complete')
        ->first();
    expect($newLog)->not->toBeNull();
});

test('assets and logs are isolated between church workspaces', function () {
    $userA = User::factory()->create();
    $churchA = Church::create(['name' => 'Church Workspace A']);
    $churchA->users()->attach($userA->id, ['role' => 'Admin', 'modules' => ['assets']]);
    $userA->update(['current_church_id' => $churchA->id]);

    $userB = User::factory()->create();
    $churchB = Church::create(['name' => 'Church Workspace B']);
    $churchB->users()->attach($userB->id, ['role' => 'Admin', 'modules' => ['assets']]);
    $userB->update(['current_church_id' => $churchB->id]);

    $assetA = Asset::create([
        'church_id' => $churchA->id,
        'name' => 'Church A Projector',
        'category' => 'Video',
        'status' => 'Active',
        'created_by' => $userA->id,
    ]);

    // User B tries to view Asset A details
    $this->actingAs($userB);
    $response = $this->get(route('assets.show', $assetA));
    $response->assertStatus(403);

    // User B tries to update Asset A details
    $response = $this->put(route('assets.update', $assetA), [
        'name' => 'Hijacked Name',
        'category' => 'Video',
        'status' => 'Active',
    ]);
    $response->assertStatus(403);
});

test('read-only User role cannot mutate assets, logs, or reminders', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Calvary']);
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['assets'],
    ]);
    $user->update(['current_church_id' => $church->id]);

    $asset = Asset::create([
        'church_id' => $church->id,
        'name' => 'Calvary Console',
        'category' => 'Audio',
        'status' => 'Active',
        'created_by' => $user->id,
    ]);

    $reminder = ServiceReminder::create([
        'church_id' => $church->id,
        'asset_id' => $asset->id,
        'title' => 'Check cable connections',
        'frequency' => 'One-time',
        'next_due_date' => '2026-08-01',
        'status' => 'Active',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    // 1. Try store asset
    $response = $this->post(route('assets.store'), [
        'name' => 'Unauthorized Asset',
        'category' => 'IT',
        'status' => 'Active',
    ]);
    $response->assertStatus(403);

    // 2. Try update asset
    $response = $this->put(route('assets.update', $asset), [
        'name' => 'Mod',
        'category' => 'Audio',
        'status' => 'Active',
    ]);
    $response->assertStatus(403);

    // 3. Try delete asset
    $response = $this->delete(route('assets.destroy', $asset));
    $response->assertStatus(403);

    // 4. Try store maintenance log
    $response = $this->post(route('assets.logs.store', $asset), [
        'service_type' => 'Routine',
        'status' => 'Completed',
        'cost' => 0.00,
        'service_date' => '2026-07-23',
    ]);
    $response->assertStatus(403);

    // 5. Try store reminder
    $response = $this->post(route('assets.reminders.store', $asset), [
        'title' => 'Unauthorized Reminder',
        'frequency' => 'Monthly',
        'next_due_date' => '2026-08-23',
    ]);
    $response->assertStatus(403);

    // 6. Try complete reminder
    $response = $this->post(route('assets.reminders.complete', [$asset, $reminder]));
    $response->assertStatus(403);
});

test('privileged users can migrate shopping list items to assets', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Calvary']);
    $church->users()->attach($user->id, [
        'role' => 'Manager',
        'modules' => ['shopping_lists', 'assets'],
    ]);
    $user->update(['current_church_id' => $church->id]);

    $list = ShoppingList::create([
        'church_id' => $church->id,
        'name' => 'Tech Upgrades Q3',
        'created_by' => $user->id,
    ]);

    $item = ShoppingListItem::create([
        'shopping_list_id' => $list->id,
        'name' => 'SM58 Microphone',
        'unit_price' => 99.00,
        'quantity' => 2,
        'comments' => 'Vocal mics',
    ]);

    $this->actingAs($user);

    // Can migrate
    $response = $this->post(route('shopping-lists.items.migrate', [$list, $item]), [
        'category' => 'Audio',
        'status' => 'In Storage',
        'location' => 'Mic Cabinet',
    ]);

    // Check redirection and review filter IDs parameters
    $assets = Asset::where('category', 'Audio')->get();
    expect($assets)->toHaveCount(2);
    expect($assets[0]->name)->toBe('SM58 Microphone (1)');
    expect($assets[1]->name)->toBe('SM58 Microphone (2)');
    expect($assets[0]->purchase_price)->toEqual(99.00);

    $idsString = $assets->pluck('id')->implode(',');
    $response->assertRedirect(route('assets.index', ['review_ids' => $idsString]));

    // Check review filter works in assets index query
    $response = $this->get(route('assets.index', ['review_ids' => $idsString]));
    $response->assertOk();
    $response->assertSee('SM58 Microphone (1)');
});

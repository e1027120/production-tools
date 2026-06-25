<?php

use App\Models\Church;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users without shopping_lists module access are blocked', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);

    // User role with NO shopping_lists module
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['racks'],
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->get(route('shopping-lists.index'));
    $response->assertStatus(403);
});

test('users with shopping_lists module can manage shopping lists and items', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);

    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['shopping_lists'],
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    // 1. Can view index page
    $response = $this->get(route('shopping-lists.index'));
    $response->assertOk();

    // 2. Can create shopping list
    $response = $this->post(route('shopping-lists.store'), [
        'name' => 'Stage Mic Upgrade',
        'description' => 'Upgrade vocal mics to Shure Beta 58A',
    ]);

    $list = ShoppingList::where('name', 'Stage Mic Upgrade')->first();
    expect($list)->not->toBeNull();
    $response->assertRedirect(route('shopping-lists.show', $list));

    // 3. Can load editor detail page
    $response = $this->get(route('shopping-lists.show', $list));
    $response->assertOk();

    // 4. Can update list metadata (name/description)
    $response = $this->put(route('shopping-lists.update', $list), [
        'name' => 'Stage Mic Upgrade v2',
        'description' => 'Vocal mic upgrade list',
    ]);
    $response->assertRedirect();
    expect($list->refresh()->name)->toBe('Stage Mic Upgrade v2');

    // 5. Can add item to list
    $response = $this->post(route('shopping-lists.items.add', $list), [
        'name' => 'Shure Beta 58A',
        'unit_price' => 169.00,
        'quantity' => 4,
        'link' => 'https://sweetwater.com/store/detail/Beta58',
        'comments' => 'Buy from Sweetwater with bundle discount',
    ]);
    $response->assertRedirect();

    $item = ShoppingListItem::where('name', 'Shure Beta 58A')->first();
    expect($item)->not->toBeNull();
    expect($item->total_price)->toBe(676.00);

    // 6. Can update item in list
    $response = $this->put(route('shopping-lists.items.update', [$list, $item]), [
        'name' => 'Shure Beta 58A Wireless',
        'unit_price' => 399.00,
        'quantity' => 2,
        'link' => 'https://sweetwater.com/store/detail/Beta58',
        'comments' => 'Vocal mic upgrade list updated comments',
    ]);
    $response->assertRedirect();
    expect($item->refresh()->name)->toBe('Shure Beta 58A Wireless');
    expect($item->total_price)->toBe(798.00);

    // 7. Can delete item from list
    $response = $this->delete(route('shopping-lists.items.remove', [$list, $item]));
    $response->assertRedirect();
    expect(ShoppingListItem::find($item->id))->toBeNull();

    // 8. Can delete list
    $response = $this->delete(route('shopping-lists.destroy', $list));
    $response->assertRedirect(route('shopping-lists.index'));
    expect(ShoppingList::find($list->id))->toBeNull();
});

test('shopping lists are isolated between church workspaces', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $churchA = Church::create(['name' => 'Church A']);
    $churchB = Church::create(['name' => 'Church B']);

    $churchA->users()->attach($userA->id, ['role' => 'Admin']);
    $churchB->users()->attach($userB->id, ['role' => 'Admin']);

    $userA->update(['current_church_id' => $churchA->id]);
    $userB->update(['current_church_id' => $churchB->id]);

    $listA = ShoppingList::create([
        'church_id' => $churchA->id,
        'name' => 'Church A List',
        'created_by' => $userA->id,
    ]);

    // User B tries to view Church A shopping list
    $this->actingAs($userB);
    $response = $this->get(route('shopping-lists.show', $listA));
    $response->assertStatus(403);
});

test('shopping lists can be shared externally via token', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'Grace Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);

    $list = ShoppingList::create([
        'church_id' => $church->id,
        'name' => 'Public Share Upgrade List',
        'created_by' => $user->id,
    ]);

    // Add item to list
    $item = ShoppingListItem::create([
        'shopping_list_id' => $list->id,
        'name' => 'LED Par Lights',
        'unit_price' => 120.00,
        'quantity' => 6,
    ]);

    $this->actingAs($user);

    // 1. Initial share token is null
    expect($list->share_token)->toBeNull();

    // 2. Toggle share to generate token
    $response = $this->post(route('shopping-lists.toggle-share', $list));
    $response->assertRedirect();
    $list->refresh();
    expect($list->share_token)->not->toBeNull();

    // 3. Access shared list publicly without auth
    auth()->logout();

    $response = $this->get(route('shopping-lists.shared.view', $list->share_token));
    $response->assertOk();
    $response->assertSee('Public Share Upgrade List');
    $response->assertSee('LED Par Lights');
    $response->assertSee('720'); // Total price format or total budget calculation

    // 4. Can add/remove guest emails
    $this->actingAs($user);
    $response = $this->post(route('shopping-lists.share-email', $list), [
        'email' => 'pastor@church.org',
    ]);
    $response->assertRedirect();
    expect($list->refresh()->shared_emails)->toContain('pastor@church.org');

    $response = $this->post(route('shopping-lists.remove-shared-email', $list), [
        'email' => 'pastor@church.org',
    ]);
    $response->assertRedirect();
    expect($list->refresh()->shared_emails)->not->toContain('pastor@church.org');
});

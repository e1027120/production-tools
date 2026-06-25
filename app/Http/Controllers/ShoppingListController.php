<?php

namespace App\Http\Controllers;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingListController extends Controller
{
    /**
     * Display shopping lists.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        $lists = ShoppingList::where('church_id', $user->current_church_id)
            ->with(['creator', 'items'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'share_token' => $list->share_token,
                    'shared_emails' => $list->shared_emails ?? [],
                    'created_by' => $list->creator->name,
                    'created_at' => $list->created_at?->toIso8601String(),
                    'items_count' => $list->items->count(),
                    'total_price' => $list->items->sum('total_price'),
                ];
            });

        return Inertia::render('shopping/Index', [
            'lists' => $lists,
        ]);
    }

    /**
     * Store a newly created shopping list.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $list = ShoppingList::create([
            'church_id' => $user->current_church_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->route('shopping-lists.show', $list);
    }

    /**
     * Display a specific shopping list editor.
     */
    public function show(Request $request, ShoppingList $shoppingList): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $shoppingList->load(['items', 'creator']);

        return Inertia::render('shopping/Show', [
            'list' => [
                'id' => $shoppingList->id,
                'name' => $shoppingList->name,
                'description' => $shoppingList->description,
                'share_token' => $shoppingList->share_token,
                'shared_emails' => $shoppingList->shared_emails ?? [],
                'created_by' => $shoppingList->creator->name,
                'created_at' => $shoppingList->created_at?->toIso8601String(),
                'items' => $shoppingList->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'unit_price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'link' => $item->link,
                        'comments' => $item->comments,
                        'total_price' => $item->total_price,
                    ];
                }),
                'total_price' => $shoppingList->items->sum('total_price'),
            ],
        ]);
    }

    /**
     * Update shopping list details (name/description).
     */
    public function update(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $shoppingList->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->back();
    }

    /**
     * Delete shopping list.
     */
    public function destroy(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $shoppingList->delete();

        return redirect()->route('shopping-lists.index');
    }

    /**
     * Add an item to the shopping list.
     */
    public function addItem(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'link' => ['nullable', 'url', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $shoppingList->items()->create($validated);

        return redirect()->back();
    }

    /**
     * Update an item in the shopping list.
     */
    public function updateItem(Request $request, ShoppingList $shoppingList, ShoppingListItem $item): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id || $item->shopping_list_id !== $shoppingList->id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'link' => ['nullable', 'url', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $item->update($validated);

        return redirect()->back();
    }

    /**
     * Remove an item from the shopping list.
     */
    public function removeItem(Request $request, ShoppingList $shoppingList, ShoppingListItem $item): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id || $item->shopping_list_id !== $shoppingList->id) {
            abort(403, 'Unauthorized.');
        }

        $item->delete();

        return redirect()->back();
    }

    /**
     * Toggle public sharing on the shopping list.
     */
    public function toggleShare(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        if ($shoppingList->share_token) {
            $shoppingList->update([
                'share_token' => null,
            ]);
        } else {
            $shoppingList->update([
                'share_token' => Str::random(32),
            ]);
        }

        return redirect()->back();
    }

    /**
     * Share list with email.
     */
    public function shareEmail(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $emails = $shoppingList->shared_emails ?? [];
        if (! in_array($validated['email'], $emails)) {
            $emails[] = $validated['email'];
            $shoppingList->update([
                'shared_emails' => $emails,
            ]);
        }

        return redirect()->back();
    }

    /**
     * Remove shared email from list.
     */
    public function removeSharedEmail(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('shopping_lists')) {
            abort(403, 'You do not have access to this module.');
        }

        if ($shoppingList->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $emails = $shoppingList->shared_emails ?? [];
        if (($key = array_search($validated['email'], $emails)) !== false) {
            unset($emails[$key]);
            $shoppingList->update([
                'shared_emails' => array_values($emails),
            ]);
        }

        return redirect()->back();
    }

    /**
     * Display shared shopping list publicly.
     */
    public function viewShared(string $token): Response
    {
        $list = ShoppingList::where('share_token', $token)
            ->with(['items', 'church'])
            ->firstOrFail();

        return Inertia::render('shopping/Shared', [
            'list' => [
                'name' => $list->name,
                'description' => $list->description,
                'church_name' => $list->church->name,
                'items' => $list->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'unit_price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'link' => $item->link,
                        'comments' => $item->comments,
                        'total_price' => $item->total_price,
                    ];
                }),
                'total_price' => $list->items->sum('total_price'),
            ],
        ]);
    }
}

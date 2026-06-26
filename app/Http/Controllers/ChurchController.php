<?php

namespace App\Http\Controllers;

use App\Mail\MemberInvitationMail;
use App\Models\Church;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChurchController extends Controller
{
    /**
     * Switch the user's active church context.
     */
    public function switch(Request $request, Church $church): RedirectResponse
    {
        $user = $request->user();

        // Check if user belongs to this church
        if (! $user->churches()->where('churches.id', $church->id)->exists()) {
            abort(403, 'You do not belong to this church.');
        }

        $user->update([
            'current_church_id' => $church->id,
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Display church settings and membership list.
     */
    public function settings(Request $request): Response
    {
        $user = $request->user();
        $church = $user->currentChurch;

        if (! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        $members = $church->users()
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                    'modules' => $member->pivot->modules ?: [],
                ];
            });

        return Inertia::render('churches/Settings', [
            'church' => $church,
            'members' => $members,
            'userRole' => $user->currentChurchMember()?->role,
        ]);
    }

    /**
     * Update church details. Only Admins.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $church = $user->currentChurch;

        if (! $user->hasChurchRole('Admin')) {
            abort(403, 'Only Admins can change church details.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $church->update($validated);

        return redirect()->back();
    }

    /**
     * Add/invite a user to the active church.
     */
    public function addUser(Request $request): RedirectResponse
    {
        $user = $request->user();
        $church = $user->currentChurch;

        if (! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:Admin,Manager,User'],
            'modules' => ['nullable', 'array'],
        ]);

        // 1. Check if user already exists on the platform
        $targetUser = User::where('email', $validated['email'])->first();

        if ($targetUser) {
            // Check if user is already in this church
            if ($church->users()->where('users.id', $targetUser->id)->exists()) {
                return back()->withErrors([
                    'email' => 'This user is already a member of this church.',
                ]);
            }

            // Directly attach existing user
            $church->users()->attach($targetUser->id, [
                'role' => $validated['role'],
                'modules' => $validated['modules'] ?? [],
            ]);

            return redirect()->back();
        }

        // 2. If user does NOT exist, generate and send an invitation
        $token = Str::random(40);

        // Save or update existing invitation for this email
        MemberInvitation::updateOrCreate(
            ['email' => $validated['email']],
            [
                'church_id' => $church->id,
                'role' => $validated['role'],
                'modules' => $validated['modules'] ?? [],
                'token' => $token,
            ]
        );

        // Build invitation URL
        $invitationHash = config('auth.invitation_hash');
        $url = route('register', [
            'invitation' => $invitationHash,
            'invite_token' => $token,
            'email' => $validated['email'],
        ]);

        // Send invitation email
        Mail::to($validated['email'])->send(
            new MemberInvitationMail($url, $church->name)
        );

        return redirect()->back();
    }

    /**
     * Update member role and module permissions.
     */
    public function updateUser(Request $request, User $member): RedirectResponse
    {
        $user = $request->user();
        $church = $user->currentChurch;

        if (! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        // Prevent updating yourself
        if ($user->id === $member->id) {
            abort(403, 'You cannot modify your own role.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:Admin,Manager,User'],
            'modules' => ['nullable', 'array'],
        ]);

        $church->users()->updateExistingPivot($member->id, [
            'role' => $validated['role'],
            'modules' => $validated['modules'] ?? [],
        ]);

        return redirect()->back();
    }

    /**
     * Remove a member from the active church.
     */
    public function removeUser(Request $request, User $member): RedirectResponse
    {
        $user = $request->user();
        $church = $user->currentChurch;

        if (! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        // Prevent removing yourself
        if ($user->id === $member->id) {
            abort(403, 'You cannot remove yourself from the church.');
        }

        $church->users()->detach($member->id);

        // If the removed user's active church was this one, clear it
        if ($member->current_church_id === $church->id) {
            $member->update([
                'current_church_id' => null,
            ]);
        }

        return redirect()->back();
    }

    /**
     * Store a newly created church. Only Admins.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasChurchRole('Admin')) {
            abort(403, 'Only Admins can create new churches.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $church = Church::create($validated);

            // Attach user as Admin with default modules
            $church->users()->attach($user->id, [
                'role' => 'Admin',
                'modules' => ['racks', 'trainings', 'diagrams', 'shopping_lists', 'cables'],
            ]);

            // Set as active church context
            $user->update([
                'current_church_id' => $church->id,
            ]);
        });

        return redirect()->route('dashboard');
    }
}

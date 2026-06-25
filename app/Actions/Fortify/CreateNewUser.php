<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $configuredHash = config('auth.invitation_hash');
                    if (empty($configuredHash) || $value !== $configuredHash) {
                        $fail('The provided invitation code/link is invalid or expired.');
                    }
                },
            ],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $church = Church::create([
                'name' => $user->name."'s Church",
                'description' => 'Default production workspace for '.$user->name.'.',
            ]);

            $church->users()->attach($user->id, [
                'role' => 'Admin',
                'modules' => ['racks', 'trainings', 'diagrams', 'shopping_lists', 'cables'],
            ]);

            $user->update([
                'current_church_id' => $church->id,
            ]);

            return $user;
        });
    }
}

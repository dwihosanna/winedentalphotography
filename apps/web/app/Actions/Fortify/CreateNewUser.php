<?php

namespace App\Actions\Fortify;

use App\Actions\CreateDentistWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/** Registrasi dokter dengan email (FR-27). Akun baru = Free; email harus diverifikasi sebelum membuat pasien. */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private CreateDentistWorkspace $createWorkspace) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'practice_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'dentist_declaration' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'dentist_declaration.accepted' => 'Centang pernyataan bahwa Anda dokter gigi berizin.',
            'terms.accepted' => 'Setujui syarat layanan untuk mendaftar.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'auth_provider' => 'password',
            ]);
            ($this->createWorkspace)($user, $input['practice_name'], request());

            return $user;
        });
    }
}

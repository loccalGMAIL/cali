<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

it('crea los usuarios administradores con sus credenciales', function () {
    $this->seed(UserSeeder::class);

    $claudio = User::where('email', 'loccal@gmail.com')->firstOrFail();
    $carlos = User::where('email', 'carloscasaretto@hotmail.com')->firstOrFail();

    expect($claudio->name)->toBe('Claudio')
        ->and($claudio->role)->toBe(UserRole::Admin)
        ->and($claudio->active)->toBeTrue()
        ->and(Hash::check('Claudio123', $claudio->password))->toBeTrue()
        ->and($carlos->name)->toBe('Carlos Casaretto')
        ->and($carlos->role)->toBe(UserRole::Admin)
        ->and(Hash::check('Carlos#123', $carlos->password))->toBeTrue();
});

it('no duplica usuarios si se ejecuta de nuevo', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::whereIn('email', ['loccal@gmail.com', 'carloscasaretto@hotmail.com'])->count())->toBe(2);
});

it('el seeder principal también crea estos usuarios', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'loccal@gmail.com')->exists())->toBeTrue()
        ->and(User::where('email', 'carloscasaretto@hotmail.com')->exists())->toBeTrue();
});

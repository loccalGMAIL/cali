<?php

use App\Enums\UserRole;
use App\Models\User;

it('distingue admin de vendedor', function () {
    expect(User::factory()->admin()->make()->isAdmin())->toBeTrue()
        ->and(User::factory()->seller()->make()->isSeller())->toBeTrue();
});

it('lista solo vendedores activos en el scope sellers', function () {
    User::factory()->admin()->create();
    User::factory()->seller()->inactive()->create();
    $seller = User::factory()->seller()->create();

    expect(User::sellers()->pluck('id')->all())->toBe([$seller->id])
        ->and($seller->role)->toBe(UserRole::Seller);
});

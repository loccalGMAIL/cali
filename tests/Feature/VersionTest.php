<?php

use App\Models\User;

it('la versión es semántica', function () {
    expect(config('cali.version'))->toMatch('/^\d+\.\d+\.\d+$/');
});

it('el changelog documenta la versión vigente', function () {
    $changelog = file_get_contents(base_path('CHANGELOG.md'));
    $version = config('cali.version');

    expect($changelog)->toContain("## [{$version}] - ")
        ->and($changelog)->toContain('## [Sin publicar]')
        ->and($changelog)->toContain("[{$version}]: ");
});

it('la última versión del changelog es la vigente', function () {
    preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', file_get_contents(base_path('CHANGELOG.md')), $matches);

    expect($matches[1][0])->toBe(config('cali.version'));
});

it('muestra la versión en el login y en el panel', function () {
    $version = '>v'.config('cali.version').'<';

    $this->get('http://'.config('domains.panel').'/login')->assertOk()->assertSee($version, false)
        ->assertSeeInOrder(['cali-version-login'], false);

    $this->actingAs(User::factory()->admin()->create())
        ->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertSee($version, false)
        ->assertSee('cali-version-navbar', false);
});

it('muestra la versión una sola vez en el login', function () {
    $html = $this->get('http://'.config('domains.panel').'/login')->assertOk()->getContent();

    expect(substr_count($html, '>v'.config('cali.version').'<'))->toBe(1);
});

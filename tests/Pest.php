<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Relaticle\Ink\Tests\Fixtures\Models\TokenUser;
use Relaticle\Ink\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Seed a default test user the package expects via author_id. */
function testUser(): TokenUser
{
    /** @var TokenUser $user */
    $user = TokenUser::query()->create([
        'name' => 'Test Author',
        'email' => 'author@example.test',
    ]);

    return $user;
}

/**
 * A caller carrying a Sanctum token with the given abilities.
 *
 * Sanctum grants session-authenticated users a TransientToken whose can() is always
 * true, so '*' models that case; a narrower list models a scoped API token.
 */
function tokenUser(array $abilities = ['*']): TokenUser
{
    $user = testUser();

    $user->withAccessToken(new PersonalAccessToken(['abilities' => $abilities]));

    return $user;
}

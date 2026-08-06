<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Orchestra\Testbench\Factories\UserFactory;
use Relaticle\Ink\Ink;

afterEach(fn () => Ink::flushState());

test('it returns the caller when the caller is the configured author model', function () {
    $user = testUser();

    expect(Ink::resolveAuthor($user)?->getKey())->toBe($user->getKey());
});

test('it returns null when the caller is not the configured author model', function () {
    config()->set('ink.author_model', stdClass::class);

    expect(Ink::resolveAuthor(testUser()))->toBeNull();
});

test('it returns null when there is no caller', function () {
    expect(Ink::resolveAuthor(null))->toBeNull();
});

test('a host hook overrides the default resolution', function () {
    $designated = testUser();

    Ink::resolveAuthorUsing(fn (): User => $designated);

    $other = (new UserFactory)->create(['email' => 'other@example.test']);

    expect(Ink::resolveAuthor($other)?->getKey())->toBe($designated->getKey());
});

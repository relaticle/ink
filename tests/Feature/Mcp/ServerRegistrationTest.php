<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;

function bootInkWithMcp(bool $enabled): void
{
    config()->set('ink.features.mcp', $enabled);

    $app = Container::getInstance();
    $app->register(InkServiceProvider::class, force: true);
    $app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
}

function registeredUris(): array
{
    return collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();
}

test('the MCP route is not registered by default', function () {
    bootInkWithMcp(false);

    expect(registeredUris())->not->toContain('mcp/blog');
});

test('the MCP route is registered when the feature is enabled', function () {
    bootInkWithMcp(true);

    expect(registeredUris())->toContain('mcp/blog');
});

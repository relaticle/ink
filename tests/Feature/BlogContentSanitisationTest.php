<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Post;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
});

$payload = "## Intro\n\nSafe copy.\n\n<script>window.pwned=true</script>\n<img src=x onerror=\"window.pwned=true\">";

it('strips raw HTML from post content on the public show page', function () use ($payload) {
    Post::factory()->published()->create([
        'title' => 'Sanitised post',
        'slug' => 'sanitised-post',
        'content' => $payload,
    ]);

    $html = $this->get(route('blog.show', 'sanitised-post'))->assertOk()->getContent();

    expect($html)->not->toContain('<script>window.pwned')
        ->and($html)->not->toContain('onerror=')
        ->and($html)->toContain('Safe copy.');
});

it('strips raw HTML from post content on the signed preview page', function () use ($payload) {
    $post = Post::factory()->create([
        'title' => 'Sanitised draft',
        'slug' => 'sanitised-draft',
        'content' => $payload,
    ]);

    $html = $this->get(URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $post]))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('<script>window.pwned')
        ->and($html)->not->toContain('onerror=')
        ->and($html)->toContain('Safe copy.');
});

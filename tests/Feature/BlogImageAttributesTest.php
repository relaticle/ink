<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Support\ImageAttributes;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
});

test('the public show page marks markdown images lazy and async', function () {
    Post::factory()->published()->create([
        'title' => 'Lazy images',
        'slug' => 'lazy-images',
        'content' => "Intro.\n\n![First](https://example.test/one.png)\n\nMiddle.\n\n![Second](https://example.test/two.png)",
    ]);

    $html = $this->get(route('blog.show', 'lazy-images'))->assertOk()->getContent();

    expect(substr_count($html, '<img loading="lazy" decoding="async" '))->toBe(2)
        ->and($html)->toContain('src="https://example.test/one.png"')
        ->and($html)->toContain('src="https://example.test/two.png"');
});

test('the signed preview page marks markdown images lazy and async', function () {
    $post = Post::factory()->create([
        'title' => 'Lazy draft',
        'slug' => 'lazy-draft',
        'content' => "Intro.\n\n![Only](https://example.test/draft.png)",
    ]);

    $html = $this->get(URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $post]))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, '<img loading="lazy" decoding="async" '))->toBe(1)
        ->and($html)->toContain('src="https://example.test/draft.png"');
});

test('an author-declared loading attribute survives untouched', function () {
    $post = Post::factory()->create([
        'content' => '<img loading="eager" src="https://example.test/hero.png" alt="Hero">',
    ]);

    expect($post->toHtml())
        ->toContain('<img decoding="async" loading="eager" src="https://example.test/hero.png" alt="Hero">')
        ->and(substr_count($post->toHtml(), 'loading='))->toBe(1)
        ->and(substr_count($post->toHtml(), 'decoding='))->toBe(1);
});

test('an author-declared decoding attribute survives untouched', function () {
    $post = Post::factory()->create([
        'content' => '<img decoding="sync" src="https://example.test/hero.png" alt="Hero">',
    ]);

    expect($post->toHtml())
        ->toContain('<img loading="lazy" decoding="sync" src="https://example.test/hero.png" alt="Hero">')
        ->and(substr_count($post->toHtml(), 'decoding='))->toBe(1)
        ->and(substr_count($post->toHtml(), 'loading='))->toBe(1);
});

test('an image already carrying both attributes is left alone', function () {
    $tag = '<img loading="eager" decoding="sync" src="https://example.test/hero.png" alt="Hero">';

    $post = Post::factory()->create(['content' => $tag]);

    expect($post->toHtml())->toContain($tag)
        ->and(substr_count($post->toHtml(), '<img'))->toBe(1)
        ->and(substr_count($post->toHtml(), 'loading='))->toBe(1)
        ->and(substr_count($post->toHtml(), 'decoding='))->toBe(1);
});

test('an image whose attributes span multiple lines is still marked', function () {
    $post = Post::factory()->create([
        'content' => "<img\n  src=\"https://example.test/multi.png\"\n  alt=\"Multi\">",
    ]);

    expect($post->toHtml())
        ->toContain("<img loading=\"lazy\" decoding=\"async\"\nsrc=\"https://example.test/multi.png\"\nalt=\"Multi\">");
});

test('an alt text mentioning loading does not suppress the attributes', function () {
    $post = Post::factory()->create([
        'content' => '<img src="https://example.test/x.png" alt="loading=fast, decoding=none">',
    ]);

    expect($post->toHtml())
        ->toContain('<img loading="lazy" decoding="async" src="https://example.test/x.png" alt="loading=fast, decoding=none">');
});

// CommonMark rejects a raw-HTML tag whose *unquoted* attribute value contains `=` and
// escapes the whole thing as text, so this shape cannot reach markLazy() through post
// markdown today. It is asserted on the helper directly because markLazy() post-processes
// whatever HTML the configured renderer produced, and nothing pins that to CommonMark.
test('an unquoted value mentioning loading does not suppress the attributes', function () {
    $tag = '<img src=https://example.test/x.png?loading=true alt="Test">';

    expect(ImageAttributes::markLazy($tag))
        ->toBe('<img loading="lazy" decoding="async" src=https://example.test/x.png?loading=true alt="Test">');
});

test('a data-loading attribute does not suppress the attributes', function () {
    $post = Post::factory()->create([
        'content' => '<img data-loading="1" src="https://example.test/x.png" alt="Test">',
    ]);

    expect($post->toHtml())
        ->toContain('<img loading="lazy" decoding="async" data-loading="1" src="https://example.test/x.png" alt="Test">');
});

test('an unquoted declared attribute still suppresses injection and survives untouched', function () {
    $post = Post::factory()->create([
        'content' => '<img loading=eager src=https://example.test/hero.png alt="Hero">',
    ]);

    expect($post->toHtml())
        ->toContain('<img decoding="async" loading=eager src=https://example.test/hero.png alt="Hero">')
        ->and(substr_count($post->toHtml(), 'loading='))->toBe(1)
        ->and(substr_count($post->toHtml(), 'decoding='))->toBe(1);
});

test('the post body component marks markdown images lazy and async', function () {
    $post = Post::factory()->create([
        'content' => '![Only](https://example.test/component.png)',
    ]);

    $html = (string) $this->blade('<x-ink::post-body :post="$post" />', ['post' => $post]);

    expect($html)->toContain('<img loading="lazy" decoding="async" src="https://example.test/component.png"');
});

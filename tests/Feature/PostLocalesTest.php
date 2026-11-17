<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Relaticle\Ink\BlogSitemapGenerator;
use Relaticle\Ink\Ink;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Livewire\BlogSearch;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;
use Spatie\Sitemap\Sitemap;

function bootBlogIn(array $locales): void
{
    config()->set('ink.locales', $locales);
    config()->set('ink.features.public_routes', true);
    config()->set('ink.features.feed', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    app()->register(InkServiceProvider::class, force: true);
    app()->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
    Ink::flushState();
}

function translatedPair(array $english = [], array $german = []): array
{
    $en = Post::factory()->published()->create(['slug' => 'hello', 'title' => 'Hello', ...$english]);
    $de = Post::factory()->published()->create([
        'slug' => 'hello',
        'title' => 'Hallo',
        'locale' => 'de',
        'translation_group' => $en->ensureTranslationGroup(),
        ...$german,
    ]);

    return [$en, $de];
}

test('serves a German post at /de/blog/{slug} and its English sibling at /blog/{slug} with the same slug', function () {
    bootBlogIn(['en', 'de']);
    translatedPair(['content' => 'English body'], ['content' => 'Deutscher Text']);

    $this->get('/blog/hello')->assertOk()->assertSee('English body')->assertDontSee('Deutscher Text');
    $this->get('/de/blog/hello')->assertOk()->assertSee('Deutscher Text')->assertDontSee('English body');
});

test('answers 404 for /de/blog/{slug} when only the English post exists', function () {
    bootBlogIn(['en', 'de']);
    Post::factory()->published()->create(['slug' => 'only-english']);

    $this->get('/blog/only-english')->assertOk();
    $this->get('/de/blog/only-english')->assertNotFound();
});

test('lists only posts in the page locale on the index, category, tag and feed', function () {
    bootBlogIn(['en', 'de']);
    config()->set('ink.features.tags', true);
    $category = Category::factory()->create();
    translatedPair(['category_id' => $category->id], ['category_id' => $category->id]);

    foreach (['/blog' => ['Hello', 'Hallo'], '/de/blog' => ['Hallo', 'Hello']] as $path => [$seen, $hidden]) {
        $this->get($path)->assertSee($seen)->assertDontSee($hidden);
        $this->get("{$path}/category/{$category->slug}")->assertSee($seen)->assertDontSee($hidden);
        $this->get("{$path}/feed")->assertSee("<title>{$seen}</title>", false)->assertDontSee("<title>{$hidden}</title>", false);
    }

    [$en, $de] = [Post::where('locale', 'en')->first(), Post::where('locale', 'de')->first()];
    $tag = Tag::factory()->create();
    $en->tags()->attach($tag);
    $de->tags()->attach($tag);

    $this->get("/de/blog/tag/{$tag->slug}")->assertSee('Hallo')->assertDontSee('Hello');
    $this->get("/blog/tag/{$tag->slug}")->assertSee('Hello')->assertDontSee('Hallo');
});

test('emits hreflang links for every published translation plus x-default', function () {
    bootBlogIn(['en', 'de', 'fr']);
    [$en] = translatedPair();
    Post::factory()->scheduled()->create(['slug' => 'hello', 'locale' => 'fr', 'translation_group' => $en->translation_group]);

    $this->get('/de/blog/hello')
        ->assertSee('hreflang="en" href="'.url('/blog/hello').'"', false)
        ->assertSee('hreflang="de" href="'.url('/de/blog/hello').'"', false)
        ->assertSee('hreflang="x-default" href="'.url('/blog/hello').'"', false)
        ->assertDontSee('hreflang="fr"', false);
});

test('emits no hreflang for a post without translations', function () {
    bootBlogIn(['en', 'de']);
    Post::factory()->published()->create(['slug' => 'solo']);

    $this->get('/blog/solo')->assertOk()->assertDontSee('hreflang', false);
});

test('adds xhtml:link alternates to the sitemap', function () {
    bootBlogIn(['en', 'de']);
    translatedPair();

    $urls = collect(BlogSitemapGenerator::addToSitemap(Sitemap::create())->getTags())->keyBy('url');

    expect($urls)->toHaveKeys([url('/blog'), url('/de/blog'), url('/blog/hello'), url('/de/blog/hello')]);

    $alternates = collect($urls[url('/de/blog/hello')]->alternates)->mapWithKeys(fn ($alt) => [$alt->locale => $alt->url]);

    expect($alternates->sortKeys()->all())->toBe([
        'de' => url('/de/blog/hello'),
        'en' => url('/blog/hello'),
        'x-default' => url('/blog/hello'),
    ]);
});

test('leaves a locale out of the sitemap until it has a published post', function () {
    bootBlogIn(['en', 'de']);
    Post::factory()->published()->create();

    $urls = collect(BlogSitemapGenerator::addToSitemap(Sitemap::create())->getTags())->map->url;

    expect($urls)->toContain(url('/blog'))->not->toContain(url('/de/blog'));
});

test('sets <language> in a locale feed', function () {
    bootBlogIn(['en', 'de']);
    translatedPair();

    $this->get('/de/blog/feed')
        ->assertSee('<language>de</language>', false)
        ->assertSee(url('/de/blog/hello'), false)
        ->assertSee('href="'.url('/de/blog/feed').'"', false);
    $this->get('/blog/feed')->assertSee('<language>en</language>', false);
});

test('keeps a single-language blog on the original routes with no prefixed ones', function () {
    bootBlogIn(['en']);

    expect(Route::has('blog.index'))->toBeTrue()
        ->and(Route::has('blog.en.index'))->toBeFalse()
        ->and(collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn ($name) => preg_match('/^blog\.[a-z]{2}\./', $name)))->toBeEmpty();

    $post = Post::factory()->published()->create(['slug' => 'plain']);

    expect($post->locale)->toBe('en')
        ->and($post->getUrl())->toBe(route('blog.show', 'plain'))
        ->and($post->alternateUrls())->toBe([]);
});

test('registers the prefixed routes under blog.{locale}.*', function () {
    bootBlogIn(['en', 'de']);

    expect(route('blog.index'))->toBe(url('/blog'))
        ->and(route('blog.de.index'))->toBe(url('/de/blog'))
        ->and(route('blog.de.show', 'x'))->toBe(url('/de/blog/x'))
        ->and(route('blog.de.category', 'x'))->toBe(url('/de/blog/category/x'))
        ->and(route('blog.de.tag', 'x'))->toBe(url('/de/blog/tag/x'))
        ->and(route('blog.de.feed'))->toBe(url('/de/blog/feed'));
});

test('a post links to the URL of its own locale and related posts stay in it', function () {
    bootBlogIn(['en', 'de']);
    $category = Category::factory()->create();
    [$en, $de] = translatedPair(['category_id' => $category->id], ['category_id' => $category->id]);
    $otherDe = Post::factory()->published()->create(['locale' => 'de', 'category_id' => $category->id]);

    expect($de->getUrl())->toBe(url('/de/blog/hello'))
        ->and($de->relatedPosts()->pluck('id')->all())->toBe([$otherDe->id])
        ->and($en->relatedPosts()->get())->toBeEmpty();
});

test('translations lists published siblings in other locales only', function () {
    bootBlogIn(['en', 'de', 'fr']);
    [$en, $de] = translatedPair();
    Post::factory()->draft()->create(['locale' => 'fr', 'translation_group' => $en->translation_group]);

    expect($en->translations()->pluck('id')->all())->toBe([$de->id])
        ->and($de->translations()->pluck('id')->all())->toBe([$en->id])
        ->and($en->siblings()->count())->toBe(2)
        ->and(Post::factory()->published()->create()->translations())->toBeEmpty();
});

test('an explicit slug already taken in the same locale is suffixed', function () {
    bootBlogIn(['en', 'de']);
    translatedPair();

    $duplicate = Post::factory()->create(['title' => 'Hallo', 'slug' => 'hello', 'locale' => 'de']);

    expect($duplicate->slug)->not->toBe('hello');
});

test('a generated slug is unique per locale', function () {
    bootBlogIn(['en', 'de']);
    $english = Post::factory()->create(['title' => 'Release notes', 'slug' => null]);
    $german = Post::factory()->create(['title' => 'Release notes', 'slug' => null, 'locale' => 'de']);
    $secondGerman = Post::factory()->create(['title' => 'Release notes', 'slug' => null, 'locale' => 'de']);

    expect($english->slug)->toBe('release-notes')
        ->and($german->slug)->toBe('release-notes')
        ->and($secondGerman->slug)->toBe('release-notes-1');
});

test('a trashed post only gives up its slug to a live post in the same locale', function () {
    bootBlogIn(['en', 'de']);
    $english = Post::factory()->create(['title' => 'Release notes', 'slug' => null]);
    $english->delete();

    $german = Post::factory()->create(['title' => 'Release notes', 'slug' => null, 'locale' => 'de']);
    expect(Post::withTrashed()->find($english->id)->slug)->toBe('release-notes')
        ->and($german->slug)->toBe('release-notes');

    $reborn = Post::factory()->create(['title' => 'Release notes', 'slug' => null]);
    expect($reborn->slug)->toBe('release-notes')
        ->and(Post::withTrashed()->find($english->id)->slug)->not->toBe('release-notes');
});

test('search stays inside the page locale', function () {
    bootBlogIn(['en', 'de']);
    Post::factory()->published()->create(['title' => 'Webhooks guide']);
    Post::factory()->published()->create(['title' => 'Webhooks Anleitung', 'locale' => 'de']);

    Livewire::test(BlogSearch::class, ['locale' => 'de', 'query' => 'Webhooks'])
        ->assertSee('Webhooks Anleitung')
        ->assertDontSee('Webhooks guide');

    Livewire::test(BlogSearch::class, ['query' => 'Webhooks'])
        ->assertSee('Webhooks guide')
        ->assertDontSee('Webhooks Anleitung');
});

test('a post gets the install default locale when none is given', function () {
    config()->set('ink.default_locale', 'de');

    expect(Post::factory()->create()->locale)->toBe('de');
});

test('the category badge links to the category page of the page locale', function () {
    bootBlogIn(['en', 'de']);
    $category = Category::factory()->create(['slug' => 'news']);

    expect(Blade::render('<x-ink::category-badge :category="$category" />', ['category' => $category]))
        ->toContain('href="'.url('/blog/category/news').'"')
        ->and(Blade::render('<x-ink::category-badge :category="$category" locale="de" />', ['category' => $category]))
        ->toContain('href="'.url('/de/blog/category/news').'"')
        ->and(Blade::render('<x-ink::category-badge :category="$category" :linked="false" />', ['category' => $category]))
        ->not->toContain('href=');
});

test('the feed component and feed link follow the locale', function () {
    bootBlogIn(['en', 'de']);
    [, $de] = translatedPair();

    $feed = Blade::render('<x-ink::feed :posts="$posts" locale="de" />', ['posts' => collect([$de])]);

    expect($feed)
        ->toContain('<language>de</language>')
        ->toContain('<link>'.url('/de/blog/hello').'</link>')
        ->toContain('href="'.url('/de/blog/feed').'"');

    request()->setRouteResolver(fn () => Route::getRoutes()->getByName('blog.de.index'));

    expect(Blade::render('<x-ink::feed-link />'))->toContain('href="'.url('/de/blog/feed').'"');
});

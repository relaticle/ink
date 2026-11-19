<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\GetPostTool;
use Relaticle\Ink\Mcp\Tools\ListPostsTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

beforeEach(function () {
    config()->set('ink.locales', ['en', 'de', 'fr']);
});

function localizedArgs(Category $category, array $extra = []): array
{
    return [
        'title' => 'Hallo',
        'content' => 'Inhalt',
        'excerpt' => 'Auszug.',
        'category_id' => $category->id,
        ...$extra,
    ];
}

test('creates a translation through MCP with translation_of', function () {
    $category = Category::factory()->create();
    $english = Post::factory()->published()->create(['slug' => 'hello']);

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, localizedArgs($category, ['locale' => 'de', 'slug' => 'hello', 'translation_of' => $english->id]))
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('locale', 'de')->where('slug', 'hello')->etc());

    $german = Post::where('locale', 'de')->firstOrFail();

    expect($german->translation_group)->not->toBeNull()
        ->and($english->fresh()->translation_group)->toBe($german->translation_group);
});

test('refuses a locale outside ink.locales', function () {
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, localizedArgs($category, ['locale' => 'it']))
        ->assertHasErrors();

    expect(Post::count())->toBe(0);
});

test('refuses a translation_of whose locale is already taken in the group', function () {
    $category = Category::factory()->create();
    $english = Post::factory()->published()->create();
    Post::factory()->published()->create(['locale' => 'de', 'translation_group' => $english->ensureTranslationGroup()]);

    $user = tokenUser();

    BlogServer::actingAs($user)
        ->tool(CreatePostTool::class, localizedArgs($category, ['locale' => 'de', 'translation_of' => $english->id]))
        ->assertHasErrors();

    BlogServer::actingAs($user)
        ->tool(CreatePostTool::class, localizedArgs($category, ['locale' => 'en', 'translation_of' => $english->id]))
        ->assertHasErrors();

    BlogServer::actingAs($user)
        ->tool(CreatePostTool::class, localizedArgs($category, ['locale' => 'fr', 'translation_of' => 999999]))
        ->assertHasErrors();
});

test('the slug rule is scoped to the locale', function () {
    $category = Category::factory()->create();
    Post::factory()->create(['slug' => 'hello']);

    $user = tokenUser();

    BlogServer::actingAs($user)
        ->tool(CreatePostTool::class, localizedArgs($category, ['slug' => 'hello']))
        ->assertHasErrors();

    BlogServer::actingAs($user)
        ->tool(CreatePostTool::class, localizedArgs($category, ['slug' => 'hello', 'locale' => 'de']))
        ->assertOk();
});

test('update-post-tool links an existing post as a translation', function () {
    $english = Post::factory()->create();
    $german = Post::factory()->create(['locale' => 'de']);

    BlogServer::actingAs(tokenUser())
        ->tool(UpdatePostTool::class, ['id' => $german->id, 'translation_of' => $english->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('locale', 'de')->etc());

    expect($german->fresh()->translation_group)->toBe($english->fresh()->translation_group)
        ->and($german->fresh()->translation_group)->not->toBeNull();
});

test('list-posts-tool filters by locale', function () {
    Post::factory()->create(['title' => 'English one']);
    Post::factory()->create(['title' => 'Deutsch eins', 'locale' => 'de']);

    BlogServer::actingAs(tokenUser())
        ->tool(ListPostsTool::class, ['locale' => 'de'])
        ->assertOk()
        ->assertSee('Deutsch eins')
        ->assertDontSee('English one');
});

test('get-post-tool returns the locale and every translation, drafts included', function () {
    $english = Post::factory()->published()->create(['slug' => 'hello']);
    $german = Post::factory()->create(['slug' => 'hello', 'locale' => 'de', 'translation_group' => $english->ensureTranslationGroup()]);

    $user = tokenUser();

    BlogServer::actingAs($user)
        ->tool(GetPostTool::class, ['id' => $english->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('locale', 'en')
            ->where('translations', [['id' => $german->id, 'locale' => 'de', 'slug' => 'hello', 'title' => $german->title, 'status' => 'draft']])
            ->etc());

    BlogServer::actingAs($user)
        ->tool(GetPostTool::class, ['slug' => 'hello', 'locale' => 'de'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('id', $german->id)->etc());

    BlogServer::actingAs($user)
        ->tool(GetPostTool::class, ['slug' => 'hello'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('id', $english->id)->etc());
});

test('update-post-tool refuses a locale change that collides with another post in the group', function () {
    $english = Post::factory()->published()->create();
    $group = $english->ensureTranslationGroup();
    Post::factory()->published()->create(['locale' => 'de', 'translation_group' => $group]);
    $french = Post::factory()->published()->create(['locale' => 'fr', 'translation_group' => $group]);

    BlogServer::actingAs(tokenUser())
        ->tool(UpdatePostTool::class, ['id' => $french->id, 'locale' => 'de'])
        ->assertHasErrors();

    expect($french->fresh()->locale)->toBe('fr');
});

test('update-post-tool allows a locale change that frees no collision', function () {
    $english = Post::factory()->published()->create();
    $french = Post::factory()->published()->create(['locale' => 'fr', 'translation_group' => $english->ensureTranslationGroup()]);

    BlogServer::actingAs(tokenUser())
        ->tool(UpdatePostTool::class, ['id' => $french->id, 'locale' => 'de'])
        ->assertOk();

    expect($french->fresh()->locale)->toBe('de');
});

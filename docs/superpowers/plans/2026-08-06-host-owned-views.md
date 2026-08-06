# Host-Owned Views Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a host render its own views through ink's controllers, so it never duplicates or publishes package files.

**Architecture:** A config map of view names replaces the hardcoded `ink::pages.*` in `BlogController`; `null` keeps today's behaviour. Two data gaps close (`relatedPosts` and an edit-URL hook on preview, `tags` eager-loaded). Route middleware becomes configurable, the preview route constrains `{post}` to digits, and the feed route registers only when its flag is on (the tag route stays registered — an existing test resolves it while the feature is off). The table-of-contents parser moves onto `Post`, next to the `toHtml()` output it reads.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 5, Orchestra Testbench 11.

## Global Constraints

- Target branch `2.x`, released as **v2.3.0**. Additive for hosts on ink's own views.
- Config holds plain strings and class-strings only — never closures. Host callables go on the `Ink` manager in a provider's `boot()`.
- `null` in `ink.views.*` must fall back to `ink::pages.*`, so an existing host upgrading changes nothing.
- Every task ends green: `vendor/bin/pest` and `vendor/bin/pint --test` both pass before committing.
- Existing route names (`blog.index`, `blog.show`, `blog.category`, `blog.tag`, `blog.preview`, `blog.feed`) do not change.

---

## File Structure

**Create:**
- `tests/Feature/HostViewsTest.php` — view resolution + data contract
- `tests/Feature/TableOfContentsTest.php` — parser, incl. the three cases the host regex got wrong
- `tests/Fixtures/views/host-*.blade.php` — minimal host views that echo what they receive

**Modify:**
- `config/ink.php` — `views` map, `middleware`
- `src/Http/Controllers/BlogController.php` — view resolution, preview payload, `tags` eager-load
- `src/Models/Post.php` — `tableOfContents()`
- `src/Ink.php` — `resolvePreviewEditUrlUsing()` / `resolvePreviewEditUrl()`
- `routes/web.php` — configurable middleware, `whereNumber` on preview, conditional feed route
- `README.md`, `UPGRADING.md`, `docs/content/2.essentials/*` — document the seams

---

### Task 1: Config keys and view resolution

**Files:**
- Modify: `config/ink.php`, `src/Http/Controllers/BlogController.php`
- Create: `tests/Feature/HostViewsTest.php`, `tests/Fixtures/views/host-index.blade.php`, `tests/Fixtures/views/host-show.blade.php`

**Interfaces:**
- Consumes: nothing
- Produces: config keys `ink.views.{index,show,category,tag,preview,feed}` (each `?string`) and `ink.middleware` (`array<string>`); `BlogController::viewFor(string $key): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/Fixtures/views/host-index.blade.php`:

```blade
HOST INDEX: {{ $posts->total() }} posts
```

Create `tests/Fixtures/views/host-show.blade.php`:

```blade
HOST SHOW: {{ $post->title }} / related={{ $relatedPosts->count() }}
```

Create `tests/Feature/HostViewsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Post;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
});

test('it renders ink pages when no host view is configured', function () {
    Post::factory()->published()->create(['title' => 'Default rendering']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertDontSee('HOST INDEX');
});

test('it renders the host view for the index when configured', function () {
    config()->set('ink.views.index', 'tests::host-index');
    Post::factory(2)->published()->create();

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSee('HOST INDEX: 2 posts');
});

test('it renders the host view for a post when configured', function () {
    config()->set('ink.views.show', 'tests::host-show');
    $post = Post::factory()->published()->create(['title' => 'Hello host', 'slug' => 'hello-host']);

    $this->get(route('blog.show', 'hello-host'))
        ->assertOk()
        ->assertSee('HOST SHOW: Hello host');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/HostViewsTest.php`
Expected: the two "host view" tests FAIL — ink renders `ink::pages.*` regardless of config.

- [ ] **Step 3: Add the config keys**

In `config/ink.php`, add two top-level blocks after `'layout'`:

```php
    /*
     * Render your own views instead of the package's. Each null falls back to
     * the matching `ink::pages.*` view.
     */
    'views' => [
        'index' => null,
        'show' => null,
        'category' => null,
        'tag' => null,
        'preview' => null,
        'feed' => null,
    ],

    'middleware' => ['web'],
```

- [ ] **Step 4: Resolve views through config**

In `src/Http/Controllers/BlogController.php`, add a private helper at the end of the class:

```php
    private function viewFor(string $key): string
    {
        $view = config("ink.views.{$key}");

        return is_string($view) && $view !== '' ? $view : "ink::pages.{$key}";
    }
```

Then replace each hardcoded view name:

- `view('ink::pages.index', [...])` → `view($this->viewFor('index'), [...])`
- `view('ink::pages.show', [...])` → `view($this->viewFor('show'), [...])`
- `view('ink::pages.category', [...])` → `view($this->viewFor('category'), [...])`
- `view('ink::pages.tag', [...])` → `view($this->viewFor('tag'), [...])`
- `view('ink::pages.preview', [...])` → `view($this->viewFor('preview'), [...])`
- `response()->view('ink::pages.feed', [...])` → `response()->view($this->viewFor('feed'), [...])`

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/HostViewsTest.php`
Expected: 3 passed.

- [ ] **Step 6: Commit**

```bash
git add config/ink.php src/Http/Controllers/BlogController.php tests/
git commit -m "feat: let a host render its own views through ink's controllers"
```

---

### Task 2: Preview payload, edit-URL hook and eager-loaded tags

**Files:**
- Modify: `src/Ink.php`, `src/Http/Controllers/BlogController.php`
- Create: `tests/Fixtures/views/host-preview.blade.php`
- Test: `tests/Feature/HostViewsTest.php` (append)

**Interfaces:**
- Consumes: `viewFor()` (Task 1)
- Produces:
  - `Ink::resolvePreviewEditUrlUsing(?Closure $callback): void`
  - `Ink::resolvePreviewEditUrl(Post $post): ?string`
  - `preview` view receives `post`, `relatedPosts`, `editUrl`
  - `show` and `preview` eager-load `tags`

- [ ] **Step 1: Write the failing test**

Create `tests/Fixtures/views/host-preview.blade.php`:

```blade
HOST PREVIEW: {{ $post->title }} / related={{ $relatedPosts->count() }} / edit={{ $editUrl ?? 'none' }}
```

Extend `tests/Fixtures/views/host-show.blade.php` to exercise the eager-loaded tags:

```blade
HOST SHOW: {{ $post->title }} / related={{ $relatedPosts->count() }} / tags={{ $post->tags->count() }}
```

Append to `tests/Feature/HostViewsTest.php`:

```php
test('the preview view receives related posts and a null edit url by default', function () {
    config()->set('ink.views.preview', 'tests::host-preview');
    $post = Post::factory()->create(['title' => 'Draft one']);

    $this->get(URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $post]))
        ->assertOk()
        ->assertSee('HOST PREVIEW: Draft one')
        ->assertSee('edit=none');
});

test('a host hook supplies the preview edit url', function () {
    config()->set('ink.views.preview', 'tests::host-preview');
    Ink::resolvePreviewEditUrlUsing(fn (Post $post): string => "https://admin.test/posts/{$post->id}/edit");

    $post = Post::factory()->create(['title' => 'Draft two']);

    $this->get(URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $post]))
        ->assertOk()
        ->assertSee("edit=https://admin.test/posts/{$post->id}/edit");
});

test('show and preview do not lazy-load tags', function () {
    config()->set('ink.views.show', 'tests::host-show');
    Model::preventLazyLoading();

    $post = Post::factory()->published()->create(['slug' => 'no-lazy']);
    $post->tags()->attach(Tag::factory()->create());

    $this->get(route('blog.show', 'no-lazy'))->assertOk();

    Model::preventLazyLoading(false);
});
```

Add these imports at the top of the file:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Relaticle\Ink\Ink;
use Relaticle\Ink\Models\Tag;
```

and reset the hook between tests by appending to the existing `beforeEach`:

```php
    Ink::flushState();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/HostViewsTest.php`
Expected: FAIL — `Ink::resolvePreviewEditUrlUsing()` does not exist, and `$relatedPosts`/`$editUrl` are undefined in the preview view.

- [ ] **Step 3: Add the hook to the Ink manager**

In `src/Ink.php`, add a second property and two methods, and extend `flushState()`:

```php
    private static ?Closure $previewEditUrlResolver = null;

    /**
     * Register how to build the "edit this post" link shown on the preview page.
     *
     * Which panel and which guard own the blog admin is a host decision — a package
     * that hardcodes it breaks the moment the host moves the resource.
     */
    public static function resolvePreviewEditUrlUsing(?Closure $callback): void
    {
        self::$previewEditUrlResolver = $callback;
    }

    public static function resolvePreviewEditUrl(Post $post): ?string
    {
        if (! self::$previewEditUrlResolver instanceof Closure) {
            return null;
        }

        $url = (self::$previewEditUrlResolver)($post);

        return is_string($url) && $url !== '' ? $url : null;
    }
```

Add `use Relaticle\Ink\Models\Post;` to the imports, and inside `flushState()` add:

```php
        self::$previewEditUrlResolver = null;
```

- [ ] **Step 4: Enrich the controller's preview and show actions**

In `src/Http/Controllers/BlogController.php`, add `use Relaticle\Ink\Ink;` to the imports.

Change `show()`'s eager-load from `->with(['category', 'author', 'seo'])` to:

```php
            ->with(['category', 'author', 'seo', 'tags'])
```

Replace the body of `preview()` with:

```php
    public function preview(Post $post): View
    {
        $post->loadMissing(['category', 'author', 'seo', 'tags']);

        $relatedPosts = $post->relatedPosts(limit: 3)->get();

        seo()->for($post);

        return view($this->viewFor('preview'), [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'editUrl' => Ink::resolvePreviewEditUrl($post),
        ]);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/HostViewsTest.php`
Expected: 6 passed.

- [ ] **Step 6: Commit**

```bash
git add src/Ink.php src/Http/Controllers/BlogController.php tests/
git commit -m "feat: give the preview view related posts and a host-supplied edit url"
```

---

### Task 3: Route middleware, numeric preview ids, conditional feed

**Files:**
- Modify: `routes/web.php`, `src/Http/Controllers/BlogController.php`
- Create: `tests/Feature/BlogRouteConfigTest.php`

**Interfaces:**
- Consumes: `ink.middleware` (Task 1)
- Produces: the blog route group uses configured middleware; `blog.preview` matches digits only; `blog.feed` exists only when `ink.features.feed` is true.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BlogRouteConfigTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;

function bootBlogRoutes(array $config = []): void
{
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }

    test()->app->register(InkServiceProvider::class, force: true);
    test()->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
}

test('a non-numeric preview segment does not reach route model binding', function () {
    bootBlogRoutes();

    // Without whereNumber this casts to bigint and throws a QueryException — a 500
    // on a public, unauthenticated route.
    $this->get('/blog/preview/not-a-post-id')->assertNotFound();
});

test('the feed route is absent when the feature is off', function () {
    bootBlogRoutes(['ink.features.feed' => false]);

    expect(Route::has('blog.feed'))->toBeFalse();
});

test('the feed route is present when the feature is on', function () {
    bootBlogRoutes(['ink.features.feed' => true]);

    expect(Route::has('blog.feed'))->toBeTrue();
});

test('configured middleware is applied to the blog routes', function () {
    bootBlogRoutes(['ink.middleware' => ['web', 'throttle:7,1']]);

    $middleware = Route::getRoutes()->getByName('blog.index')->gatherMiddleware();

    expect($middleware)->toContain('throttle:7,1');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/BlogRouteConfigTest.php`
Expected: FAIL — the preview route 500s, the feed route exists regardless of the flag, and the middleware is hardcoded.

- [ ] **Step 3: Rewrite the route file**

Replace `routes/web.php` with:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\Http\Controllers\BlogController;

$prefix = config('ink.prefix', 'ink');
$middleware = config('ink.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function (): void {
    Route::get('/', [BlogController::class, 'index'])->name('blog.index');

    // The tag route stays unconditional: TagsTest resolves route('blog.tag', ...) with the
    // feature OFF, which would throw RouteNotFoundException if the route disappeared. The
    // runtime abort_unless in tag() remains the gate.
    Route::get('/tag/{slug}', [BlogController::class, 'tag'])->name('blog.tag');

    Route::get('/category/{slug}', [BlogController::class, 'category'])->name('blog.category');

    // whereNumber matters: {post} binds by id, so a non-numeric segment would otherwise
    // reach the database and throw, 500ing a public route.
    Route::get('/preview/{post}', [BlogController::class, 'preview'])
        ->middleware('signed')
        ->whereNumber('post')
        ->name('blog.preview');

    if (config('ink.features.feed', false)) {
        Route::get('/feed', [BlogController::class, 'feed'])->name('blog.feed');
    }

    Route::get('/{slug}', [BlogController::class, 'show'])->name('blog.show');
});
```

Note the feed route must be declared **before** the catch-all `/{slug}` route, and the tag route keeps its existing position.

- [ ] **Step 4: Drop the now-redundant feed abort**

In `src/Http/Controllers/BlogController.php`, delete this line from `feed()` — a disabled feed no longer has a route to reach:

```php
        abort_unless(config('ink.features.feed', false), 404);
```

Leave `tag()`'s `abort_unless` in place. Its route is still registered unconditionally, so the runtime check is what returns the 404.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/BlogRouteConfigTest.php`
Expected: 4 passed.

- [ ] **Step 6: Confirm nothing regressed**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass. `TagsTest` must be untouched — it resolves `route('blog.tag', ...)` with the feature off, which is why that route stays registered.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php src/Http/Controllers/BlogController.php tests/
git commit -m "feat: configurable blog route middleware, numeric preview ids, gated feed route"
```

---

### Task 4: `Post::tableOfContents()`

**Files:**
- Modify: `src/Models/Post.php`
- Create: `tests/Feature/TableOfContentsTest.php`

**Interfaces:**
- Consumes: `Post::toHtml()`
- Produces: `Post::tableOfContents(string $tag = 'h2'): array<string, string>` — fragment => heading text.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TableOfContentsTest.php`. These are the exact cases a host-side regex got wrong, ported up as the regression lock:

```php
<?php

declare(strict_types=1);

use Relaticle\Ink\Models\Post;

test('it keeps the full label when a heading contains inline markup', function () {
    $post = Post::factory()->create([
        'content' => "## Why **we** built it\n\nBody.\n\n## Using `artisan` commands\n\nBody.",
    ]);

    expect(array_values($post->tableOfContents()))
        ->toBe(['Why we built it', 'Using artisan commands']);
});

test('it decodes entities exactly once', function () {
    $post = Post::factory()->create(['content' => "## Ampersands & more\n\nBody."]);

    expect(array_values($post->tableOfContents()))->toBe(['Ampersands & more']);
});

test('it keys entries by the permalink anchor id', function () {
    $post = Post::factory()->create(['content' => "## Why we built it\n\nBody."]);

    expect(array_keys($post->tableOfContents()))->toBe(['why-we-built-it']);
});

test('it returns an empty array for a post with no headings', function () {
    $post = Post::factory()->create(['content' => 'Just a paragraph.']);

    expect($post->tableOfContents())->toBe([]);
});

test('it strips the injected permalink symbol from the label', function () {
    $post = Post::factory()->create(['content' => "## Plain heading\n\nBody."]);

    expect(array_values($post->tableOfContents()))->toBe(['Plain heading']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/TableOfContentsTest.php`
Expected: FAIL — `Call to undefined method ...::tableOfContents()`.

- [ ] **Step 3: Implement it on the model**

In `src/Models/Post.php`, add these imports:

```php
use DOMDocument;
use DOMElement;
use DOMXPath;
```

and add this method directly after `toHtml()`:

```php
    /**
     * Build a fragment => heading-text map from the rendered post.
     *
     * DOM-parsed rather than regex: heading text is taken from textContent so inline
     * markup (bold, code, links) survives, and entities decode exactly once. The
     * fragment comes from the injected permalink anchor — the heading's own id is
     * slugified from its inner HTML and unusable as a target.
     *
     * @return array<string, string>
     */
    public function tableOfContents(string $tag = 'h2'): array
    {
        $html = $this->toHtml();

        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><div>'.$html.'</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toc = [];

        foreach (new DOMXPath($document)->query('//'.$tag) ?: [] as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $fragment = $this->headingFragment($heading);
            $text = $this->headingText($heading);

            if ($fragment === null || $text === '') {
                continue;
            }

            $toc[$fragment] = $text;
        }

        return $toc;
    }

    private function headingFragment(DOMElement $heading): ?string
    {
        foreach ($heading->getElementsByTagName('a') as $anchor) {
            $id = $anchor->getAttribute('id');

            if ($id !== '') {
                return $id;
            }
        }

        $ownId = $heading->getAttribute('id');

        return $ownId === '' ? null : $ownId;
    }

    private function headingText(DOMElement $heading): string
    {
        $clone = $heading->cloneNode(true);

        if (! $clone instanceof DOMElement) {
            return '';
        }

        foreach (iterator_to_array($clone->getElementsByTagName('a')) as $anchor) {
            if ($anchor->getAttribute('id') !== '') {
                $anchor->parentNode?->removeChild($anchor);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $clone->textContent) ?? '');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/TableOfContentsTest.php`
Expected: 5 passed.

If the fragment test fails, the test host is not configuring heading permalinks. Set the same
`markdown.commonmark_options.heading_permalink` block the docs use in
`TestCase::defineEnvironment()` — the parser depends on that extension being enabled, and
that dependency should be explicit in the test host rather than assumed.

- [ ] **Step 5: Confirm the whole suite is green**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add src/Models/Post.php tests/Feature/TableOfContentsTest.php
git commit -m "feat: add Post::tableOfContents()"
```

---

### Task 5: Documentation and release

**Files:**
- Modify: `README.md`, `UPGRADING.md`, `docs/content/2.essentials/`

**Interfaces:**
- Consumes: everything above
- Produces: no code.

- [ ] **Step 1: Document the seams**

Add a "Using your own views" section to the docs covering: the `views` config map with the
fallback rule, the per-action data contract table from the spec, `ink.middleware`, and
`Ink::resolvePreviewEditUrlUsing()` with the Relaticle example:

```php
Ink::resolvePreviewEditUrlUsing(fn (Post $post): ?string =>
    auth('sysadmin')->check()
        ? PostResource::getUrl('edit', ['record' => $post], panel: 'sysadmin')
        : null);
```

State plainly that publishing views is **not** the recommended route — a published file is a
frozen copy that stops receiving upstream changes — and that pointing ink at host-owned views
avoids both drift and surprise.

- [ ] **Step 2: Document `tableOfContents()`**

Add it to the docs' post-model reference with a short example building a TOC in a host view.

- [ ] **Step 3: Warn about the shallow config merge**

Add a note to the docs: `mergeConfigFrom` is shallow, so a host that has published
`config/ink.php` will not receive new keys added *inside* an existing array such as
`features` — those must be added by hand. New top-level keys merge normally.

- [ ] **Step 4: Write the upgrade notes**

Append a `## To 2.3 from 2.2` section to `UPGRADING.md`: the `views`/`middleware` config keys
are additive; `feed()` and `tag()` no longer abort at runtime because their routes are now
conditional; `preview` gains `relatedPosts` and `editUrl` in its view data.

- [ ] **Step 5: Commit**

```bash
git add docs README.md UPGRADING.md
git commit -m "docs: document host-owned views, the preview hook and tableOfContents"
```

---

## Verification

- [ ] `vendor/bin/pest` — all pass, including the 3 new files
- [ ] `vendor/bin/pint --test` — clean
- [ ] `grep -rn "ink::pages\." src/Http/Controllers/BlogController.php` — no output (all resolved through `viewFor`)
- [ ] `grep -c "abort_unless" src/Http/Controllers/BlogController.php` — exactly 1 (tag only)
- [ ] CI green on `2.x`, then tag **v2.3.0**

## Relaticle follow-up (separate branch, after v2.3.0 is tagged)

1. `composer update relaticle/ink`
2. Delete `app/Http/Controllers/BlogController.php`, `app/Http/Controllers/Blog/*` (5 files), `app/Support/Blog/TableOfContents.php`, and the blog route group in `routes/web.php`.
3. In `config/ink.php` set `features.public_routes`, `features.feed` and `features.tags` to true; add `'middleware' => ['web', ProvideMarkdownResponse::class]`; add the `views` map pointing at `blog.index`, `blog.show`, `blog.index` (category), `blog.index` (tag), `blog.preview`, `blog.feed`.
4. Register `Ink::resolvePreviewEditUrlUsing()` in `AppServiceProvider::boot()`.
5. Change `x-blog.toc` to call `$post->tableOfContents()`.
6. **The existing `tests/Feature/Public/PublicPagesTest.php` must pass unchanged** — that is the proof the rendered design is identical. Any assertion needing a change means behaviour drifted; investigate rather than edit the assertion.

# Upgrading

## To 2.3 from 2.2

### Host-owned views for public-routes mode

`BlogController`'s six actions used to render only the package's own `ink::pages.*` views.
A new `views` config key lets you point each action at a view your app already owns instead:

```php
// config/ink.php
'views' => [
    'index' => null,
    'show' => null,
    'category' => null,
    'tag' => null,
    'preview' => null,
    'feed' => null,
],
```

Every key defaults to `null`, which keeps rendering `ink::pages.*` exactly as before — this
key is purely additive and requires no action from existing installs.

See [Host-Owned Views](https://relaticle.github.io/ink/essentials/host-owned-views) for the
full per-action view-data contract.

### Route middleware is configurable

The blog route group's middleware was hardcoded to `['web']`. It now reads from a new
`middleware` config key, defaulting to the same `['web']`:

```php
// config/ink.php
'middleware' => ['web'],
```

Purely additive. Add your own middleware alongside `web` — for example a response
transformer for AI crawlers.

### Preview page: edit link and related posts

`preview()` now passes `relatedPosts` and `editUrl` alongside `post`. `editUrl` is `null`
unless you register a hook in a service provider's `boot()`:

```php
use Relaticle\Ink\Ink;
use Relaticle\Ink\Models\Post;

Ink::resolvePreviewEditUrlUsing(fn (Post $post): ?string =>
    auth('sysadmin')->check()
        ? PostResource::getUrl('edit', ['record' => $post], panel: 'sysadmin')
        : null);
```

If your published `preview` view doesn't reference these keys, nothing breaks — Blade
ignores extra view data. `show` and `preview` also now eager-load `tags` on the post, so
`$post->tags` no longer triggers a lazy-load query in either view.

### `blog.feed` route is no longer registered when the feature is off

Previously the `blog.feed` route was always registered, and `feed()` called
`abort_unless(config('ink.features.feed'), 404)` at request time. The route is now
registered only when `features.feed` is `true`; the runtime check is gone.

For most hosts this is invisible — a disabled feed still 404s. It only matters if you were
calling `route('blog.feed')` or `Route::has('blog.feed')` directly with the feature off:
`Route::has(...)` now returns `false` instead of `true`, and `route('blog.feed')` now throws
`RouteNotFoundException` instead of returning a URL that 404s when visited.

`blog.tag` is unaffected by this change — its route still registers unconditionally and
`tag()` still aborts at runtime when `features.tags` is off.

`features.feed` is now read once, at route-registration time, instead of on every
request. If you run `route:cache`, flipping the flag from `false` to `true` (or back)
requires `php artisan route:clear` (or a re-run of `route:cache`) before the change takes
effect — previously the runtime check picked it up immediately on the next request.

### `/blog/preview/{post}` rejects non-numeric ids at the router

The preview route now constrains `{post}` to `[0-9]{1,18}` (up to 18 digits — the safe
maximum for a signed 64-bit id) instead of `whereNumber()`'s unbounded `[0-9]+`. A
non-numeric segment 404s at routing instead of reaching route-model binding, which could
otherwise throw a database error on a strict-typed `id` column; the length bound closes
the same hole for an arbitrarily long digit string, which could otherwise overflow a
`bigint` column on Postgres.

## To 2.2 from 2.1

### MCP tools authorize through your Gate

The tools previously required `$user->is_admin`, which only worked where staff were rows in
your `users` table. They now call `Gate::forUser($caller)->authorize(...)`.

**Register a policy** for `Relaticle\Ink\Models\Post` and `Relaticle\Ink\Models\Category`.
With none registered the Gate denies and every tool returns `This action is unauthorized.`
If you relied on `is_admin`, a policy returning `$user->is_admin` reproduces the old
behaviour exactly.

Sanctum token abilities are unchanged and still checked, as a separate axis.

### MCP tools store markdown, not HTML

`CreatePostTool` and `UpdatePostTool` used to run `Str::markdown()` and store the rendered
HTML, while the Filament editor stored raw markdown. They now store what they are given, so
`content` means one thing regardless of write path.

The included `convert_html_post_content_to_markdown` migration converts existing HTML rows.
It prints the ids of any row it could not convert cleanly — review those by hand rather
than assuming the migration handled everything.

### Author attribution is host-controlled

`CreatePostTool` no longer assumes the caller is the author. It uses the caller when that is
already an `ink.author_model` instance; otherwise register a mapping in a service provider:

```php
Ink::resolveAuthorUsing(fn ($caller) => User::firstWhere('email', $caller->email));
```

### Custom tools extend BlogTool

Tools of your own should extend `Relaticle\Ink\Mcp\BlogTool`, not
`Laravel\Mcp\Server\Tool`, so they inherit authorization. Declare `ability()`,
`tokenAbility()`, `model()` and `run()`.

### Dropped support

Laravel 12, `spatie/laravel-sitemap` 7, `spatie/laravel-sluggable` 3, Pest 3/4 and
Testbench 10 are no longer supported. `laravel/mcp` moves to `^0.9`.

## To 2.0 from 1.x — package rename

This package was renamed from `manukminasyan/filament-blog` to `relaticle/ink` at version `2.0.0`.

### What changed

| Before | After |
|---|---|
| `manukminasyan/filament-blog` | `relaticle/ink` |
| `ManukMinasyan\FilamentBlog\` | `Relaticle\Ink\` |
| `FilamentBlogServiceProvider` | `InkServiceProvider` |
| `FilamentBlogPlugin` | `InkPlugin` |
| `config/filament-blog.php` | `config/ink.php` |
| `config('filament-blog.X')` | `config('ink.X')` |
| `<x-blog::post-card>` etc. | `<x-ink::post-card>` etc. |
| `view('blog::pages.show')` | `view('ink::pages.show')` |
| `--tag=filament-blog-config` | `--tag=ink-config` |
| `--tag=filament-blog-views` | `--tag=ink-views` |
| `--tag=filament-blog-migrations` | `--tag=ink-migrations` |
| `--tag=filament-blog-translations` | `--tag=ink-translations` |

### What did NOT change

- Database tables stay `blog_posts`, `blog_categories`, `blog_tags`, `blog_post_tag` — **no data migration required**
- Route names stay `blog.index`, `blog.show`, `blog.category`, `blog.preview`, `blog.feed`, `blog.tag`
- URL prefix default stays `/blog` (override via `config('ink.prefix')`)
- All public model methods, component APIs, MCP tool signatures

### Upgrade steps

### 1. Swap the composer dependency

```bash
composer remove manukminasyan/filament-blog
composer require relaticle/ink:^2.0
```

### 2. Update imports and references

From your project root, run:

```bash
# PHP namespaces and class names
find app -type f -name '*.php' -exec perl -i -pe '
  s|ManukMinasyan\\FilamentBlog|Relaticle\\Ink|g;
  s|FilamentBlogServiceProvider|InkServiceProvider|g;
  s|FilamentBlogPlugin|InkPlugin|g;
' {} +

# Config calls
find app config -type f \( -name '*.php' -o -name '*.blade.php' \) -exec perl -i -pe "
  s|config\('filament-blog\\.|config('ink.|g;
  s|config\(\"filament-blog\\.|config(\"ink.|g;
" {} +

# Blade components and view namespace
find resources -type f -name '*.blade.php' -exec perl -i -pe '
  s|<x-blog::|<x-ink::|g;
  s|</x-blog::|</x-ink::|g;
  s|view\(["'"'"']blog::|view("ink::|g;
' {} +
```

### 3. Republish config (optional — only if you'd published the old one)

If you'd published `config/filament-blog.php`, either:

- **Keep your edits**: `git mv config/filament-blog.php config/ink.php` (config keys are the same)
- **Start fresh**: delete `config/filament-blog.php` and run `php artisan vendor:publish --tag=ink-config`

### 4. Re-publish views (optional)

If you'd published views to `resources/views/vendor/blog/`, rename to `resources/views/vendor/ink/`:

```bash
git mv resources/views/vendor/blog resources/views/vendor/ink
```

### 5. Run tests

Your existing tests should pass without changes (route names, DB tables, model APIs all preserved).

### Need help?

Open an issue at https://github.com/relaticle/ink/issues

# Upgrading

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

# Ink

  <a href="https://packagist.org/packages/relaticle/ink"><img src="https://img.shields.io/packagist/dt/relaticle/ink.svg?style=for-the-badge" alt="Downloads"></a>
  <a href="https://laravel.com/docs/12.x"><img src="https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel 12 and 13"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php" alt="PHP 8.4"></a>
  <a href="https://packagist.org/packages/relaticle/ink"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge" alt="License"></a>
  <a href="https://github.com/relaticle/ink/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/relaticle/ink/tests.yml?branch=main&style=for-the-badge&label=tests" alt="Tests"></a>

Filament-native content publishing for blog, docs, and AI-citable articles. Ships Eloquent models, a full Filament admin, MCP tools for AI agents, SEO components, publishable Blade UI components, and an **opt-in public-routes mode** for hosts that want a working blog without writing controllers.

## Features

- **Filament Admin** — PostResource and CategoryResource with markdown editor, draft/published/scheduled UX, SEO fields, featured images, and bulk publish/unpublish/schedule actions
- **SEO Components** — Meta tags, Open Graph, Twitter Cards, RSS feed, per-page canonicals on paginated listings
- **JSON-LD Schema** — `BlogPosting` + `BreadcrumbList` on post pages, `FAQPage` and `HowTo` auto-detected from content (opt-in), `Blog` + `CollectionPage` on listings
- **Search** — Portable `Post::search()` scope (LIKE by default, override for FTS / Scout), drop-in `BlogSearch` Livewire component with `?q=` URL sync
- **14 MCP Tools** — Full CRUD for posts and categories via Model Context Protocol, plus image uploads (URL or base64, mime-sniffed) for featured images and in-content markdown, authorized through your app's Gate and Sanctum token abilities. Ships a ready-to-register `BlogServer`; requires `laravel/mcp`
- **Publishable UI Components** — Post card, header, body, related posts, category badge, preview banner — all with dark mode
- **Two install modes**
  - **Headless (default)** — define your own routes/controllers, use the Blade components
  - **Public-routes mode (opt-in)** — flip a config flag, get `/blog`, `/blog/{slug}`, `/blog/category/{slug}`, signed `/blog/preview/{post}`, and optional `/blog/feed`
- **Host-owned views** — point the `views` config map at your app's own views per action, without publishing; register `Ink::resolvePreviewEditUrlUsing()` to control the preview page's edit link
- **Tags taxonomy** (opt-in via `features.tags`) — many-to-many `blog_post_tag` table, `TagResource` admin UI, public archive at `/blog/tag/{slug}`
- **MediaLibrary integration** (opt-in via `features.media_library`) — when both the flag is on AND `spatie/laravel-medialibrary` is installed, the featured-image upload uses `SpatieMediaLibraryFileUpload` instead of the plain `FileUpload`. Falls back gracefully if MediaLibrary isn't installed.
- **Sitemap Generator** — Route-aware sitemap integration via spatie/laravel-sitemap
- **Reading-time, related-posts, and table-of-contents** helpers on the Post model

## Requirements

- PHP 8.4+
- Laravel 12+ or 13
- Filament 5.x

## Installation

```bash
composer require relaticle/ink
```

Register the plugin and run migrations:

```php
// AppPanelProvider.php
->plugin(\Relaticle\Ink\InkPlugin::make())
```

```bash
php artisan migrate
```

## Public-routes mode (opt-in)

By default this package is fully headless: no routes, no controllers, no forced views. Your app owns all rendering.

To get a working blog at `/blog` without writing any controllers, flip the feature flag:

```php
// config/ink.php
'features' => [
    'public_routes' => true,   // /blog, /blog/{slug}, /blog/category/{slug}, /blog/preview/{post}
    'feed'          => true,   // adds /blog/feed (RSS 2.0)
    'tags'          => true,   // /blog/tag/{slug}, TagResource in admin
    'media_library' => true,   // SpatieMediaLibraryFileUpload (requires spatie/laravel-medialibrary)
],

'layout' => 'layouts.app',     // your host layout to extend
```

Routes register at the service-provider level — no Filament panel boot is required, so the public site keeps working for guests who never touch the admin.

Render your own views instead of the shipped ones by pointing the `views` config map at them — no publishing required:

```php
// config/ink.php
'views' => [
    'show' => 'blog.show',
    'preview' => 'blog.preview',
],
```

See [Host-Owned Views](https://relaticle.github.io/ink/essentials/host-owned-views) for the full data contract per action and for wiring the preview "edit this post" link.

## Images

The `upload-image` MCP tool accepts either a fetchable `url` or base64-encoded `data`,
mime-sniffs the actual bytes (`jpeg`/`png`/`gif`/`webp` only, no `svg`), and returns a path
you pass as `featured_image` on `create-post-tool` / `update-post-tool`, plus a markdown
snippet for in-content images. See [MCP Tools](https://relaticle.github.io/ink/essentials/mcp-tools#images)
for the full contract, including the SSRF stance on the `url` fetch.

::caution
**base64 uploads are bound by PHP's `post_max_size`, not just `ink.uploads.max_bytes`.**
base64 inflates the binary size by ~4/3, and PHP rejects an oversized request body
*before Laravel ever boots* — the client gets a raw PHP warning in an HTTP 200 instead of a
clean JSON-RPC error, and no app-level config can catch it. `ink.uploads.max_bytes` defaults
to 3MB (≈4MB base64-encoded) to stay safely under a common 5-8M `post_max_size` floor;
raise `post_max_size` (and any reverse-proxy body-size limit) to comfortably exceed
`max_bytes * 4/3` if you need a higher cap. The `url` path has no such ceiling — the image
is fetched by this server's own HTTP client, not carried in the MCP request body at all —
so prefer it for anything larger than a few MB.
::

Rendered post images automatically get `loading="lazy"` and `decoding="async"` on both
render paths (`Post::toHtml()` behind `<x-ink::post-body>`, and `Post::toSafeHtml()` behind
the shipped page views); an attribute the author declared by hand is left untouched. There
is no automatic resizing, format conversion, or `srcset`
generation — an oversized source image is served at its original dimensions and file size,
which still costs bandwidth and LCP even with lazy loading. This is a known limitation, not
a bug: keep source images reasonably sized (a few hundred KB, not multiple MB) before
uploading until a resizing pipeline ships.

## Documentation

**[Read the full documentation →](https://relaticle.github.io/ink/)**

- [Installation](https://relaticle.github.io/ink/getting-started/installation)
- [Frontend Setup](https://relaticle.github.io/ink/getting-started/frontend-setup)
- [Blade Components](https://relaticle.github.io/ink/essentials/blade-components)
- [Filament Admin](https://relaticle.github.io/ink/essentials/filament-admin)
- [MCP Tools](https://relaticle.github.io/ink/essentials/mcp-tools)
- [Configuration](https://relaticle.github.io/ink/essentials/configuration)
- [Host-Owned Views](https://relaticle.github.io/ink/essentials/host-owned-views)

## Quick Example (headless)

```blade
{{-- In your blog show page --}}
<x-your-layout>
    @push('head')
        <x-ink::meta-tags :post="$post" />
        <x-ink::feed-link />
    @endpush

    <x-ink::structured-data :post="$post" />
    <x-ink::post-header :post="$post" />
    <x-ink::post-body :post="$post" />
    <x-ink::related-posts :post="$post" />
</x-your-layout>
```

## License

MIT

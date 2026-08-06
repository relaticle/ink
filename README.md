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
- **13 MCP Tools** — Full CRUD for posts and categories via Model Context Protocol, authorized through your app's Gate and Sanctum token abilities. Ships a ready-to-register `BlogServer`; requires `laravel/mcp`
- **Publishable UI Components** — Post card, header, body, related posts, category badge, preview banner — all with dark mode
- **Two install modes**
  - **Headless (default)** — define your own routes/controllers, use the Blade components
  - **Public-routes mode (opt-in)** — flip a config flag, get `/blog`, `/blog/{slug}`, `/blog/category/{slug}`, signed `/blog/preview/{post}`, and optional `/blog/feed`
- **Tags taxonomy** (opt-in via `features.tags`) — many-to-many `blog_post_tag` table, `TagResource` admin UI, public archive at `/blog/tag/{slug}`
- **MediaLibrary integration** (opt-in via `features.media_library`) — when both the flag is on AND `spatie/laravel-medialibrary` is installed, the featured-image upload uses `SpatieMediaLibraryFileUpload` instead of the plain `FileUpload`. Falls back gracefully if MediaLibrary isn't installed.
- **Sitemap Generator** — Route-aware sitemap integration via spatie/laravel-sitemap
- **Reading-time + related-posts** helpers on the Post model

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

Publish the views if you want to customize them:

```bash
php artisan vendor:publish --tag=ink-views
```

## Documentation

**[Read the full documentation →](https://relaticle.github.io/ink/)**

- [Installation](https://relaticle.github.io/ink/getting-started/installation)
- [Frontend Setup](https://relaticle.github.io/ink/getting-started/frontend-setup)
- [Blade Components](https://relaticle.github.io/ink/essentials/blade-components)
- [Filament Admin](https://relaticle.github.io/ink/essentials/filament-admin)
- [MCP Tools](https://relaticle.github.io/ink/essentials/mcp-tools)
- [Configuration](https://relaticle.github.io/ink/essentials/configuration)

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

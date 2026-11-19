---
title: "Installation"
description: "Install Ink in your Laravel application."
canonical_url: "https://relaticle.github.io/getting-started/installation"
---
# Installation

> Install Ink in your Laravel application.

## Requirements

- **PHP:** 8.4+
- **Laravel:** 12+
- **Filament:** 5.x
- **Database:** PostgreSQL, MySQL, SQLite

## Quick Setup

<steps>

### Install Package

```bash [Terminal]
composer require relaticle/ink
```

<alert type="info">

For private repositories, add the VCS repository to your `composer.json` first:

```json [composer.json]
"repositories": [
    {"type": "vcs", "url": "git@github.com:relaticle/ink.git"}
]
```

</alert>

### Run Migrations

```bash [Terminal]
php artisan migrate
```

This creates `blog_posts`, `blog_categories`, `blog_tags`, and `blog_post_tag` tables. Tag tables are empty unless you enable the `tags` feature.

### Register Filament Plugin

```php [AppPanelProvider.php]
use Relaticle\Ink\InkPlugin;

$panel->plugins([
    InkPlugin::make(),
]);
```

### Publish Config (optional)

```bash [Terminal]
php artisan vendor:publish --tag=ink-config
```

</steps>

**Done!** Visit your Filament panel — you'll see the Blog section with Posts and Categories.

## Pick your install mode

The package ships **two install modes**:

<table>
<thead>
  <tr>
    <th>
      Mode
    </th>
    
    <th>
      When to use
    </th>
    
    <th>
      Doc
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <strong>
        Headless (default)
      </strong>
    </td>
    
    <td>
      You want the Blade components and admin, but full control over routing/views
    </td>
    
    <td>
      <a href="https://relaticle.github.io/getting-started/frontend-setup">
        Frontend Setup (headless)
      </a>
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Public-routes mode (opt-in)
      </strong>
    </td>
    
    <td>
      You want a working blog at <code>
        /blog
      </code>
      
       without writing a single controller
    </td>
    
    <td>
      <a href="https://relaticle.github.io/getting-started/public-routes-mode">
        Public-routes mode
      </a>
    </td>
  </tr>
</tbody>
</table>

Most teams porting from the Tapix/FilaForms internal blog packages want public-routes mode — it ships the same flow they had, just behind a flag.

## Default config

After publishing, `config/ink.php` looks like this. Everything is opt-in — defaults match the headless mode, so the package is a no-op until you flip a flag:

```php [config/ink.php]
return [
    'prefix' => 'blog',
    'layout' => 'layouts.app',
    'author_model' => \App\Models\User::class,
    'per_page' => 12,

    'features' => [
        'public_routes' => false,  // /blog, /blog/{slug}, /blog/category/{slug}, signed /blog/preview/{post}
        'feed'          => false,  // /blog/feed (RSS 2.0)
        'sitemap'       => false,  // helper for spatie/laravel-sitemap
        'tags'          => false,  // /blog/tag/{slug}, TagResource admin
        'media_library' => false,  // SpatieMediaLibraryFileUpload (requires spatie/laravel-medialibrary)
    ],

    'feed' => [
        'title' => null,         // falls back to config('app.name')
        'description' => null,
        'author_email' => null,  // RSS <author> email
    ],

    'publisher' => [
        'name' => null,    // Organization name (JSON-LD)
        'url' => null,
        'logo' => null,    // Path to logo (used with asset())
    ],

    'tables' => [
        'posts' => 'blog_posts',
        'categories' => 'blog_categories',
    ],
];
```

See [Configuration](https://relaticle.github.io/essentials/configuration) for the full reference.

## Morph Map

If your app enforces morph maps, register the blog models:

```php [AppServiceProvider.php]
Relation::enforceMorphMap([
    // ...existing entries
    'blog_post' => \Relaticle\Ink\Models\Post::class,
    'blog_category' => \Relaticle\Ink\Models\Category::class,
    'blog_tag' => \Relaticle\Ink\Models\Tag::class,
]);
```

## SEO Migration

The package depends on `ralphjsmit/laravel-seo`. Publish its migration:

```bash [Terminal]
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

## Upgrading from the Tapix/FilaForms internal blog packages

The schema is identical (`blog_posts` and `blog_categories` table names match). To swap:

```bash [Terminal]
composer remove tapix/blog        # or filaforms/blog
composer require relaticle/ink
```

Then enable public-routes mode in config:

```php
'features' => [
    'public_routes' => true,
    'feed' => true,
],
```

No data migration needed.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

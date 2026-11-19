---
title: "MediaLibrary Integration"
description: "Opt-in featured-image upload via spatie/laravel-medialibrary."
canonical_url: "https://relaticle.github.io/essentials/media-library"
---
# MediaLibrary Integration

> Opt-in featured-image upload via spatie/laravel-medialibrary.

The package ships an **opt-in** integration with [`spatie/laravel-medialibrary`](https://spatie.be/docs/laravel-medialibrary). When enabled, the featured-image upload uses `SpatieMediaLibraryFileUpload` instead of the default `FileUpload`.

<alert type="info">

**MediaLibrary is not a hard dependency.** If you don't install it, the form gracefully falls back to the plain `FileUpload` — no crash. Flipping `features.media_library` without installing MediaLibrary is a no-op.

</alert>

## Enable

1. Install the package:```bash [Terminal]
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```
2. Flip the flag:```php [config/ink.php]
'features' => [
    'media_library' => true,
],
```
3. Use the form as usual — the featured-image field now writes to the MediaLibrary `media` table on a `featured_image` collection.

## Form-side only (for now)

This integration covers the **admin form-component swap only**. The model-side integration (implementing `HasMedia`, registering collections via `registerMediaCollections()`, migrating existing `featured_image` string column data) is intentionally deferred — that requires careful schema-migration design that doesn't fit a feature-flag layer.

If you want the full integration today, override the `Post` model in your app:

```php [app/Models/BlogPost.php]
namespace App\Models;

use Relaticle\Ink\Models\Post as BasePost;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;

class BlogPost extends BasePost implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')->singleFile();
    }
}
```

Then point the package at your model:

```php [config/ink.php]
// (post_model is not currently a config key — track this in
// Phase 3 follow-up; for now the package always uses its own Post)
```

<alert type="warning">

Swapping the post model isn't a config option in v1.4 — that lands in Phase 3 along with the model-side integration. Watch the [GitHub releases](https://github.com/relaticle/ink/releases) for `v1.5`.

</alert>

## Migrating existing `featured_image` data

When Phase 3 lands, existing posts with a `featured_image` string path will be migrated to MediaLibrary entries via a console command. Until then, two options:

1. **Stay on FileUpload** — leave the flag off. No migration required.
2. **Manual migration** — write your own script to copy `featured_image` files into MediaLibrary on a `featured_image` collection.

Both Tapix and FilaForms blogs (which this package replaces) use the same string-column approach, so flipping the flag without migration leaves you in a usable state — new posts go to MediaLibrary, old posts keep using the string path. The shipped views render whichever exists.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

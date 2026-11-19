---
title: "Search"
description: "Portable search across blog posts with a config-driven backend hook."
canonical_url: "https://relaticle.github.io/essentials/search"
---
# Search

> Portable search across blog posts with a config-driven backend hook.

The package ships a `Post::search($term)` query scope and a `BlogSearch` Livewire component.

## Default behavior

`Post::search($term)` does a `LIKE` search across `title`, `excerpt`, and `content`. Works on every database driver out of the box.

```php
$posts = Post::query()
    ->published()
    ->search($request->query('q', ''))
    ->latest('published_at')
    ->paginate(12);
```

Empty / whitespace-only terms are no-ops — the scope leaves the query unchanged. LIKE wildcards in the term are escaped.

## Override the backend

Set `search.callback` in `config/ink.php` to use Postgres FTS, MySQL FULLTEXT, Scout, Meilisearch, or anything else:

```php
'search' => [
    'callback' => function (\Illuminate\Database\Eloquent\Builder $query, string $term): void {
        $query->whereRaw(
            "to_tsvector('english', title || ' ' || excerpt || ' ' || content) @@ plainto_tsquery('english', ?)",
            [$term]
        );
    },
],
```

Whatever the callback applies becomes the search strategy. Empty terms still short-circuit before the callback fires.

## Livewire component

Use `<livewire:blog::search />` anywhere. It reads `?q=` from the URL, debounces 400ms, and renders up to 20 matches with an empty state.

To restyle, publish the view:

```bash
php artisan vendor:publish --tag=ink-views
```

Then edit `resources/views/vendor/ink/livewire/blog-search.blade.php`.

## SEO

When the shipped `/blog` route handles `?q=`, the package emits `<meta name="robots" content="noindex,follow">` on the search result page. Search queries should not be indexed as separate URLs.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

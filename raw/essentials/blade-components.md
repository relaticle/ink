---
title: "Blade Components"
description: "All available Blade components for building your blog frontend."
canonical_url: "https://relaticle.github.io/essentials/blade-components"
---
# Blade Components

> All available Blade components for building your blog frontend.

All components are publishable. Run `php artisan vendor:publish --tag=ink-views` to customize.

## SEO Components

### Meta Tags

Renders Open Graph, Twitter Card, and article meta tags in `<head>`.

```blade
@push('head')
    <x-ink::meta-tags :post="$post" />
@endpush
```

**Renders:** `og:title`, `og:description`, `og:type`, `og:image`, `article:published_time`, `article:author`, `article:section`, `twitter:card`, `twitter:title`, `twitter:image`, `canonical`

### Structured Data

Renders JSON-LD `BlogPosting` schema with publisher and breadcrumb data.

```blade
<x-ink::structured-data :post="$post" />
```

**Renders:** `@type: BlogPosting` with headline, description, datePublished, dateModified, author, publisher (from config), articleSection, articleBody, and image.

### Feed Link

Renders RSS `<link>` tag in `<head>`. Only renders if `blog.feed` route exists and feed is enabled in config.

```blade
@push('head')
    <x-ink::feed-link />
@endpush
```

### Feed

Renders full RSS 2.0 XML. Use in your feed route view:

```blade
{{-- resources/views/blog/feed.blade.php --}}
<x-ink::feed :posts="$posts" />
```

## UI Components

### Post Card

Displays a post in a listing. Shows category badge, date, title, excerpt, and featured image thumbnail.

```blade
<x-ink::post-card :post="$post" />
```

### Post Header

Renders category badge, title, author name, published date, and estimated read time.

```blade
<x-ink::post-header :post="$post" />
```

### Post Body

Renders featured image and markdown content in a Tailwind prose container with dark mode support.

```blade
<x-ink::post-body :post="$post" />
```

### Related Posts

Shows related posts section. Only renders if the collection is not empty.

```blade
<x-ink::related-posts :posts="$relatedPosts" />
```

### Category Badge

Displays a category pill. Links to category page if `blog.category` route exists.

```blade
<x-ink::category-badge :category="$post->category" />
<x-ink::category-badge :category="$post->category" :linked="false" />
```

### Preview Banner

Sticky amber banner for draft previews. Pushes `noindex` meta tag.

```blade
<x-ink::preview-banner :post="$post" :editUrl="$editUrl" />
```

## Pagination

The package ships a numbered, aria-labeled pagination view used by the index/category/tag pages. To use it in your own views:

```blade
{{ $posts->links('ink::pagination.blog') }}
```

The view emits `aria-label="Blog pagination"` on the nav, `aria-current="page"` on the active page, and `wire:navigate` on every link.

## Search (Livewire)

Drop `<livewire:blog::search />` anywhere. It reads `?q=` from the URL, debounces 400ms, and renders up to 20 matching posts. Empty queries show no results until the user types.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

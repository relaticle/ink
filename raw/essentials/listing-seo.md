---
title: "Listing SEO"
description: "Per-page canonicals, titles, and metadata for listing routes."
canonical_url: "https://relaticle.github.io/essentials/listing-seo"
---
# Listing SEO

> Per-page canonicals, titles, and metadata for listing routes.

The package's listing routes (`/blog`, `/blog/category/{slug}`, `/blog/tag/{slug}`) emit per-page canonical URLs, titles, and metadata via `BlogListingSeo`.

## Behavior

<table>
<thead>
  <tr>
    <th>
      Route
    </th>
    
    <th>
      Page 1 canonical
    </th>
    
    <th>
      Page N canonical
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        /blog
      </code>
    </td>
    
    <td>
      <code>
        /blog
      </code>
    </td>
    
    <td>
      <code>
        /blog?page=N
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        /blog?q=term
      </code>
    </td>
    
    <td>
      <code>
        /blog
      </code>
    </td>
    
    <td>
      <code>
        noindex,follow
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        /blog/category/guides
      </code>
    </td>
    
    <td>
      <code>
        /blog/category/guides
      </code>
    </td>
    
    <td>
      <code>
        /blog/category/guides?page=N
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        /blog/tag/filament
      </code>
    </td>
    
    <td>
      <code>
        /blog/tag/filament
      </code>
    </td>
    
    <td>
      <code>
        /blog/tag/filament?page=N
      </code>
    </td>
  </tr>
</tbody>
</table>

Titles include `— Page N` from page 2 onward.

## Headless consumers

If you write your own controllers in headless mode, call the helper directly:

```php
use Relaticle\Ink\Support\BlogListingSeo;

public function index(Request $request)
{
    $page = (int) $request->query('page', 1);
    seo()->for(BlogListingSeo::forIndex(page: $page, searchQuery: $request->query('q')));

    return view('blog.index', [...]);
}
```

Available factories:

- `BlogListingSeo::forIndex(int $page = 1, ?string $searchQuery = null): SEOData`
- `BlogListingSeo::forCategory(Category $category, int $page = 1): SEOData`
- `BlogListingSeo::forTag(Tag $tag, int $page = 1): SEOData`

## Pagination

The package ships a numbered, aria-labeled pagination view at `ink::pagination.blog`. Listing pages use it by default. Publish to customize:

```bash
php artisan vendor:publish --tag=ink-views
```


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

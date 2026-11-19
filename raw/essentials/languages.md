---
title: "Languages"
description: "Publish the same post in several languages with per-language URLs, hreflang and feeds."
canonical_url: "https://relaticle.github.io/essentials/languages"
---
# Languages

> Publish the same post in several languages with per-language URLs, hreflang and feeds.

Every post has a `locale` and an optional `translation_group`. A blog that never sets
`ink.locales` stays a single-language blog and behaves exactly as before.

## Configure

```php [config/ink.php]
'locales' => ['en', 'de', 'fr'],
'default_locale' => 'en',
```

The default locale keeps the unprefixed routes. Each other locale gets its own set:

<table>
<thead>
  <tr>
    <th>
      Route
    </th>
    
    <th>
      Default locale
    </th>
    
    <th>
      Other locales
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      Index
    </td>
    
    <td>
      <code>
        /blog
      </code>
      
       (<code>
        blog.index
      </code>
      
      )
    </td>
    
    <td>
      <code>
        /de/blog
      </code>
      
       (<code>
        blog.de.index
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      Post
    </td>
    
    <td>
      <code>
        /blog/{slug}
      </code>
      
       (<code>
        blog.show
      </code>
      
      )
    </td>
    
    <td>
      <code>
        /de/blog/{slug}
      </code>
      
       (<code>
        blog.de.show
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      Category
    </td>
    
    <td>
      <code>
        /blog/category/{slug}
      </code>
      
       (<code>
        blog.category
      </code>
      
      )
    </td>
    
    <td>
      <code>
        /de/blog/category/{slug}
      </code>
      
       (<code>
        blog.de.category
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      Tag
    </td>
    
    <td>
      <code>
        /blog/tag/{slug}
      </code>
      
       (<code>
        blog.tag
      </code>
      
      )
    </td>
    
    <td>
      <code>
        /de/blog/tag/{slug}
      </code>
      
       (<code>
        blog.de.tag
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      Feed
    </td>
    
    <td>
      <code>
        /blog/feed
      </code>
      
       (<code>
        blog.feed
      </code>
      
      )
    </td>
    
    <td>
      <code>
        /de/blog/feed
      </code>
      
       (<code>
        blog.de.feed
      </code>
      
      )
    </td>
  </tr>
</tbody>
</table>

The preview route stays unprefixed. A page only lists posts in its own language, and a post that
has no version in a language answers 404 at that language's URL. Slugs are unique per language, so a
German post and its English original can share one.

Ink does not set the application locale for you. If your layout needs it, set it from the route
(`request()->route()->defaults['locale']`, absent on the default locale's routes).

## Translations

Posts that translate each other share a `translation_group`. Link them in the panel with the
"Translation of" field, or over MCP with `translation_of` (the id of the post being translated).
A group holds at most one post per language.

```php
$post->translations();   // published versions in other languages
$post->siblings();       // query builder: same group, any status
$post->alternateUrls();  // ['en' => ..., 'de' => ..., 'x-default' => ...]
$post->getUrl();         // the URL in the post's own language
Post::query()->inLocale('de');
```

A published post with at least one published translation emits `hreflang` alternates (through
`seo()->for($post)` and `<x-ink::meta-tags>`), including `x-default` for the default locale's
version. The sitemap lists every language's pages with `xhtml:link` alternates. A language's
sitemap entries appear once it has a published post, and each feed sets `<language>` to its locale.

`<livewire:blog::search />` and related posts stay inside one language. Pass `locale="de"` to the
search component to override the language it takes from the route.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

---
title: "Schema"
description: "Automatic JSON-LD schema emission for posts and listings."
canonical_url: "https://relaticle.github.io/essentials/schema"
---
# Schema

> Automatic JSON-LD schema emission for posts and listings.

The package emits JSON-LD schema automatically for blog content. Each schema type can be toggled in `config/ink.php`.

## What's emitted by default

<table>
<thead>
  <tr>
    <th>
      Schema
    </th>
    
    <th>
      Where
    </th>
    
    <th>
      Toggle
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        BlogPosting
      </code>
    </td>
    
    <td>
      Post pages
    </td>
    
    <td>
      Always on (via <code>
        Post::getDynamicSEOData()
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        BreadcrumbList
      </code>
    </td>
    
    <td>
      Post pages
    </td>
    
    <td>
      Always on
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        FAQPage
      </code>
    </td>
    
    <td>
      Post pages with <code>
        ## FAQ
      </code>
      
       section
    </td>
    
    <td>
      <code>
        schema.faq_auto
      </code>
      
       (default <code>
        true
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        HowTo
      </code>
    </td>
    
    <td>
      Post pages with <code>
        ## Steps
      </code>
      
       section
    </td>
    
    <td>
      <code>
        schema.howto_auto
      </code>
      
       (default <code>
        false
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        Blog
      </code>
    </td>
    
    <td>
      <code>
        /blog
      </code>
      
       index
    </td>
    
    <td>
      Always when public-routes mode is on
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        CollectionPage
      </code>
    </td>
    
    <td>
      <code>
        /blog
      </code>
      
      , <code>
        /blog/category/{slug}
      </code>
      
      , <code>
        /blog/tag/{slug}
      </code>
    </td>
    
    <td>
      Always when public-routes mode is on
    </td>
  </tr>
</tbody>
</table>

## FAQPage conventions

To trigger FAQPage emission, include a `## FAQ` heading followed by `### Question?` and a paragraph answer:

```markdown
## FAQ

### Does the package auto-emit schema?
Yes — FAQ schema is on by default.

### Can I turn it off?
Set `schema.faq_auto` to `false`.
```

## HowTo conventions

To trigger HowTo emission, opt in via `schema.howto_auto = true` and write a `## Steps` section with `### Step Name` headings:

```markdown
## Steps

### Install the package
Run `composer require relaticle/ink`.

### Publish the config
Run `php artisan vendor:publish --tag=ink-config`.
```

Each `### heading` becomes a `HowToStep` with auto-incremented `position`.

## Disabling auto-detection

```php
'schema' => [
    'faq_auto' => false,
    'howto_auto' => false,
],
```

The `BlogPosting` + `BreadcrumbList` schemas remain on regardless — they're considered baseline for any blog.

## Validate your schema

After publishing, paste a post URL into [Google Rich Results Test](https://search.google.com/test/rich-results) or the [Schema.org Validator](https://validator.schema.org/) to confirm zero errors.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

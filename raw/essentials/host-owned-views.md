# Host-Owned Views

> Render ink's controllers through views your app already owns, and control the preview edit link.

Public-routes mode ships a `BlogController` with all six public actions. By default they
render the package's own `ink::pages.*` views. Point each action at a view your app already
owns instead — same controller, same SEO and query behavior, your markup.

## The `views` config map

```php [config/ink.php]
'views' => [
    'index' => null,
    'show' => null,
    'category' => null,
    'tag' => null,
    'preview' => null,
    'feed' => null,
],
```

Every key defaults to `null`. A `null` or empty-string value falls back to the matching
`ink::pages.*` view, so an existing install that never touches this key renders exactly as
before. Set a key to any view name your app can resolve — a Blade view, a package view, a
namespaced view from another provider:

```php [config/ink.php]
'views' => [
    'index' => 'blog.index',
    'show' => 'blog.show',
    'preview' => 'blog.preview',
],
```

Resolution happens once per request, through a private `viewFor()` helper on
`BlogController` — every action calls it instead of hardcoding `ink::pages.*`.

## What each view receives

<table>
<thead>
  <tr>
    <th>
      Action
    </th>
    
    <th>
      View data
    </th>
    
    <th>
      Notes
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        index
      </code>
    </td>
    
    <td>
      <code>
        posts
      </code>
    </td>
    
    <td>
      —
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        show
      </code>
    </td>
    
    <td>
      <code>
        post
      </code>
      
      , <code>
        relatedPosts
      </code>
    </td>
    
    <td>
      <code>
        post->tags
      </code>
      
       is eager-loaded
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        category
      </code>
    </td>
    
    <td>
      <code>
        category
      </code>
      
      , <code>
        posts
      </code>
    </td>
    
    <td>
      —
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        tag
      </code>
    </td>
    
    <td>
      <code>
        tag
      </code>
      
      , <code>
        posts
      </code>
    </td>
    
    <td>
      —
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        preview
      </code>
    </td>
    
    <td>
      <code>
        post
      </code>
      
      , <code>
        relatedPosts
      </code>
      
      , <code>
        editUrl
      </code>
    </td>
    
    <td>
      <code>
        post->tags
      </code>
      
       is eager-loaded; <code>
        editUrl
      </code>
      
       is <code>
        null
      </code>
      
       unless a host registers <a href="#preview-edit-link">
        <code>
          Ink::resolvePreviewEditUrlUsing()
        </code>
      </a>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        feed
      </code>
    </td>
    
    <td>
      <code>
        posts
      </code>
    </td>
    
    <td>
      —
    </td>
  </tr>
</tbody>
</table>

`relatedPosts` and `editUrl` on `preview` are new — earlier versions passed only `post`.

<caution>

Publishing views (`php artisan vendor:publish --tag=ink-views`) is not the recommended way
to customize the six page views above. Laravel resolves `resources/views/vendor/ink/**`
ahead of `ink::**` automatically, so publishing *works* — but the file it creates is a
frozen copy of package markup from the day you published it. It will not receive upstream
fixes, and nothing will tell you when it's drifted. Pointing `views` at your own view avoids
both problems: you own the markup from the start, and ink's own pages keep improving
underneath you without needing a merge.

Publishing is still how you customize the [Blade components](/essentials/blade-components)
(`post-card`, `post-header`, …) — those aren't covered by the `views` map.

</caution>

## Preview edit link

`Ink::resolvePreviewEditUrlUsing()` registers how to build the "edit this post" link shown
on the preview page:

```php
public static function resolvePreviewEditUrlUsing(?Closure $callback): void
```

With nothing registered, `editUrl` is always `null` and no edit link renders. Register a
callback in a service provider's `boot()` — never in config, since a closure in a config
file breaks `config:cache`:

```php
use Relaticle\Ink\Ink;
use Relaticle\Ink\Models\Post;

Ink::resolvePreviewEditUrlUsing(fn (Post $post): ?string =>
    auth('sysadmin')->check()
        ? PostResource::getUrl('edit', ['record' => $post], panel: 'sysadmin')
        : null);
```

The callback receives the `Post` and must return a string or `null`. A `null`, or any
non-string return, is treated as "no link" — a misconfigured *return value* never throws.
If your callback itself throws, that exception is not caught and will surface as a 500 on
the preview page; ink only guards the return value, not the callback's own execution.
Which panel and which guard own the blog admin is a host decision; ink does not guess at
it.

`<x-ink::preview-banner :post="$post" :editUrl="$editUrl" />` is the shipped component that
renders this link.

## Route middleware

```php [config/ink.php]
'middleware' => ['web'],
```

Applied to the entire blog route group. Add your own alongside it — for example a host that
serves markdown to AI crawlers:

```php [config/ink.php]
'middleware' => ['web', ProvideMarkdownResponse::class],
```

## The shallow config-merge trap

<caution>

If your app has already run `php artisan vendor:publish --tag=ink-config`, be aware that
Laravel's `mergeConfigFrom` — which is how the package's config defaults reach your
`config/ink.php` — is a **shallow** merge. It's `array_merge($packageDefaults, $yourConfig)`:
for any top-level key present in both arrays, your value wins **wholesale**, array or not.

New top-level keys like `views` and `middleware` merge in fine — your published file simply
doesn't have them, so the package default fills the gap. But a key added *inside* an
existing array, such as a new flag under `features`, will not reach you: your published
`features` array replaces the package's entirely, new subkey and all. This isn't
hypothetical — it already bit a host of this package: `ink.features.mcp` silently resolved
to `null` after they'd published `config/ink.php` before that flag existed.

After upgrading, diff your published `config/ink.php` against the package's
[`config/ink.php`](https://github.com/relaticle/ink/blob/main/config/ink.php) and add any
new subkeys by hand.

</caution>

# Host-owned views: let a host use ink's controllers with its own markup

**Date:** 2026-08-06
**Status:** approved, pending implementation plan
**Branch:** `2.x` → target release `v2.3.0`

## Problem

Ink ships a `BlogController` with all six public actions, behind `features.public_routes`.
Relaticle could not use it, so it reimplemented all six as five app controllers plus its own
route group — about 150 lines duplicating the package — and set `public_routes => false`.

The cause is not the controller. It is the **layout seam**. Ink's pages open with:

```blade
@extends(config('ink.layout', 'layouts.app'))
```

Blade *inheritance*. Relaticle's layout is a *component* taking props:

```blade
<x-guest-layout :title="..." :description="..." :ogImage="...">
```

`@extends` cannot pass props to a component, so Relaticle could not render its chrome from
ink's pages. It wrote its own views, and then needed its own controllers to render them.
The controllers are a symptom.

### What the fork costs

Ink's controller does three things the copies do not:

- **Listing SEO** — every listing action calls `seo()->for(BlogListingSeo::forIndex(page: ...))`,
  which emits a **page-aware canonical** (`$url = $page > 1 ? "{$base}?page={$page}" : $base`).
  That is exactly the open finding "paginated pages canonicalise to page 1, so posts that
  exist only on page 2 have no canonical listing page". Ink already solves it; the copies
  never call it.
- **Search** — `?q=` through the `search()` scope, wired to the `blog::search` Livewire
  component ink also ships.
- **`withQueryString()`** on the paginator, so filters survive pagination.

`seo()->for($post)` on show is missing from the copy too.

## Goals

1. A host renders **its own views** through ink's controllers, publishing nothing.
2. Relaticle deletes its five controllers and route group, and its design is unchanged.
3. The route-level behaviour Relaticle added (markdown responses, numeric preview ids) is
   expressible in ink rather than lost.

## Non-goals

- Making ink's own markup match any host's design. Hosts that want bespoke markup supply
  their own views; ink's pages remain the zero-config default.
- Publishing views as the customisation route. Laravel resolves
  `resources/views/vendor/ink/**` ahead of `ink::**` automatically, so it *works* — but a
  published file is a frozen copy of package markup that silently stops receiving upstream
  changes. Two incidents in this codebase already: a `structured-data` override that had to
  be retired by hand once upstream caught up, and FilaForms rendering `ink::pages.*` where a
  package change would have blanked three posts. Publish and you drift; don't publish and
  upstream surprises you. Pointing ink at host-owned views avoids both.
- Moving `App\Support\Blog\TableOfContents` upstream. It is a clean reusable parser and
  could later become `$post->tableOfContents()`, but it is not needed to drop the
  controllers, and its markup is host design.

## Design

### View resolution

A config map of plain strings — cacheable, no closures, the Spatie lever used by
`media_model` / `path_generator` / `renderer_class`:

```php
// config/ink.php
'views' => [
    'index' => null,
    'show' => null,
    'category' => null,
    'tag' => null,
    'preview' => null,
    'feed' => null,
],
```

`null` falls back to the current `ink::pages.*`, so existing hosts are unaffected. The
controller resolves through one helper:

```php
private function view(string $key): string
{
    return config("ink.views.{$key}") ?? "ink::pages.{$key}";
}
```

Fortify's callback style (`Fortify::loginView(...)`) was considered and rejected: it exists
so a host can return an Inertia or Livewire *response*, which no ink action needs. A view
name carries everything, and stays `config:cache`-safe.

### View data contract

Ink already passes what host views need, with two gaps:

| action | passes today | change |
|---|---|---|
| `index` | `posts` | — |
| `show` | `post`, `relatedPosts` | eager-load `tags` |
| `category` | `category`, `posts` | — |
| `tag` | `tag`, `posts` | — |
| `preview` | `post` | add `relatedPosts`, add `editUrl` |
| `feed` | `posts` | — |

`show` currently loads `['category', 'author', 'seo']`; host views iterating `$post->tags`
force a lazy load. Add `tags` to the eager-load on `show` and `preview`.

### Preview edit URL

Which panel and which guard own the blog admin is a host decision, and hardcoding it breaks:
Relaticle moved its blog admin to the sysadmin panel and the app's preview controller kept
building `filament.app.resources.posts.edit`, 500ing the page for every authenticated user
while guests still saw 200. A provider-registered hook, same shape as
`Ink::resolveAuthorUsing()`:

```php
Ink::resolvePreviewEditUrlUsing(fn (Post $post): ?string => ...);
```

Defaults to `null`; hosts wanting no edit link register nothing.

### Route configuration

```php
'middleware' => ['web'],
```

Applied to the route group, replacing the hardcoded `web`. Relaticle appends
`ProvideMarkdownResponse`.

The preview route gains `->whereNumber('post')`. Without it a non-numeric segment reaches
route-model binding and throws a `QueryException` — an unauthenticated 500 on a public
route, which Relaticle already had to patch app-side.

### Feed gating

`feed()` currently runs `abort_unless(config('ink.features.feed'), 404)` while the route is
registered unconditionally. Register the route only when the flag is on and drop the runtime
abort — a disabled feature should not have a route at all, matching how `public_routes` and
`mcp` already behave.

## Relaticle's migration

Delete `app/Http/Controllers/BlogController.php`, `app/Http/Controllers/Blog/*` (5 files) and
the blog route group in `routes/web.php`. Set in `config/ink.php`:

```php
'features' => ['public_routes' => true, 'feed' => true, 'tags' => true],
'middleware' => ['web', ProvideMarkdownResponse::class],
'views' => [
    'index' => 'blog.index',
    'show' => 'blog.show',
    'category' => 'blog.index',
    'tag' => 'blog.index',
    'preview' => 'blog.preview',
    'feed' => 'blog.feed',
],
```

and register the edit-URL hook in `AppServiceProvider::boot()`.

Kept unchanged: all six blog views, `blog/pagination.blade.php`, `x-blog.toc`, and
`App\Support\Blog\TableOfContents`. The rendered design is identical.

Gained: the page-aware canonical, `seo()->for($post)` on show, `?q=` search, and
`withQueryString()`.

**Note on the `features` array.** Relaticle publishes `config/ink.php`, and
`mergeConfigFrom` is a *shallow* merge — the host's `features` array replaces the package's
wholesale, so keys added upstream inside `features` never reach a host that has published
the file. `ink.features.mcp` currently resolves to `NULL` in Relaticle for exactly this
reason. Any new top-level key (`views`, `middleware`) merges fine; anything added *inside*
`features` must be added to the host config by hand. Document this.

## Testing

1. **View resolution** — each action renders the configured view when set, and `ink::pages.*`
   when null.
2. **Data contract** — `preview` receives `relatedPosts` and `editUrl`; `show` and `preview`
   do not lazy-load `tags`.
3. **Edit-URL hook** — default null; a registered hook is used; the hook receives the post.
4. **Route config** — configured middleware is applied; `/blog/preview/not-a-number` returns
   404, not 500.
5. **Feed gating** — no feed route when `features.feed` is off; 200 when on.

On the Relaticle side, the existing public-pages suite is the regression lock: it must pass
unchanged after the controllers are deleted, proving the rendered output is identical.

## Breaking changes

None for hosts on ink's own views. `feed()` no longer aborts at runtime — a host relying on
the route existing while the flag is off would now 404 at routing time instead, which is the
intended behaviour.

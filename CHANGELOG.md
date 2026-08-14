# Changelog

All notable changes to this project will be documented in this file.

## [2.4.0] - 2026-08-14

### Added
- `UploadImageTool` MCP tool (`upload-image`): uploads an image from a fetchable URL or base64 data, sniffs the actual bytes to whitelist `jpeg`/`png`/`gif`/`webp` (no `svg`), enforces a configurable max size, and returns a storage path, public URL, and ready-to-paste markdown snippet. Shares the `create` Gate ability and `posts:create` token ability with `CreatePostTool`. The `url` fetch is scheme-restricted (`http`/`https` only) with no host allowlist — an accepted risk given the caller is already an authenticated, admin-scoped identity; see the SSRF note in [MCP Tools](https://relaticle.github.io/ink/essentials/mcp-tools#images).
- `featured_image` param on `CreatePostTool` and `UpdatePostTool`, accepting a path returned by `upload-image`. Validated against the configured uploads disk and directory to reject path injection (including traversal-shaped values); `null` on update clears it.
- New top-level `uploads` config key (`disk`, `directory`, `max_bytes`), defaulting to the same `public` disk / `ink` directory the Filament featured-image field already uses. `max_bytes` defaults to 3MB (≈4MB base64-encoded), chosen to stay safely under a common 5-8M PHP `post_max_size` floor — see the coordination note below and the comment in `config/ink.php`.
- `blog.preview` is now also registered when `features.mcp` is enabled (previously only `features.public_routes`), so `GeneratePreviewUrlTool` works pre-launch, with the rest of the public blog dark. See [UPGRADING.md](UPGRADING.md) for the `route:cache` implication.
- Rendered post images (`Post::toHtml()`) now automatically get `loading="lazy" decoding="async"`, matching the technique already used for other below-the-fold article images. No config, no opt-out.

### Fixed
- `UpdatePostTool` silently no-oped on every call: validated data was read from a variable never assigned in `run()`'s scope, so `Post::update([])` ran with nothing dirty while the tool reported a normal-looking (but stale) success payload. Introduced in 2.2.0's `resolveRecord()`/`run()` refactor.
- `UpdateCategoryTool` had the same scope bug, but surfaced as a masked "An internal server error occurred." instead of a no-op, since `blog_categories.name` is `NOT NULL`.
- `GeneratePreviewUrlTool` threw an uncaught `RouteNotFoundException` (masked as the same generic internal error) when `blog.preview` wasn't registered. It now guards with `Route::has()` and returns an actionable error; combined with the routing fix above, this should not trip in a correctly booted app.
- `featured_image`'s uploads-directory confinement was bypassable: it only checked that the value started with the directory prefix as a string, but Flysystem's local disk normalizes `..` segments by default, so a value like `ink/x/../../other/secret.png` passed the prefix check yet resolved to a file outside the uploads directory (and some traversal shapes threw an uncaught `PathTraversalDetected`, masked as a generic internal error, instead of a clean validation failure). The path is now rejected outright if it contains any traversal segment, and the disk existence check is wrapped to convert any remaining Flysystem exception into a clean validation error.
- A non-string `featured_image` (a JSON number, boolean, or array — laravel/mcp does not validate `tools/call` arguments against the tool's schema before invoking it) threw an uncaught `TypeError` in the same confinement check, masked the same way. It's now rejected with a clean validation error.
- `UploadImageTool`'s size cap was enforced only after the full body was already in memory (a fully base64-decoded payload, or a fully downloaded URL response), defeating the point of a cap against a large/malicious payload. Base64 payloads are now rejected by their encoded length before `base64_decode()` runs; URL fetches now send a `HEAD` request first and reject an oversized `Content-Length` before the body is ever downloaded, and the body itself is now read in bounded chunks via a streamed request rather than buffered whole, so a server that omits or understates `Content-Length` still can't force a full download past the cap.

### Coordination note: `post_max_size`

The base64 `data` upload path is bound by PHP's `post_max_size` (and any webserver/proxy
body-size limit) *before Laravel ever boots* — base64 inflates the binary size by ~4/3, and
an oversized request body is rejected by PHP itself, returning a raw PHP warning wrapped in
an HTTP 200 instead of a clean JSON-RPC error. No app-level config, including `max_bytes`,
can intercept this. `ink.uploads.max_bytes` defaults to 3MB for exactly this reason; the
`url` upload path is unaffected, since the image bytes never travel through the MCP request
body. See [MCP Tools → Images](https://relaticle.github.io/ink/essentials/mcp-tools#images).

## [2.3.0] - 2026-08-06

### Added
- Host-owned views: an `ink.views` config map lets a host point any of the six public-routes actions (`index`, `show`, `category`, `tag`, `preview`, `feed`) at a view it already owns, instead of rendering ink's own `ink::pages.*` views. Every key defaults to `null` and is purely additive — see [Host-Owned Views](https://relaticle.github.io/ink/essentials/host-owned-views).
- `ink.middleware` config key for the blog route group, defaulting to `['web']`. Add your own middleware (e.g. a response transformer for AI crawlers) alongside it.
- `preview()` now passes `relatedPosts` and `editUrl` to the view. Register `Ink::resolvePreviewEditUrlUsing()` in a service provider's `boot()` to supply the edit link; ink's own preview page now renders it via `<x-ink::preview-banner>`, matching the contract host-owned preview views already had.
- `Post::tableOfContents(string $tag = 'h2')` builds a `fragment => heading text` map from the rendered post, for hosts building an in-page table of contents. Restricted to `h1`-`h6`; any other value throws `InvalidArgumentException`.
- `show()` and `preview()` now eager-load `tags` on the post, so `$post->tags` no longer triggers a lazy-load query in either view.

### Changed
- `/blog/preview/{post}` now constrains `{post}` to `[0-9]{1,18}` (the safe maximum for a signed 64-bit id) instead of the unbounded `whereNumber()`, closing a public unauthenticated 500 that an over-long digit segment could trigger via a `bigint` overflow on Postgres.
- `blog.feed` is now registered only when `features.feed` is `true`, instead of always being registered behind a runtime `abort_unless()` check.

See [UPGRADING.md](UPGRADING.md) for the full migration notes, including the `route:cache` implication of the `blog.feed` change.

## [2.2.0] - 2026-08-06

### Added
- First-class MCP support: a shippable `BlogServer`, tool authorization through the host's `Gate` instead of a hardcoded `is_admin` check, and an `Ink::resolveAuthorUsing()` hook for hosts whose staff aren't `ink.author_model` instances.

### Changed
- MCP tools now store markdown instead of pre-rendering to HTML, matching what the Filament editor already stored. The included migration converts existing HTML rows.

### Fixed
- Long unbreakable tokens (URLs, code identifiers) in a post title no longer overflow the post page layout.
- JSON-LD structured data now escapes `</script>`, `&`, `'` and `"`, closing an injection hole where a post title or excerpt containing `</script>` broke out of the schema block and rendered as visible page text.
- The default `show` and `preview` views now strip raw HTML from post content before rendering markdown, closing a stored-XSS hole where a `<script>` in a post body executed on the public page.

## [2.1.1] - 2026-08-04

### Changed
- Widened the `spatie/laravel-sluggable` constraint to allow `^4`.

## [2.1.0] - 2026-05-14

### Added
- `Relaticle\Ink\Support\BlogListingSeo` helper for building per-page `SEOData` for listings. Headless consumers can call `BlogListingSeo::forIndex/forCategory/forTag` from their own controllers.
- Auto-emit `FAQPage` JSON-LD on post pages when content contains an `## FAQ` H2 followed by `### Question?` / answer-paragraph pairs. Controlled by `schema.faq_auto` config (default `true`).
- Auto-emit `HowTo` JSON-LD on post pages when content contains a `## Steps` H2 followed by `### Step Name` / paragraph pairs. Opt-in via `schema.howto_auto` config (default `false`).
- `Relaticle\Ink\Support\SchemaExtractor` helper for FAQ + HowTo HTML parsing.
- Auto-emit `Blog` and `CollectionPage` JSON-LD on listing routes (`blog.index` emits both; `blog.category` and `blog.tag` emit `CollectionPage`).
- Numbered, aria-labeled pagination view at `ink::pagination.blog`. Listing pages (index/category/tag) use it by default. Publish via `php artisan vendor:publish --tag=ink-views` to customize.
- `wire:navigate` on the `<x-ink::post-card>` post-link and pagination links for SPA-feel navigation in Livewire 4 hosts. No-op when Livewire is not present.
- `Post::search($term)` query scope. Defaults to a portable LIKE search across title/excerpt/content. Override via `search.callback` config for Postgres FTS, MySQL FULLTEXT, Scout, etc.
- The shipped `/blog` route now honors `?q=` for search. The existing `BlogListingSeo::forIndex(searchQuery:)` call ensures `noindex,follow` is set on search result URLs.
- `BlogSearch` Livewire component (`<livewire:blog::search />`) with URL-synced `?q=` query, 400ms debounce, and empty state. Theme-agnostic — publish the view at `resources/views/vendor/ink/livewire/blog-search.blade.php` to restyle.

### Fixed
- `BlogSitemapGenerator` now includes `/blog/tag/{slug}` URLs when the tags feature is enabled and the tag has published posts.
- Listing routes (`/blog`, `/blog/category/{slug}`, `/blog/tag/{slug}`) now emit per-page canonical URLs and page-aware titles. Previously every paginated page canonicalized to page 1, causing Google to treat pages 2+ as duplicate content.
- Shipped `show` and `preview` routes now call `seo()->for($post)` so post-attached SEO (BlogPosting + BreadcrumbList + FAQPage JSON-LD) actually renders in public-routes mode. Previously the schema only worked for consumers who overrode the controller.

## [2.0.0] - 2026-05-14

### Changed (BREAKING)
- **Package renamed** from `manukminasyan/filament-blog` to `relaticle/ink`
- **PHP namespace** changed from `ManukMinasyan\FilamentBlog\` to `Relaticle\Ink\`
- **Service provider** renamed: `FilamentBlogServiceProvider` → `InkServiceProvider`
- **Filament plugin** renamed: `FilamentBlogPlugin` → `InkPlugin`
- **Config file** renamed: `config/filament-blog.php` → `config/ink.php`. Use `config('ink.X')` instead of `config('filament-blog.X')`.
- **Publish tags** renamed: `filament-blog-config` → `ink-config`, `filament-blog-views` → `ink-views`, `filament-blog-migrations` → `ink-migrations`, `filament-blog-translations` → `ink-translations`
- **View component prefix** renamed: `<x-blog::post-card>` → `<x-ink::post-card>` (all components affected)
- **View namespace** renamed: `view('blog::X')` → `view('ink::X')`

### Unchanged (compatibility-preserving)
- Database table names (`blog_posts`, `blog_categories`, `blog_tags`, `blog_post_tag`) — no data migration required
- Route names (`blog.index`, `blog.show`, `blog.category`, `blog.preview`, `blog.feed`, `blog.tag`) — public API contract preserved
- URL prefix default (still `/blog`, configurable via `config('ink.prefix')`)
- All public model/component APIs and method signatures

### Migration
```bash
composer remove manukminasyan/filament-blog
composer require relaticle/ink:^2.0
```

See [UPGRADING.md](UPGRADING.md) for the full sed recipe.

## [1.0.1] - 2026-04-01

### Fixed
- Detect user ID column type for `author_id` foreign key (supports ULID/UUID)

## [1.0.0] - 2026-04-01

### Added
- Initial release
- Post and Category Eloquent models with SEO, slugs, soft deletes
- PostStatus enum (Draft/Published)
- Filament PostResource with markdown editor, status toggle, SEO section
- Filament CategoryResource with post count
- 13 MCP tools for blog post and category CRUD
- 10 publishable Blade components (meta-tags, structured-data, feed-link, feed, post-card, post-header, post-body, related-posts, category-badge, preview-banner)
- BlogSitemapGenerator with route-aware URL generation
- Dark mode support in all UI components
- Route-aware `Post::getUrl()` with fallback

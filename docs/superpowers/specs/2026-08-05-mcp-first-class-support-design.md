# First-class MCP support in ink 2.x

**Date:** 2026-08-05
**Status:** approved, pending implementation plan
**Branch:** `2.x`

## Problem

Ink advertises "13 MCP Tools" but ships no way to use them:

- There is no `Server` class. Every host hand-writes one; FilaForms' `BlogServer` is 45 lines of
  boilerplate that the package could own.
- `laravel/mcp` sits in `require-dev` and is absent from `suggest`, so a host installing ink has no
  signal that it needs the dependency.
- All 13 tools hardcode `$request->user()?->is_admin`. That works only where staff are rows in the
  host's `User` table. Relaticle's staff are `SystemAdministrator` records on a separate `sysadmin`
  guard, so no caller can ever satisfy the check — the tools are unusable there, and adding an
  `is_admin` column to the customer `users` table would hand every customer a path to the company
  blog.
- The same six-line authorization preamble is duplicated across all 13 tools.
- `CreatePostTool` and `UpdatePostTool` run `Str::markdown()` and store **HTML**, while the Filament
  `MarkdownEditor` stores raw **markdown** and `Post::toHtml()` renders at read time. The two write
  paths disagree about what `content` holds.
- The tools have no test coverage at all.

### The divergence is already causing damage

Commit `2ac33d3` set `html_input => 'strip'` on `ink::pages.show` and `ink::pages.preview` to close a
stored-XSS hole. FilaForms renders those package views through its own controller
(`view('ink::pages.preview')`). Three of its 46 posts hold HTML from the MCP tools; post #77 stores
`<h2>FAQ</h2><h3>What is FilaForms?</h3>…` and renders to an **empty string** once HTML is stripped.

Render-time sanitisation cannot be correct while storage format is ambiguous. Fixing storage is a
precondition, not a nice-to-have.

## Goals

1. A host enables blog MCP without writing a server class.
2. Authorization is the host's existing Gate/Policy, not a package-invented flag.
3. Hosts whose staff are not `User` instances work without schema changes.
4. One authorization implementation, not thirteen.
5. `content` means the same thing whichever write path produced it.
6. The tools are tested.

## Non-goals

- Backward compatibility. 2.x is the primary line and may break.
- OAuth / dynamic client registration — `laravel/mcp` owns that.
- Per-tool visibility filtering via `shouldRegister()`. Per-call authorization is the security
  boundary; hiding tools from `tools/list` is cosmetic and adds a second code path.
- Resources and Prompts. Tools only.

## Design

### Authorization: the host's Gate

Tools call the host's Gate. Ink defines no policy and no ability names beyond Laravel's conventional
`viewAny` / `view` / `create` / `update` / `delete` against `Post::class` and `Category::class`.

```php
Gate::forUser($caller)->allows('create', Post::class)
```

`forUser` is required because the caller may come from a non-default guard. Relaticle's existing
`Relaticle\SystemAdmin\Policies\PostPolicy` satisfies this unchanged; FilaForms adds a five-line
policy returning `$user->is_admin`.

A host with no policy registered for `Post`/`Category` gets a denial, not an error — Laravel's Gate
returns false when nothing is defined. The tools fail closed.

**Ability mapping.** Each tool declares one Gate ability and one Sanctum ability:

| Tool | Gate ability | Target | Token ability |
|---|---|---|---|
| `ListPostsTool` | `viewAny` | `Post::class` | `posts:read` |
| `GetPostTool` | `view` | instance | `posts:read` |
| `GeneratePreviewUrlTool` | `view` | instance | `posts:read` |
| `CreatePostTool` | `create` | `Post::class` | `posts:create` |
| `UpdatePostTool` | `update` | instance | `posts:update` |
| `DeletePostTool` | `delete` | instance | `posts:delete` |
| `RestorePostTool` | `restore` | instance | `posts:delete` |
| `ListCategoriesTool` | `viewAny` | `Category::class` | `categories:read` |
| `GetCategoryTool` | `view` | instance | `categories:read` |
| `CreateCategoryTool` | `create` | `Category::class` | `categories:create` |
| `UpdateCategoryTool` | `update` | instance | `categories:update` |
| `DeleteCategoryTool` | `delete` | instance | `categories:delete` |
| `RestoreCategoryTool` | `restore` | instance | `categories:delete` |

**Ordering.** Class-target tools (`viewAny`, `create`) authorize before doing any work.
Instance-target tools resolve the record first, return the existing "not found" error if it is
missing, then authorize against the instance. Authorizing before the lookup would leak existence
through the error message.

### Authorization: Sanctum abilities stay

`tokenCan('posts:create')` is retained as a **second, orthogonal axis**. The Gate answers "may this
identity manage the blog"; the token ability answers "may this credential do so". A content-writer
token scoped to `posts:read,posts:create` is a real use case and Sanctum is the idiomatic mechanism.
Ability names are unchanged: `posts:{read,create,update,delete}`,
`categories:{read,create,update,delete}`.

### The caller and its guard

```php
$caller = $request->user(config('ink.mcp.guard'));
```

`ink.mcp.guard` is a plain string (or null for the default guard), so `config:cache` is safe.

### `Relaticle\Ink\Ink` — manager class

One static hook, registered in a host service provider's `boot()`. Closures live in providers, never
in config — `config:cache` serialises with `var_export` and throws on closures. (Ink's existing
`ink.search.callback` has this latent bug; out of scope here, but do not add a second instance.)

```php
Ink::resolveAuthorUsing(fn (Authenticatable $caller): ?Model => ...);
```

Default when unset: return `$caller` if it is an instance of `config('ink.author_model')`, else
`null`. `blog_posts.author_id` is NOT NULL, so an unresolved author is a hard tool error naming the
hook — a host misconfiguration, not a caller mistake.

### `Relaticle\Ink\Mcp\BlogServer`

Ships the class FilaForms hand-wrote: `#[Name('Blog')]`, `#[Version]`, `#[Instructions]`, and the 13
tools in `$tools`.

### `Relaticle\Ink\Mcp\Concerns\AuthorizesBlogTools`

Replaces the duplicated preamble. Each tool opens with:

```php
// class target — before any work
if ($denied = $this->denyUnlessAuthorized($request, 'create', Post::class, 'posts:create')) {
    return $denied;
}

// instance target — after the record is resolved
if ($denied = $this->denyUnlessAuthorized($request, 'update', $post, 'posts:update')) {
    return $denied;
}
```

The trait resolves the caller, runs the Gate, then the token ability, returning `null` on success or
a `Response::error()` naming the specific failure. It also exposes `resolveAuthor()`, which only
`CreatePostTool` uses — it is the sole tool that writes `author_id`.

### Route registration

`InkServiceProvider::packageBooted()` registers the MCP route when `ink.features.mcp` is true,
mirroring the existing `features.public_routes` branch and defaulting **off**. Hosts wanting custom
routing leave the flag off and register `BlogServer` themselves.

```php
'features' => ['mcp' => false, /* ... */],
'mcp' => [
    'path' => '/mcp/blog',
    'guard' => null,
    'middleware' => ['auth:sanctum'],
],
```

All scalars and arrays — cacheable.

### Content storage

`CreatePostTool` and `UpdatePostTool` stop calling `Str::markdown()` and store the markdown they were
given, exactly as the panel does. The tools validate; they no longer transform. Render-time safety is
the host's markdown configuration, which is where it belongs and where ink's own views already
enforce it.

### Legacy HTML content

A migration converts HTML-looking `content` to markdown via `league/html-to-markdown`. Rows it cannot
convert cleanly are left untouched and listed in the migration output for manual review — silently
mangling a published post is worse than reporting it.

Detection is deliberately narrow: content matching a block-level HTML tag at the start
(`<p>`, `<h1>`–`<h6>`, `<ul>`, `<ol>`, `<pre>`, `<blockquote>`). Markdown containing incidental inline
HTML is not rewritten.

## Dependency upgrades

2.x targets current majors only; the optional older constraints are dropped. Both known consumers
(Relaticle, FilaForms) are already on Laravel 13 / Filament 5 / PHP 8.4, so nothing is stranded.

| Package | Was | Becomes |
|---|---|---|
| `illuminate/contracts` | `^12.0\|^13.0` | `^13.0` |
| `spatie/laravel-sitemap` | `^7.0 \|\| ^8.0` | `^8.2` |
| `spatie/laravel-sluggable` | `^3.0 \|\| ^4.0` | `^4.0` |
| `spatie/laravel-markdown` | `^2.7` | `^2.8` |
| `spatie/laravel-package-tools` | `^1.15` | `^1.93` |
| `ralphjsmit/laravel-seo` | `^1.7` | `^1.8` |
| `ralphjsmit/laravel-filament-seo` | `^2.0` | `^2.2` |
| `filament/filament` | `^5.0` | `^5.0` (unchanged) |
| `php` | `^8.4` | `^8.4` (unchanged) |
| `league/html-to-markdown` | — | `^5.1` (new, for the migration) |
| `laravel/mcp` | `^0.5` dev-only | `^0.9` in `require-dev` **and** `suggest` |
| `pestphp/pest` | `^3.0` | `^5.0` |
| `pestphp/pest-plugin-laravel` | `^3.0` | `^5.0` |
| `orchestra/testbench` | `^10.0\|^11.0` | `^11.0` |
| `laravel/pint` | `^1.14` | `^1.30` |

`Request::user(?string $guard)` and `Primitive::shouldRegister()` exist in both mcp v0.5.9 and v0.9.1,
so the tool code is compatible across the bump; FilaForms should still move to `^0.9` to match.

The Pest 3 → 5 jump requires the existing 63 tests to be re-run and any breakages fixed as part of
this work.

## Error handling

`Response::error()` with distinct, actionable messages so an agent can self-correct:

| Condition | Message |
|---|---|
| No authenticated caller | `Authentication required.` |
| Gate denies | `Permission denied.` |
| Token ability missing | `Token missing required ability: {ability}` |
| Author unresolved | `No author could be resolved for this caller. Configure Ink::resolveAuthorUsing().` |
| Record not found | unchanged per tool |

## Testing

The tools currently have zero coverage. New suite:

1. **Authorization matrix** — policy allows/denies × token ability present/absent, for one read tool
   and one write tool.
2. **Guard resolution** — a caller on a non-default guard authorizes correctly.
3. **Author resolution** — default path, custom hook, and unresolved → error.
4. **Route registration** — the MCP route exists only when `features.mcp` is on.
5. **Content parity** — a post created through `CreatePostTool` and one created through the model
   with the same markdown render byte-identically. This is the regression lock for the storage bug.
6. **Legacy migration** — an HTML row converts; an unconvertible row is left intact and reported.

## Host integration

**FilaForms** — add a `PostPolicy`/`CategoryPolicy` returning `$user->is_admin`, delete the
hand-written `BlogServer`, set `features.mcp` and `mcp.path` to `/mcp/blog`. No author hook needed.

**Relaticle** — set `features.mcp`, `mcp.guard => 'sysadmin'`, reuse the existing
`SystemAdmin\{Post,Category,Tag}Policy`, and add:

```php
Ink::resolveAuthorUsing(fn (SystemAdministrator $admin): ?User =>
    User::firstWhere('email', $admin->email));
```

No `is_admin` column, and the customer panel is untouched.

## Breaking changes for `UPGRADING.md`

1. `content` written by MCP tools is now markdown, not HTML. Existing HTML rows are converted by
   migration; review anything the migration reports as skipped.
2. Tools authorize through the host's Gate. A host with no policy for `Post`/`Category` will find the
   tools denied until one is registered.
3. Laravel 12, Pest 3/4 and Testbench 10 are no longer supported.

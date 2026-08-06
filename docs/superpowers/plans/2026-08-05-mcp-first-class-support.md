# First-class MCP Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `relaticle/ink` 2.x ship usable MCP support — a registerable server, authorization through the host's Gate, host-controlled author attribution, and consistent markdown storage.

**Architecture:** A trait centralises the two authorization axes (host Gate for identity, Sanctum ability for credential) that are currently copy-pasted across 13 tools. A static manager class carries the one hook a host must supply (author resolution). A shipped `BlogServer` plus an opt-in route replace the server class every host writes by hand. The MCP tools stop transforming markdown to HTML, and a migration converts rows written under the old behaviour.

**Tech Stack:** PHP 8.4, Laravel 13, Filament 5, `laravel/mcp` ^0.9, Pest 5, Orchestra Testbench 11, `league/html-to-markdown` ^5.1.

## Global Constraints

- Target branch is `2.x`. No backward compatibility is required or attempted.
- Dependency constraints name current majors only — no `||` alternatives to older versions.
- Closures never go in config files; `config:cache` serialises with `var_export` and throws. Host callables are registered on the `Ink` manager in a service provider's `boot()`.
- Sanctum token abilities are retained alongside the Gate. They are separate axes, not redundant.
- Every task ends green: `vendor/bin/pest` and `vendor/bin/pint --test` both pass before committing.
- Ability names are unchanged: `posts:{read,create,update,delete}`, `categories:{read,create,update,delete}`.

---

## File Structure

**Create:**
- `src/Ink.php` — static manager holding the author-resolution hook
- `src/Mcp/BlogServer.php` — the shippable server with all 13 tools
- `src/Mcp/Concerns/AuthorizesBlogTools.php` — caller resolution, Gate check, token check, author resolution
- `routes/mcp.php` — the opt-in MCP route
- `database/migrations/2026_08_05_000000_convert_html_post_content_to_markdown.php`
- `tests/Feature/Mcp/AuthorizationTest.php`
- `tests/Feature/Mcp/AuthorResolutionTest.php`
- `tests/Feature/Mcp/ContentStorageTest.php`
- `tests/Feature/Mcp/ServerRegistrationTest.php`
- `tests/Feature/Mcp/HtmlContentMigrationTest.php`
- `tests/Fixtures/Policies/{PostPolicy,CategoryPolicy}.php` — test-host policies

**Modify:**
- `composer.json` — dependency upgrades, `suggest`, `league/html-to-markdown`
- `config/ink.php` — `features.mcp`, `mcp.{path,guard,middleware}`
- `src/InkServiceProvider.php` — conditional MCP route registration
- All 13 files in `src/Mcp/Tools/` — use the trait, drop the duplicated preamble
- `src/Mcp/Tools/CreatePostTool.php`, `src/Mcp/Tools/UpdatePostTool.php` — stop calling `Str::markdown()`
- `tests/TestCase.php` — register test policies
- `README.md`, `UPGRADING.md`, `docs/content/2.essentials/3.mcp-tools.md`

---

### Task 1: Upgrade dependencies to current majors

**Files:**
- Modify: `composer.json`

**Interfaces:**
- Consumes: nothing
- Produces: Pest 5 + Testbench 11 + `laravel/mcp` ^0.9 available to every later task; `League\HTMLToMarkdown\HtmlConverter` available to Task 8.

- [ ] **Step 1: Rewrite the dependency blocks**

Replace the `require`, `require-dev` and (new) `suggest` sections of `composer.json` with:

```json
    "require": {
        "php": "^8.4",
        "filament/filament": "^5.0",
        "illuminate/contracts": "^13.0",
        "league/html-to-markdown": "^5.1",
        "ralphjsmit/laravel-filament-seo": "^2.2",
        "ralphjsmit/laravel-seo": "^1.8",
        "spatie/laravel-markdown": "^2.8",
        "spatie/laravel-package-tools": "^1.93",
        "spatie/laravel-sitemap": "^8.2",
        "spatie/laravel-sluggable": "^4.0"
    },
    "require-dev": {
        "laravel/mcp": "^0.9",
        "laravel/pint": "^1.30",
        "orchestra/testbench": "^11.0",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-laravel": "^5.0"
    },
    "suggest": {
        "laravel/mcp": "Required to expose the blog MCP tools (^0.9)."
    },
```

- [ ] **Step 2: Resolve and install**

Run: `composer update --with-all-dependencies`
Expected: resolves without conflict. If `filament/filament` blocks Testbench 11, stop and report — do not relax a constraint to force it.

- [ ] **Step 3: Run the existing suite against Pest 5**

Run: `vendor/bin/pest`
Expected: the 63 existing tests run. Pest 5 may report failures from renamed APIs. Fix each one in the test layer only — production behaviour is not changing in this task.

- [ ] **Step 4: Confirm green**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock tests/
git commit -m "chore: target current majors only and suggest laravel/mcp"
```

---

### Task 2: The `Ink` manager and author resolution

**Files:**
- Create: `src/Ink.php`
- Test: `tests/Feature/Mcp/AuthorResolutionTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - `Ink::resolveAuthorUsing(?Closure $callback): void`
  - `Ink::resolveAuthor(?Authenticatable $caller): ?Model`
  - `Ink::flushState(): void` (test helper — resets the hook)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mcp/AuthorResolutionTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Relaticle\Ink\Ink;

afterEach(fn () => Ink::flushState());

test('it returns the caller when the caller is the configured author model', function () {
    $user = testUser();

    expect(Ink::resolveAuthor($user)?->getKey())->toBe($user->getKey());
});

test('it returns null when the caller is not the configured author model', function () {
    config()->set('ink.author_model', stdClass::class);

    expect(Ink::resolveAuthor(testUser()))->toBeNull();
});

test('it returns null when there is no caller', function () {
    expect(Ink::resolveAuthor(null))->toBeNull();
});

test('a host hook overrides the default resolution', function () {
    $designated = testUser();

    Ink::resolveAuthorUsing(fn (): User => $designated);

    $other = (new Orchestra\Testbench\Factories\UserFactory)->create(['email' => 'other@example.test']);

    expect(Ink::resolveAuthor($other)?->getKey())->toBe($designated->getKey());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorResolutionTest.php`
Expected: FAIL — `Class "Relaticle\Ink\Ink" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Ink.php`:

```php
<?php

declare(strict_types=1);

namespace Relaticle\Ink;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class Ink
{
    private static ?Closure $authorResolver = null;

    /**
     * Register how an MCP caller maps to the model that authors a post.
     *
     * Hosts whose staff are not instances of `ink.author_model` — separate
     * guard, separate table — supply the mapping here. Register in a service
     * provider's boot(); a closure in config breaks config:cache.
     */
    public static function resolveAuthorUsing(?Closure $callback): void
    {
        self::$authorResolver = $callback;
    }

    public static function resolveAuthor(?Authenticatable $caller): ?Model
    {
        if (! $caller instanceof Authenticatable) {
            return null;
        }

        if (self::$authorResolver instanceof Closure) {
            $resolved = (self::$authorResolver)($caller);

            return $resolved instanceof Model ? $resolved : null;
        }

        $authorModel = config('ink.author_model');

        return is_string($authorModel) && $caller instanceof $authorModel && $caller instanceof Model
            ? $caller
            : null;
    }

    public static function flushState(): void
    {
        self::$authorResolver = null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorResolutionTest.php`
Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
git add src/Ink.php tests/Feature/Mcp/AuthorResolutionTest.php
git commit -m "feat: add Ink manager with host author-resolution hook"
```

---

### Task 3: Config keys and test-host policies

**Files:**
- Modify: `config/ink.php`
- Create: `tests/Fixtures/Policies/PostPolicy.php`, `tests/Fixtures/Policies/CategoryPolicy.php`
- Modify: `tests/TestCase.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - config keys `ink.features.mcp` (bool), `ink.mcp.path` (string), `ink.mcp.guard` (?string), `ink.mcp.middleware` (array<string>)
  - `Relaticle\Ink\Tests\Fixtures\Policies\PostPolicy` and `CategoryPolicy`, both gated on a static `$allow` flag so tests can toggle authorization.

- [ ] **Step 1: Add the config keys**

In `config/ink.php`, add `'mcp' => false` to the `features` array so it reads:

```php
    'features' => [
        'public_routes' => false,
        'feed' => false,
        'sitemap' => false,
        'tags' => false,
        'media_library' => false,
        'mcp' => false,
    ],
```

Then add a new top-level block after `features`:

```php
    'mcp' => [
        'path' => '/mcp/blog',
        'guard' => null,
        'middleware' => ['auth:sanctum'],
    ],
```

- [ ] **Step 2: Create the test policies**

Create `tests/Fixtures/Policies/PostPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Relaticle\Ink\Tests\Fixtures\Policies;

class PostPolicy
{
    public static bool $allow = true;

    public function viewAny(): bool
    {
        return self::$allow;
    }

    public function view(): bool
    {
        return self::$allow;
    }

    public function create(): bool
    {
        return self::$allow;
    }

    public function update(): bool
    {
        return self::$allow;
    }

    public function delete(): bool
    {
        return self::$allow;
    }

    public function restore(): bool
    {
        return self::$allow;
    }
}
```

Create `tests/Fixtures/Policies/CategoryPolicy.php` with identical contents except `class CategoryPolicy`.

- [ ] **Step 3: Register the policies in the test host**

In `tests/TestCase.php`, add these imports:

```php
use Illuminate\Support\Facades\Gate;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Tests\Fixtures\Policies\CategoryPolicy;
use Relaticle\Ink\Tests\Fixtures\Policies\PostPolicy;
```

and append to the end of `defineEnvironment($app)`:

```php
        PostPolicy::$allow = true;
        CategoryPolicy::$allow = true;

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
```

- [ ] **Step 4: Confirm nothing regressed**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add config/ink.php tests/Fixtures tests/TestCase.php
git commit -m "feat: add mcp config keys and test-host blog policies"
```

---

### Task 4: The authorization trait

**Files:**
- Create: `src/Mcp/Concerns/AuthorizesBlogTools.php`
- Test: `tests/Feature/Mcp/AuthorizationTest.php`

**Interfaces:**
- Consumes: `Ink::resolveAuthor()` (Task 2); config keys and test policies (Task 3).
- Produces, on the trait:
  - `denyUnlessAuthorized(Request $request, string $ability, Model|string $target, string $tokenAbility): ?Response`
  - `caller(Request $request): ?Authenticatable`
  - `resolveAuthorOrFail(Request $request): Model|Response`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mcp/AuthorizationTest.php`:

```php
<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\ListPostsTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Tests\Fixtures\Policies\PostPolicy;

function blogArgs(Category $category): array
{
    return [
        'title' => 'From MCP',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ];
}

test('a caller the policy allows may list posts', function () {
    PostPolicy::$allow = true;

    BlogServer::actingAs(testUser())
        ->tool(ListPostsTool::class)
        ->assertOk();
});

test('a caller the policy denies may not list posts', function () {
    PostPolicy::$allow = false;

    BlogServer::actingAs(testUser())
        ->tool(ListPostsTool::class)
        ->assertSee('Permission denied.');
});

test('a caller the policy denies may not create a post', function () {
    PostPolicy::$allow = false;
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(testUser())
        ->tool(CreatePostTool::class, blogArgs($category))
        ->assertSee('Permission denied.');
});

test('an unauthenticated caller is rejected', function () {
    BlogServer::tool(ListPostsTool::class)
        ->assertSee('Authentication required.');
});
```

Note: these tests exercise the Gate axis. The token-ability axis is covered in Task 6, once the tools consume the trait, because `testUser()` has no Sanctum token and `tokenCan()` returns true for a tokenless user.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorizationTest.php`
Expected: FAIL — `Class "Relaticle\Ink\Mcp\BlogServer" not found`.

- [ ] **Step 3: Write the trait**

Create `src/Mcp/Concerns/AuthorizesBlogTools.php`:

```php
<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Relaticle\Ink\Ink;

trait AuthorizesBlogTools
{
    /**
     * Two independent axes: the host's Gate decides whether this identity may
     * manage the blog, the Sanctum ability decides whether this credential may.
     * Returns null when the call is allowed.
     */
    protected function denyUnlessAuthorized(
        Request $request,
        string $ability,
        Model|string $target,
        string $tokenAbility,
    ): ?Response {
        $caller = $this->caller($request);

        if (! $caller instanceof Authenticatable) {
            return Response::error('Authentication required.');
        }

        if (! Gate::forUser($caller)->allows($ability, $target)) {
            return Response::error('Permission denied.');
        }

        if (method_exists($caller, 'tokenCan') && ! $caller->tokenCan($tokenAbility)) {
            return Response::error("Token missing required ability: {$tokenAbility}");
        }

        return null;
    }

    protected function caller(Request $request): ?Authenticatable
    {
        return $request->user(config('ink.mcp.guard'));
    }

    protected function resolveAuthorOrFail(Request $request): Model|Response
    {
        $author = Ink::resolveAuthor($this->caller($request));

        return $author instanceof Model
            ? $author
            : Response::error('No author could be resolved for this caller. Configure Ink::resolveAuthorUsing().');
    }
}
```

- [ ] **Step 4: Write the server so the test can run**

Create `src/Mcp/BlogServer.php`:

```php
<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Relaticle\Ink\Mcp\Tools\CreateCategoryTool;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\DeleteCategoryTool;
use Relaticle\Ink\Mcp\Tools\DeletePostTool;
use Relaticle\Ink\Mcp\Tools\GeneratePreviewUrlTool;
use Relaticle\Ink\Mcp\Tools\GetCategoryTool;
use Relaticle\Ink\Mcp\Tools\GetPostTool;
use Relaticle\Ink\Mcp\Tools\ListCategoriesTool;
use Relaticle\Ink\Mcp\Tools\ListPostsTool;
use Relaticle\Ink\Mcp\Tools\RestoreCategoryTool;
use Relaticle\Ink\Mcp\Tools\RestorePostTool;
use Relaticle\Ink\Mcp\Tools\UpdateCategoryTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;

#[Name('Blog')]
#[Description('Manage blog posts and categories. Full CRUD with soft delete; posts carry title, markdown content, excerpt, category, status and published_at.')]
class BlogServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListPostsTool::class,
        GetPostTool::class,
        CreatePostTool::class,
        UpdatePostTool::class,
        DeletePostTool::class,
        RestorePostTool::class,
        GeneratePreviewUrlTool::class,
        ListCategoriesTool::class,
        GetCategoryTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,
        DeleteCategoryTool::class,
        RestoreCategoryTool::class,
    ];
}
```

If `Laravel\Mcp\Server\Attributes\Version` exists in the installed `laravel/mcp`, also add `#[Version('2.0.0')]` and its import. Check with:
`ls vendor/laravel/mcp/src/Server/Attributes/`

- [ ] **Step 5: Run test — expect the Gate tests to still fail**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorizationTest.php`
Expected: the "allows" test passes; the "denies" tests FAIL, because the tools still use `is_admin` and have not adopted the trait. That is corrected in Task 5.

- [ ] **Step 6: Commit**

```bash
git add src/Mcp/Concerns/AuthorizesBlogTools.php src/Mcp/BlogServer.php tests/Feature/Mcp/AuthorizationTest.php
git commit -m "feat: add blog MCP server and authorization trait"
```

---

### Task 5: Convert all 13 tools to the trait

**Files:**
- Modify: all 13 files in `src/Mcp/Tools/`
- Test: `tests/Feature/Mcp/AuthorizationTest.php` (from Task 4)

**Interfaces:**
- Consumes: `AuthorizesBlogTools` (Task 4), `Ink::resolveAuthor()` (Task 2)
- Produces: every tool authorizes via Gate + token ability; `CreatePostTool` attributes via `resolveAuthorOrFail()`.

Ability mapping — apply exactly:

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

- [ ] **Step 1: Convert a class-target tool**

In `src/Mcp/Tools/ListPostsTool.php`, add `use Relaticle\Ink\Mcp\Concerns\AuthorizesBlogTools;` to the imports and `use AuthorizesBlogTools;` as the first line of the class body. Replace the two-block `is_admin` / `tokenCan` preamble at the top of `handle()` with:

```php
        if ($denied = $this->denyUnlessAuthorized($request, 'viewAny', Post::class, 'posts:read')) {
            return $denied;
        }
```

- [ ] **Step 2: Convert an instance-target tool**

In `src/Mcp/Tools/DeletePostTool.php`, add the same import and `use AuthorizesBlogTools;`. Delete the `is_admin` / `tokenCan` preamble. Then move authorization to *after* the record lookup, so the existing "not found" error still fires first and a denial cannot leak existence:

```php
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'You must provide the post ID to delete.',
        ]);

        $post = Post::find($validated['id']);

        if (! $post) {
            return Response::error('Post not found.');
        }

        if ($denied = $this->denyUnlessAuthorized($request, 'delete', $post, 'posts:delete')) {
            return $denied;
        }

        $post->delete();
```

- [ ] **Step 3: Convert the remaining 11 tools**

Every tool takes one of the two shapes already written above — there is no third case and no judgment call.

Use the **Step 1 (class-target)** shape, authorizing before validation, for:
`CreatePostTool`, `ListCategoriesTool`, `CreateCategoryTool`.

Use the **Step 2 (instance-target)** shape, authorizing after the record is resolved and after its existing not-found branch, for:
`GetPostTool`, `UpdatePostTool`, `RestorePostTool`, `GeneratePreviewUrlTool`, `GetCategoryTool`, `UpdateCategoryTool`, `DeleteCategoryTool`, `RestoreCategoryTool`.

Take the ability and target for each from the mapping table above. `RestorePostTool` and `RestoreCategoryTool` resolve their record with `withTrashed()` — authorize after that lookup, not before.

- [ ] **Step 4: Fix author attribution in `CreatePostTool`**

Replace `'author_id' => $request->user()->id,` with a resolution that runs before `Post::create()`:

```php
        $author = $this->resolveAuthorOrFail($request);

        if ($author instanceof Response) {
            return $author;
        }
```

and in the `Post::create([...])` array use:

```php
            'author_id' => $author->getKey(),
```

- [ ] **Step 5: Verify no tool still references `is_admin`**

Run: `grep -rn "is_admin" src/`
Expected: no output.

- [ ] **Step 6: Run the authorization tests**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorizationTest.php`
Expected: 4 passed.

- [ ] **Step 7: Confirm the whole suite is green**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add src/Mcp/Tools
git commit -m "refactor: authorize MCP tools through the host gate"
```

---

### Task 6: Token ability and non-default guard enforcement

**Files:**
- Test: `tests/Feature/Mcp/AuthorizationTest.php` (append)

**Interfaces:**
- Consumes: the converted tools (Task 5)
- Produces: proof that the Sanctum axis is enforced independently of the Gate, and that a caller on a non-default guard is resolved.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Mcp/AuthorizationTest.php`:

```php
test('a token without the required ability is rejected even when the policy allows', function () {
    PostPolicy::$allow = true;

    $user = testUser();
    $user->withAccessToken(new Laravel\Sanctum\PersonalAccessToken([
        'abilities' => ['posts:read'],
    ]));

    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs($user)
        ->tool(CreatePostTool::class, blogArgs($category))
        ->assertSee('Token missing required ability: posts:create');
});
```

This requires `laravel/sanctum` and the `HasApiTokens` trait on the Testbench user. Add to `composer.json` `require-dev`: `"laravel/sanctum": "^4.0"`, run `composer update laravel/sanctum`, and in `tests/Pest.php` change `testUser()` to return a user class that uses `Laravel\Sanctum\HasApiTokens`. If Testbench's `UserFactory` model cannot be extended cleanly, create `tests/Fixtures/Models/TokenUser.php` extending `Illuminate\Foundation\Auth\User` with `HasApiTokens`, point `ink.author_model` at it in `defineEnvironment`, and have `testUser()` build that.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorizationTest.php --filter="token without"`
Expected: FAIL — the tool succeeds because no ability check bites.

- [ ] **Step 3: Confirm the trait already implements it**

No production change should be needed: `denyUnlessAuthorized()` already calls `tokenCan()`. If the test still fails, the cause is the fixture user lacking `tokenCan`, not the trait. Fix the fixture.

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorizationTest.php`
Expected: 5 passed.

- [ ] **Step 5: Write the failing guard test**

`ink.mcp.guard` is what lets a host authenticate staff on a guard other than the default. Append:

```php
test('a caller on a non-default guard is resolved and authorized', function () {
    PostPolicy::$allow = true;

    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    config()->set('ink.mcp.guard', 'staff');

    BlogServer::actingAs(testUser(), 'staff')
        ->tool(ListPostsTool::class)
        ->assertOk();
});

test('a caller absent from the configured guard is rejected', function () {
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    config()->set('ink.mcp.guard', 'staff');

    // Authenticated on the default guard only — the configured guard sees nobody.
    BlogServer::actingAs(testUser())
        ->tool(ListPostsTool::class)
        ->assertSee('Authentication required.');
});
```

- [ ] **Step 6: Run to verify both guard tests pass**

Run: `vendor/bin/pest tests/Feature/Mcp/AuthorizationTest.php`
Expected: 7 passed. These should pass without production changes — `caller()` already reads `ink.mcp.guard`. If the second test fails because the default guard leaks through, that is a real bug in `caller()`; fix it there, not in the test.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock tests/
git commit -m "test: cover sanctum abilities and non-default guard on blog MCP tools"
```

---

### Task 7: Store markdown, not HTML

**Files:**
- Modify: `src/Mcp/Tools/CreatePostTool.php`, `src/Mcp/Tools/UpdatePostTool.php`
- Test: `tests/Feature/Mcp/ContentStorageTest.php`

**Interfaces:**
- Consumes: the converted tools (Task 5)
- Produces: `content` holds markdown regardless of write path.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mcp/ContentStorageTest.php`:

```php
<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

test('a post created through MCP stores markdown, not rendered HTML', function () {
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(testUser())->tool(CreatePostTool::class, [
        'title' => 'Markdown storage',
        'content' => "## Heading\n\nSome **bold** copy.",
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ])->assertOk();

    $post = Post::query()->where('title', 'Markdown storage')->sole();

    expect($post->content)->toBe("## Heading\n\nSome **bold** copy.")
        ->and($post->content)->not->toContain('<h2>');
});

test('MCP and model write paths render identically', function () {
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);
    $markdown = "## Heading\n\nSome **bold** copy.";

    BlogServer::actingAs(testUser())->tool(CreatePostTool::class, [
        'title' => 'Via MCP',
        'content' => $markdown,
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ])->assertOk();

    $viaMcp = Post::query()->where('title', 'Via MCP')->sole();
    $viaModel = Post::factory()->published()->create(['content' => $markdown]);

    expect($viaMcp->toHtml())->toBe($viaModel->toHtml());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Mcp/ContentStorageTest.php`
Expected: FAIL — stored content contains `<h2>`.

- [ ] **Step 3: Remove the transformation**

In `src/Mcp/Tools/CreatePostTool.php`, delete the line:

```php
        $content = Str::markdown($validated['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
```

and change the `Post::create()` entry from `'content' => $content,` to `'content' => $validated['content'],`. Remove the now-unused `use Illuminate\Support\Str;` import if nothing else in the file uses it.

In `src/Mcp/Tools/UpdatePostTool.php`, replace the conditional `Str::markdown(...)` expression so the validated content is assigned unchanged, and drop the `Str` import if it becomes unused.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Mcp/ContentStorageTest.php`
Expected: 2 passed.

- [ ] **Step 5: Confirm the whole suite is green**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add src/Mcp/Tools/CreatePostTool.php src/Mcp/Tools/UpdatePostTool.php tests/Feature/Mcp/ContentStorageTest.php
git commit -m "fix: store markdown from MCP tools instead of rendered HTML"
```

---

### Task 8: Migrate legacy HTML content

**Files:**
- Create: `database/migrations/2026_08_05_000000_convert_html_post_content_to_markdown.php`
- Test: `tests/Feature/Mcp/HtmlContentMigrationTest.php`

**Interfaces:**
- Consumes: `League\HTMLToMarkdown\HtmlConverter` (Task 1)
- Produces: existing HTML-stored rows become markdown; unconvertible rows are left intact.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mcp/HtmlContentMigrationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Relaticle\Ink\Models\Post;

/**
 * A migration file returns its anonymous class instance. Use `require` (never
 * `require_once` — a second include would return `true` instead of the object).
 */
function runContentMigration(): void
{
    $migration = require __DIR__.'/../../../database/migrations/2026_08_05_000000_convert_html_post_content_to_markdown.php';

    expect($migration)->toBeInstanceOf(Migration::class);

    $migration->up();
}

test('it converts HTML content written by the old MCP tools to markdown', function () {
    $post = Post::factory()->create();

    DB::table('blog_posts')->where('id', $post->id)->update([
        'content' => '<h2>FAQ</h2><p>A form plugin.</p>',
    ]);

    runContentMigration();

    expect($post->fresh()->content)->toContain('## FAQ')
        ->and($post->fresh()->content)->not->toContain('<h2>');
});

test('it leaves markdown content untouched', function () {
    $post = Post::factory()->create(['content' => "## Already markdown\n\nBody."]);

    runContentMigration();

    expect($post->fresh()->content)->toBe("## Already markdown\n\nBody.");
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Mcp/HtmlContentMigrationTest.php`
Expected: FAIL — migration file does not exist.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_05_000000_convert_html_post_content_to_markdown.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use League\HTMLToMarkdown\HtmlConverter;

return new class extends Migration
{
    /**
     * Before 2.x the MCP tools rendered markdown to HTML before storing it, while
     * the Filament editor stored raw markdown. Convert the HTML rows so `content`
     * means one thing. Rows that do not convert cleanly are left alone and
     * reported — silently mangling a published post is worse than reporting it.
     */
    public function up(): void
    {
        $table = config('ink.tables.posts', 'blog_posts');
        $converter = new HtmlConverter(['strip_tags' => false, 'hard_break' => true]);
        $skipped = [];

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $converter, &$skipped): void {
            foreach ($rows as $row) {
                if (! $this->looksLikeHtml((string) $row->content)) {
                    continue;
                }

                try {
                    $markdown = trim($converter->convert((string) $row->content));
                } catch (Throwable) {
                    $skipped[] = $row->id;

                    continue;
                }

                if ($markdown === '') {
                    $skipped[] = $row->id;

                    continue;
                }

                DB::table($table)->where('id', $row->id)->update(['content' => $markdown]);
            }
        });

        if ($skipped !== []) {
            echo 'ink: could not convert content for post ids '.implode(', ', $skipped).' — review manually.'.PHP_EOL;
        }
    }

    private function looksLikeHtml(string $content): bool
    {
        return (bool) preg_match('/^\s*<(p|h[1-6]|ul|ol|pre|blockquote)\b/i', $content);
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Mcp/HtmlContentMigrationTest.php`
Expected: 2 passed.

- [ ] **Step 5: Confirm the whole suite is green**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add database/migrations tests/Feature/Mcp/HtmlContentMigrationTest.php
git commit -m "feat: migrate HTML post content written by the old MCP tools"
```

---

### Task 9: Opt-in route registration

**Files:**
- Create: `routes/mcp.php`
- Modify: `src/InkServiceProvider.php`
- Test: `tests/Feature/Mcp/ServerRegistrationTest.php`

**Interfaces:**
- Consumes: `BlogServer` (Task 4), config keys (Task 3)
- Produces: the MCP endpoint exists only when `ink.features.mcp` is true.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mcp/ServerRegistrationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;

function bootInkWithMcp(bool $enabled): void
{
    config()->set('ink.features.mcp', $enabled);

    test()->app->register(InkServiceProvider::class, force: true);
    test()->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
}

test('the MCP route is not registered by default', function () {
    bootInkWithMcp(false);

    $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

    expect($uris)->not->toContain('mcp/blog');
});

test('the MCP route is registered when the feature is enabled', function () {
    bootInkWithMcp(true);

    $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

    expect($uris)->toContain('mcp/blog');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Mcp/ServerRegistrationTest.php`
Expected: FAIL — the enabled case does not find `mcp/blog`.

- [ ] **Step 3: Write the route file**

Create `routes/mcp.php`:

```php
<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Relaticle\Ink\Mcp\BlogServer;

Mcp::web(config('ink.mcp.path', '/mcp/blog'), BlogServer::class)
    ->middleware(config('ink.mcp.middleware', ['auth:sanctum']));
```

- [ ] **Step 4: Register it conditionally**

In `src/InkServiceProvider::packageBooted()`, directly after the existing `public_routes` branch, add:

```php
        if (config('ink.features.mcp') && class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/mcp.php');
        }
```

The `class_exists` guard matters because `laravel/mcp` is a suggestion, not a hard requirement — a host that enables the flag without installing the package gets no route rather than a fatal error.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Mcp/ServerRegistrationTest.php`
Expected: 2 passed.

- [ ] **Step 6: Confirm the whole suite is green**

Run: `vendor/bin/pest && vendor/bin/pint --test`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add routes/mcp.php src/InkServiceProvider.php tests/Feature/Mcp/ServerRegistrationTest.php
git commit -m "feat: register the blog MCP route behind a feature flag"
```

---

### Task 10: Documentation

**Files:**
- Modify: `docs/content/2.essentials/3.mcp-tools.md`, `README.md`, `UPGRADING.md`

**Interfaces:**
- Consumes: everything above
- Produces: no code.

- [ ] **Step 1: Rewrite the registration section of the MCP docs**

In `docs/content/2.essentials/3.mcp-tools.md`, replace the `## Registration` section — which currently tells hosts to hand-write a server — with the flag-based setup, the config block, the ability mapping table from Task 5, and this authorization note:

> Tools authorize through your application's Gate. Register a policy for
> `Relaticle\Ink\Models\Post` and `Relaticle\Ink\Models\Category`; with no policy
> registered the Gate denies and every tool returns `Permission denied.`
>
> Sanctum token abilities are checked separately, so a token can be scoped more
> narrowly than the identity holding it.

Add an author-attribution section:

```php
// A host whose staff are not `ink.author_model` instances.
Ink::resolveAuthorUsing(fn (SystemAdministrator $admin): ?User =>
    User::firstWhere('email', $admin->email));
```

- [ ] **Step 2: Correct the README claim**

In `README.md`, change the MCP bullet so it no longer says the tools sanitize markdown — they no longer transform content — and state that `laravel/mcp` is required to use them.

- [ ] **Step 3: Write the upgrade notes**

Append to `UPGRADING.md` a `## 2.x` section covering: content written by MCP tools is now markdown and a migration converts existing HTML rows (review anything it reports as skipped); tools authorize via the host Gate and need a policy; `is_admin` is no longer consulted; Laravel 12, Pest 3/4 and Testbench 10 are no longer supported.

- [ ] **Step 4: Commit**

```bash
git add docs README.md UPGRADING.md
git commit -m "docs: document gate-based MCP authorization and 2.x upgrade notes"
```

---

## Verification

- [ ] `vendor/bin/pest` — all tests pass, including the 5 new MCP files
- [ ] `vendor/bin/pint --test` — clean
- [ ] `grep -rn "is_admin" src/` — no output
- [ ] `grep -rn "Str::markdown" src/Mcp/` — no output
- [ ] CI green on `2.x`

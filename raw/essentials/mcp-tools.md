# MCP Tools

> 13 MCP tools for AI agent blog management.

The package includes 13 Model Context Protocol tools for full blog management via AI agents.

## Post Tools

<table>
<thead>
  <tr>
    <th>
      Tool
    </th>
    
    <th>
      Type
    </th>
    
    <th>
      Ability
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        ListPostsTool
      </code>
    </td>
    
    <td>
      Read-only
    </td>
    
    <td>
      <code>
        posts:read
      </code>
    </td>
    
    <td>
      List posts with filters (status, category, search, pagination)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        GetPostTool
      </code>
    </td>
    
    <td>
      Read-only
    </td>
    
    <td>
      <code>
        posts:read
      </code>
    </td>
    
    <td>
      Get post by ID or slug
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        CreatePostTool
      </code>
    </td>
    
    <td>
      Write
    </td>
    
    <td>
      <code>
        posts:create
      </code>
    </td>
    
    <td>
      Create post (markdown content, auto-slug, auto-sanitize)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UpdatePostTool
      </code>
    </td>
    
    <td>
      Idempotent
    </td>
    
    <td>
      <code>
        posts:update
      </code>
    </td>
    
    <td>
      Update post fields (partial updates)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        DeletePostTool
      </code>
    </td>
    
    <td>
      Write
    </td>
    
    <td>
      <code>
        posts:delete
      </code>
    </td>
    
    <td>
      Soft delete a post
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        RestorePostTool
      </code>
    </td>
    
    <td>
      Write
    </td>
    
    <td>
      <code>
        posts:delete
      </code>
    </td>
    
    <td>
      Restore a soft-deleted post
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        GeneratePreviewUrlTool
      </code>
    </td>
    
    <td>
      Read-only
    </td>
    
    <td>
      <code>
        posts:read
      </code>
    </td>
    
    <td>
      Generate 1-hour signed preview URL
    </td>
  </tr>
</tbody>
</table>

## Category Tools

<table>
<thead>
  <tr>
    <th>
      Tool
    </th>
    
    <th>
      Type
    </th>
    
    <th>
      Ability
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        ListCategoriesTool
      </code>
    </td>
    
    <td>
      Read-only
    </td>
    
    <td>
      <code>
        categories:read
      </code>
    </td>
    
    <td>
      List categories with post count
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        GetCategoryTool
      </code>
    </td>
    
    <td>
      Read-only
    </td>
    
    <td>
      <code>
        categories:read
      </code>
    </td>
    
    <td>
      Get category by ID or slug
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        CreateCategoryTool
      </code>
    </td>
    
    <td>
      Write
    </td>
    
    <td>
      <code>
        categories:create
      </code>
    </td>
    
    <td>
      Create category (auto-slug)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UpdateCategoryTool
      </code>
    </td>
    
    <td>
      Idempotent
    </td>
    
    <td>
      <code>
        categories:update
      </code>
    </td>
    
    <td>
      Update category name
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        DeleteCategoryTool
      </code>
    </td>
    
    <td>
      Write
    </td>
    
    <td>
      <code>
        categories:delete
      </code>
    </td>
    
    <td>
      Soft delete a category
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        RestoreCategoryTool
      </code>
    </td>
    
    <td>
      Write
    </td>
    
    <td>
      <code>
        categories:delete
      </code>
    </td>
    
    <td>
      Restore a soft-deleted category
    </td>
  </tr>
</tbody>
</table>

## Setup

Install `laravel/mcp` (a suggestion, not a hard requirement), then turn the feature on:

```php [config/ink.php]
'features' => [
    'mcp' => true,
],

'mcp' => [
    'path' => '/mcp/blog',
    'guard' => null,                    // null uses the default guard
    'middleware' => ['auth:sanctum'],
],
```

That registers `BlogServer` — all 13 tools — at the configured path. Nothing is exposed
until you opt in, and enabling the flag without `laravel/mcp` installed simply yields no
route rather than an error.

Prefer to route it yourself? Leave the flag off and register the shipped server:

```php [routes/ai.php]
use Relaticle\Ink\Mcp\BlogServer;

Mcp::web('/mcp/blog', BlogServer::class)->middleware('auth:sanctum');
```

## Authorization

Tools authorize through **your application's Gate**. Register a policy for
`Relaticle\Ink\Models\Post` and `Relaticle\Ink\Models\Category`:

```php
Gate::policy(Post::class, PostPolicy::class);
Gate::policy(Category::class, CategoryPolicy::class);
```

With no policy registered the Gate denies and every tool returns
`This action is unauthorized.` — the tools fail closed.

Each tool maps to one Gate ability and one Sanctum token ability. The two are separate
axes: the Gate decides what an **identity** may do, the token ability what a
**credential** may do, so a token can be scoped more narrowly than the person holding it.

<table>
<thead>
  <tr>
    <th>
      Tool
    </th>
    
    <th>
      Gate ability
    </th>
    
    <th>
      Token ability
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        ListPostsTool
      </code>
    </td>
    
    <td>
      <code>
        viewAny
      </code>
    </td>
    
    <td>
      <code>
        posts:read
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        GetPostTool
      </code>
    </td>
    
    <td>
      <code>
        view
      </code>
    </td>
    
    <td>
      <code>
        posts:read
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        GeneratePreviewUrlTool
      </code>
    </td>
    
    <td>
      <code>
        view
      </code>
    </td>
    
    <td>
      <code>
        posts:read
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        CreatePostTool
      </code>
    </td>
    
    <td>
      <code>
        create
      </code>
    </td>
    
    <td>
      <code>
        posts:create
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UpdatePostTool
      </code>
    </td>
    
    <td>
      <code>
        update
      </code>
    </td>
    
    <td>
      <code>
        posts:update
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        DeletePostTool
      </code>
    </td>
    
    <td>
      <code>
        delete
      </code>
    </td>
    
    <td>
      <code>
        posts:delete
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        RestorePostTool
      </code>
    </td>
    
    <td>
      <code>
        restore
      </code>
    </td>
    
    <td>
      <code>
        posts:delete
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ListCategoriesTool
      </code>
    </td>
    
    <td>
      <code>
        viewAny
      </code>
    </td>
    
    <td>
      <code>
        categories:read
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        GetCategoryTool
      </code>
    </td>
    
    <td>
      <code>
        view
      </code>
    </td>
    
    <td>
      <code>
        categories:read
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        CreateCategoryTool
      </code>
    </td>
    
    <td>
      <code>
        create
      </code>
    </td>
    
    <td>
      <code>
        categories:create
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        UpdateCategoryTool
      </code>
    </td>
    
    <td>
      <code>
        update
      </code>
    </td>
    
    <td>
      <code>
        categories:update
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        DeleteCategoryTool
      </code>
    </td>
    
    <td>
      <code>
        delete
      </code>
    </td>
    
    <td>
      <code>
        categories:delete
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        RestoreCategoryTool
      </code>
    </td>
    
    <td>
      <code>
        restore
      </code>
    </td>
    
    <td>
      <code>
        categories:delete
      </code>
    </td>
  </tr>
</tbody>
</table>

Instance-target tools resolve the record before authorizing, so a denial never doubles as
an existence check.

### Callers on another guard

If your staff authenticate on a guard other than the default — a separate admin model, for
instance — point `ink.mcp.guard` at it. `BlogTool` resolves the caller from that guard.

## Author attribution

`CreatePostTool` writes `author_id`, which is `NOT NULL`. By default the caller is used
when it is already an `ink.author_model` instance. When it is not, supply the mapping in a
service provider's `boot()`:

```php
use Relaticle\Ink\Ink;

Ink::resolveAuthorUsing(fn (SystemAdministrator $admin): ?User =>
    User::firstWhere('email', $admin->email));
```

Register it in a provider, never in config — a closure in a config file breaks
`config:cache`. If no author resolves, the tool reports the misconfiguration instead of
guessing.

## Content format

The tools store the **markdown** you send, exactly as the Filament editor does; they do not
convert it to HTML. Rendering — and therefore HTML sanitisation — happens at read time
through your application's markdown configuration.

<caution>

Posts created by ink 2.1 and earlier hold rendered HTML. The
`convert_html_post_content_to_markdown` migration converts them and prints the ids of any
row it could not convert cleanly, so you can review those by hand.

</caution>

## Extending

Custom blog tools should extend `Relaticle\Ink\Mcp\BlogTool` rather than
`Laravel\Mcp\Server\Tool`. Its `handle()` is `final` and performs authorization before
delegating, so a new tool cannot ship without it. Declare `ability()`, `tokenAbility()`,
`model()` and `run()`; override `resolveRecord()` when the tool operates on one record.

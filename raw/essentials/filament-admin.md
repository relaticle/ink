---
title: "Filament Admin"
description: "Managing blog posts, categories, and tags in the Filament admin panel."
canonical_url: "https://relaticle.github.io/essentials/filament-admin"
---
# Filament Admin

> Managing blog posts, categories, and tags in the Filament admin panel.

## Posts Resource

The PostResource provides a full CRUD interface for blog posts.

### Form Fields

<table>
<thead>
  <tr>
    <th>
      Field
    </th>
    
    <th>
      Type
    </th>
    
    <th>
      Notes
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      Title
    </td>
    
    <td>
      TextInput
    </td>
    
    <td>
      Required, max 255
    </td>
  </tr>
  
  <tr>
    <td>
      Slug
    </td>
    
    <td>
      TextInput
    </td>
    
    <td>
      Auto-generated, unique, frozen on rename
    </td>
  </tr>
  
  <tr>
    <td>
      Content
    </td>
    
    <td>
      MarkdownEditor
    </td>
    
    <td>
      Required, full toolbar
    </td>
  </tr>
  
  <tr>
    <td>
      Excerpt
    </td>
    
    <td>
      Textarea
    </td>
    
    <td>
      3 rows
    </td>
  </tr>
  
  <tr>
    <td>
      Status
    </td>
    
    <td>
      Toggle
    </td>
    
    <td>
      Hydrates to/from the `Draft
    </td>
  </tr>
  
  <tr>
    <td>
      Published At
    </td>
    
    <td>
      DateTimePicker
    </td>
    
    <td>
      Future value = scheduled
    </td>
  </tr>
  
  <tr>
    <td>
      Category
    </td>
    
    <td>
      Select
    </td>
    
    <td>
      Searchable, create inline
    </td>
  </tr>
  
  <tr>
    <td>
      Tags
    </td>
    
    <td>
      Select
    </td>
    
    <td>
      Multi, searchable, create inline — only visible when <code>
        features.tags
      </code>
      
       is on
    </td>
  </tr>
  
  <tr>
    <td>
      Author
    </td>
    
    <td>
      Select
    </td>
    
    <td>
      Defaults to current user
    </td>
  </tr>
  
  <tr>
    <td>
      Featured Image
    </td>
    
    <td>
      FileUpload
    </td>
    
    <td>
      Image, public disk — auto-swaps to <code>
        SpatieMediaLibraryFileUpload
      </code>
      
       when <code>
        features.media_library
      </code>
      
       is on AND the package is installed
    </td>
  </tr>
  
  <tr>
    <td>
      SEO
    </td>
    
    <td>
      SEO Component
    </td>
    
    <td>
      Title + description (collapsible)
    </td>
  </tr>
</tbody>
</table>

### List Tabs

Posts are organized into tabs:

<table>
<thead>
  <tr>
    <th>
      Tab
    </th>
    
    <th>
      Filter
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <strong>
        Drafts
      </strong>
    </td>
    
    <td>
      <code>
        status = draft
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Scheduled
      </strong>
    </td>
    
    <td>
      <code>
        status = published
      </code>
      
       AND <code>
        published_at > now
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Published
      </strong>
    </td>
    
    <td>
      <code>
        status = published
      </code>
      
       AND <code>
        published_at <= now
      </code>
    </td>
  </tr>
</tbody>
</table>

### Per-row actions

- **View** — opens the post URL (published) or signed preview URL (draft) in a new tab
- **Edit** — edit form
- **Delete / Force Delete / Restore** — soft delete support

### Bulk actions

<table>
<thead>
  <tr>
    <th>
      Action
    </th>
    
    <th>
      Effect
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <strong>
        Publish
      </strong>
    </td>
    
    <td>
      Sets <code>
        status = Published
      </code>
      
       and <code>
        published_at
      </code>
      
       to now (or keeps existing if already set)
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Unpublish
      </strong>
    </td>
    
    <td>
      Sets <code>
        status = Draft
      </code>
      
       and clears <code>
        published_at
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Schedule
      </strong>
    </td>
    
    <td>
      Modal with <code>
        DateTimePicker
      </code>
      
       (min: now); sets <code>
        status = Published
      </code>
      
       + that timestamp
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Delete / Force Delete / Restore
      </strong>
    </td>
    
    <td>
      Standard soft-delete bulk operations
    </td>
  </tr>
</tbody>
</table>

All bulk actions notify on completion and deselect rows.

## Categories Resource

Simple CRUD for blog categories: `name`, `slug` (auto-generated, unique), `posts_count` column, soft-delete support, trashed filter.

## Tags Resource (opt-in)

Appears in the Blog navigation group when `features.tags` is enabled. Mirrors the Categories resource shape: `name`, `slug` (auto-generated, unique, frozen on rename), `posts_count` column, soft-delete support, trashed filter.

When the flag is off, the resource class still exists but `shouldRegisterNavigation()` returns `false` so it doesn't appear in the sidebar.

See the [Tags Taxonomy](https://relaticle.github.io/essentials/tags) page for full schema and usage.

## Plugin Registration

```php [AppPanelProvider.php]
use Relaticle\Ink\InkPlugin;

$panel->plugins([
    InkPlugin::make(),
]);
```

The plugin auto-discovers `PostResource`, `CategoryResource`, and `TagResource` under the "Blog" navigation group. Resources hidden by feature flags don't appear in the sidebar — they're still resolvable for tests and direct URL access.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.

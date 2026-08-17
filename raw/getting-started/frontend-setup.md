# Frontend Setup (headless)

> Build your own blog frontend using the package's Blade components.

<alert type="info">

**Want a working blog without writing controllers?** See [Public-routes mode](/getting-started/public-routes-mode) — flip a config flag and you're done. This page covers the **headless mode** for hosts who want full control.

</alert>

In headless mode the package ships **no routes, no controllers, no page views**. You wire your own routing and use the provided Blade components.

## Create routes

```php [routes/web.php]
use App\Http\Controllers\BlogController;

Route::prefix('ink')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/feed', [BlogController::class, 'feed'])->name('feed');
    Route::get('/category/{slug}', [BlogController::class, 'category'])->name('category');
    Route::get('/tag/{slug}', [BlogController::class, 'tag'])->name('tag');
    Route::get('/preview/{post}', [BlogController::class, 'preview'])
        ->name('preview')->middleware('signed');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('show');
});
```

The route names matter — the package's URL helpers and SEO components check for them via `Route::has(...)` and fall back gracefully when missing.

## Create controller

```php [app/Http/Controllers/BlogController.php]
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

final readonly class BlogController
{
    public function index(): View
    {
        $posts = Post::query()
            ->published()
            ->with(['category', 'author', 'seo'])
            ->latest('published_at')
            ->paginate(config('ink.per_page', 12));

        return view('blog.index', compact('posts'));
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->with(['category', 'author', 'seo'])
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedPosts = $post->relatedPosts()->get();

        return view('blog.show', compact('post', 'relatedPosts'));
    }

    public function category(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $posts = Post::query()
            ->where('category_id', $category->id)
            ->published()
            ->with(['category', 'author', 'seo'])
            ->latest('published_at')
            ->paginate(config('ink.per_page', 12));

        return view('blog.category', compact('category', 'posts'));
    }

    public function tag(string $slug): View
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();
        $posts = Post::query()
            ->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $tag->id))
            ->published()
            ->with(['category', 'author', 'seo'])
            ->latest('published_at')
            ->paginate(config('ink.per_page', 12));

        return view('blog.tag', compact('tag', 'posts'));
    }

    public function preview(Post $post): View
    {
        return view('blog.preview', ['post' => $post->loadMissing(['category', 'author', 'seo'])]);
    }

    public function feed(): Response
    {
        $posts = Post::query()
            ->published()
            ->with(['category', 'author'])
            ->latest('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('blog.feed', compact('posts'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
```

## Create views

Use the package's Blade components inside your own page templates:

```blade [resources/views/blog/show.blade.php]
<x-your-layout>
    @push('head')
        <x-ink::meta-tags :post="$post" />
        <x-ink::feed-link />
    @endpush

    <x-ink::structured-data :post="$post" />

    <x-ink::post-header :post="$post" />
    <x-ink::post-body :post="$post" />
    <x-ink::related-posts :posts="$relatedPosts" />
</x-your-layout>
```

```blade [resources/views/blog/index.blade.php]
<x-your-layout>
    @foreach($posts as $post)
        <x-ink::post-card :post="$post" />
    @endforeach

    {{ $posts->links() }}
</x-your-layout>
```

```blade [resources/views/blog/feed.blade.php]
<x-ink::feed :posts="$posts" />
```

## Helpers on the Post model

Useful in your views:

```php
$post->readingTime();       // int — minutes
$post->relatedPosts(3);     // Builder of same-category, published, !=$this->id
$post->getUrl();            // route('blog.show', $post->slug) if registered, else fallback
$post->tableOfContents();   // array<string, string> — fragment => heading text, from <h2>
$post->renderedContent();   // string — rendered through your app's `markdown` config
$post->toSafeHtml();        // string — rendered with ink's hardened options
```

### The two render paths

Both renderers add `loading="lazy" decoding="async"` to every image (leaving an attribute
the author declared by hand alone), and both take markdown in and give HTML out — but they
are **not** equivalent, and the choice is a security decision:

<table>
<thead>
  <tr>
    <th>
      
    </th>
    
    <th>
      <code>
        toSafeHtml()
      </code>
    </th>
    
    <th>
      <code>
        toHtml()
      </code>
      
       / <code>
        renderedContent()
      </code>
      
       / <code>
        <x-ink::post-body>
      </code>
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      Raw HTML in content
    </td>
    
    <td>
      stripped
    </td>
    
    <td>
      governed by your <code>
        markdown.commonmark_options.html_input
      </code>
      
       (CommonMark defaults to <code>
        allow
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        javascript:
      </code>
      
       links
    </td>
    
    <td>
      disarmed
    </td>
    
    <td>
      governed by <code>
        allow_unsafe_links
      </code>
      
       (defaults to <code>
        true
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      GFM tables, strikethrough, task lists, autolinks
    </td>
    
    <td>
      yes
    </td>
    
    <td>
      only if you add the extension to <code>
        markdown.extensions
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      Heading permalink anchors, code highlighting
    </td>
    
    <td>
      no
    </td>
    
    <td>
      per your <code>
        markdown
      </code>
      
       config
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        tableOfContents()
      </code>
      
       compatible
    </td>
    
    <td>
      no
    </td>
    
    <td>
      yes
    </td>
  </tr>
  
  <tr>
    <td>
      Cached
    </td>
    
    <td>
      no
    </td>
    
    <td>
      <code>
        post-rendered:{id}
      </code>
      
      , forever
    </td>
  </tr>
</tbody>
</table>

`<x-ink::post-body>` and everything else built on `toHtml()` deliberately defer to your
app's spatie/laravel-markdown configuration — that's the documented extension point, and
it's what makes anchors, code highlighting and your own CommonMark extensions work. The
flip side is that it also defers on **sanitisation**: with a stock `config/markdown.php`,
raw HTML in a post body is rendered, not stripped. If anyone who can author a post is not
fully trusted, either pin `html_input` and `allow_unsafe_links` in
`markdown.commonmark_options`, or render with `$post->toSafeHtml()` instead — which is what
the package's own `ink::pages.show` / `ink::pages.preview` views do.

`tableOfContents(string $tag = 'h2')` parses the **rendered** post (`$post->toHtml()`), not
the markdown source, so a heading's full text is captured even when it contains inline
markup (bold, code, links) — `## Using \`artisan` commands`becomes`Using artisan
commands`, not `Using` — and HTML entities decode exactly once. Each key is the fragment
used by the heading's permalink anchor; each value is the heading's text:

```blade [resources/views/blog/show.blade.php]
@if ($toc = $post->tableOfContents())
    <nav aria-label="Table of contents">
        <ul>
            @foreach ($toc as $fragment => $heading)
                <li><a href="#{{ $fragment }}">{{ $heading }}</a></li>
            @endforeach
        </ul>
    </nav>
@endif
```

Pass `tag: 'h3'` (or any heading level your content uses) to build the TOC from a different
level. A post with no matching headings returns an empty array.

## Expected route names

The package checks for these names when generating URLs. If a route is missing, the helper returns `#`:

<table>
<thead>
  <tr>
    <th>
      Name
    </th>
    
    <th>
      Purpose
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        blog.index
      </code>
    </td>
    
    <td>
      Listing page
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        blog.show
      </code>
    </td>
    
    <td>
      Single post (<code>
        slug
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        blog.category
      </code>
    </td>
    
    <td>
      Category archive (<code>
        slug
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        blog.tag
      </code>
    </td>
    
    <td>
      Tag archive (<code>
        slug
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        blog.preview
      </code>
    </td>
    
    <td>
      Signed draft preview (<code>
        post
      </code>
      
      )
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        blog.feed
      </code>
    </td>
    
    <td>
      RSS feed
    </td>
  </tr>
</tbody>
</table>

## Promote to public-routes mode any time

If writing all this gets old, flip the flag in config and delete your controller — the package will register equivalent routes automatically. See [Public-routes mode](/getting-started/public-routes-mode).

<?php

declare(strict_types=1);

namespace Relaticle\Ink\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;
use Relaticle\Ink\Support\BlogListingSeo;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = (int) config('ink.per_page', 12);

        $searchQuery = trim((string) $request->query('q', ''));

        $query = Post::query()
            ->with(['category', 'author', 'seo'])
            ->published();

        if ($searchQuery !== '') {
            $query->search($searchQuery);
        }

        $posts = $query
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        seo()->for(BlogListingSeo::forIndex(
            page: (int) $request->query('page', 1),
            searchQuery: $request->query('q'),
        ));

        return view($this->viewFor('index'), [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->with(['category', 'author', 'seo'])
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $relatedPosts = $post->relatedPosts(limit: 3)->get();

        seo()->for($post);

        return view($this->viewFor('show'), [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $perPage = (int) config('ink.per_page', 12);

        $posts = Post::query()
            ->with(['category', 'author', 'seo'])
            ->where('category_id', $category->id)
            ->published()
            ->latest('published_at')
            ->paginate($perPage);

        seo()->for(BlogListingSeo::forCategory(
            category: $category,
            page: (int) request()->query('page', 1),
        ));

        return view($this->viewFor('category'), [
            'category' => $category,
            'posts' => $posts,
        ]);
    }

    public function preview(Post $post): View
    {
        $post->loadMissing(['category', 'author', 'seo']);

        seo()->for($post);

        return view($this->viewFor('preview'), [
            'post' => $post,
        ]);
    }

    public function tag(string $slug): View
    {
        abort_unless(config('ink.features.tags', false), 404);

        $tag = Tag::where('slug', $slug)->firstOrFail();
        $perPage = (int) config('ink.per_page', 12);

        $posts = Post::query()
            ->with(['category', 'author', 'seo'])
            ->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $tag->id))
            ->published()
            ->latest('published_at')
            ->paginate($perPage);

        seo()->for(BlogListingSeo::forTag(
            tag: $tag,
            page: (int) request()->query('page', 1),
        ));

        return view($this->viewFor('tag'), [
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }

    public function feed(): Response
    {
        abort_unless(config('ink.features.feed', false), 404);

        $posts = Post::query()
            ->with(['author', 'seo'])
            ->published()
            ->latest('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view($this->viewFor('feed'), ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function viewFor(string $key): string
    {
        $view = config("ink.views.{$key}");

        return is_string($view) && $view !== '' ? $view : "ink::pages.{$key}";
    }
}

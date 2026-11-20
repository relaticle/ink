<?php

declare(strict_types=1);

namespace Relaticle\Ink\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Relaticle\Ink\Ink;
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

        $locale = Ink::requestLocale();

        $query = Post::query()
            ->with(['category', 'author', 'seo'])
            ->published()
            ->inLocale($locale);

        if ($searchQuery !== '') {
            $query->search($searchQuery);
        }

        $posts = $query
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        seo()->for(BlogListingSeo::forIndex(
            page: $posts->currentPage(),
            searchQuery: $request->query('q'),
            locale: $locale,
        ));

        return $this->archive('index', $posts);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->with(['category', 'author', 'seo', 'tags'])
            ->where('slug', $slug)
            ->inLocale(Ink::requestLocale())
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
        $locale = Ink::requestLocale();

        $posts = Post::query()
            ->with(['category', 'author', 'seo'])
            ->where('category_id', $category->id)
            ->published()
            ->inLocale($locale)
            ->latest('published_at')
            ->paginate($perPage);

        seo()->for(BlogListingSeo::forCategory(
            category: $category,
            page: $posts->currentPage(),
            locale: $locale,
        ));

        return $this->archive('category', $posts, ['category' => $category]);
    }

    public function preview(Post $post): View
    {
        $post->loadMissing(['category', 'author', 'seo', 'tags']);

        $relatedPosts = $post->relatedPosts(limit: 3)->get();

        seo()->for($post);

        return view($this->viewFor('preview'), [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'editUrl' => Ink::resolvePreviewEditUrl($post),
        ]);
    }

    public function tag(string $slug): View
    {
        abort_unless(config('ink.features.tags', false), 404);

        $tag = Tag::where('slug', $slug)->firstOrFail();
        $perPage = (int) config('ink.per_page', 12);
        $locale = Ink::requestLocale();

        $posts = Post::query()
            ->with(['category', 'author', 'seo'])
            ->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $tag->id))
            ->published()
            ->inLocale($locale)
            ->latest('published_at')
            ->paginate($perPage);

        seo()->for(BlogListingSeo::forTag(
            tag: $tag,
            page: $posts->currentPage(),
            locale: $locale,
        ));

        return $this->archive('tag', $posts, ['tag' => $tag]);
    }

    public function feed(): Response
    {
        $locale = Ink::requestLocale();

        $posts = Post::query()
            ->with(['category', 'author', 'seo'])
            ->published()
            ->inLocale($locale)
            ->latest('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view($this->viewFor('feed'), ['posts' => $posts, 'locale' => $locale])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function archive(string $key, LengthAwarePaginator $posts, array $data = []): View
    {
        $view = view($this->viewFor($key), ['posts' => $posts, ...$data]);

        if ($posts->currentPage() > $posts->lastPage()) {
            abort(response($view, Response::HTTP_NOT_FOUND));
        }

        return $view;
    }

    private function viewFor(string $key): string
    {
        $view = config("ink.views.{$key}");

        return is_string($view) && $view !== '' ? $view : "ink::pages.{$key}";
    }
}

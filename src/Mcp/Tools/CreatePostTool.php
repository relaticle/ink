<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Relaticle\Ink\Enums\PostStatus;
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Post;

#[Description('Create a new blog post. Slug is auto-generated from title. Set status to "published" and published_at to publish immediately.')]
class CreatePostTool extends BlogTool
{
    protected function ability(): string
    {
        return 'create';
    }

    protected function tokenAbility(): string
    {
        return 'posts:create';
    }

    protected function model(): string
    {
        return Post::class;
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $this->uniqueSlugRule()],
            'content' => ['required', 'string'],
            'excerpt' => ['required', 'string', 'max:500'],
            'featured_image' => ['nullable', 'string', $this->featuredImagePathRule()],
            'category_id' => ['required', 'integer', 'exists:blog_categories,id'],
            'status' => ['nullable', 'string', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            ...$this->tagsRules(),
        ], [
            'title.required' => 'A title is required to create a post.',
            'content.required' => 'Content is required to create a post.',
            'category_id.exists' => 'The specified category does not exist. Use list-categories to see available categories.',
            'status.enum' => 'Status must be either "draft" or "published".',
        ]);

        $author = $this->resolveAuthorOrFail($request);

        if ($author instanceof Response) {
            return $author;
        }

        $post = Post::create([
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? null,
            'content' => $validated['content'],
            'excerpt' => $validated['excerpt'],
            'featured_image' => $validated['featured_image'] ?? null,
            'category_id' => $validated['category_id'],
            'author_id' => $author->getKey(),
            'status' => PostStatus::from($validated['status'] ?? PostStatus::Draft->value),
            'published_at' => isset($validated['published_at']) ? Carbon::parse($validated['published_at']) : null,
        ]);

        if (($validated['seo_title'] ?? null) || ($validated['seo_description'] ?? null)) {
            $post->seo->update(array_filter([
                'title' => $validated['seo_title'] ?? null,
                'description' => $validated['seo_description'] ?? null,
            ]));
        }

        if (array_key_exists('tags', $validated) && is_array($validated['tags'])) {
            $this->syncTagsFromNames($post, array_values($validated['tags']));
        }

        $post->load(['category', 'tags']);

        return Response::structured([
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status->value,
            'category' => $post->category?->name,
            'featured_image' => $post->featured_image,
            'tags' => $post->tags->pluck('name')->all(),
            'seo_title' => $post->seo->title,
            'seo_description' => $post->seo->description,
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at->toIso8601String(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('The post title.')->required(),
            'slug' => $schema->string()->description('URL slug. Generated from the title when omitted. Must be unique among live posts; a slug held only by a deleted post is reusable.'),
            'content' => $schema->string()->description('The post content in Markdown. Converted to HTML on save.')->required(),
            'excerpt' => $schema->string()->description('Short excerpt/summary of the post.')->required(),
            'featured_image' => $schema->string()->description('Featured image path, as returned by upload-image (e.g. "ink/xxx.webp").'),
            'category_id' => $schema->integer()->description('Category ID. Use list-categories tool to find IDs.')->required(),
            'status' => $schema->string()->enum(array_column(PostStatus::cases(), 'value'))->description('Post status. Defaults to draft.')->default(PostStatus::Draft->value),
            'published_at' => $schema->string()->description('ISO 8601 publish date. Required when status is published.'),
            'seo_title' => $schema->string()->description('Custom SEO meta title (max 60 chars). Falls back to post title if not set.'),
            'seo_description' => $schema->string()->description('Custom SEO meta description (max 160 chars). Falls back to excerpt if not set.'),
            'tags' => $schema->array()->items($schema->string())->description('Tag names. Existing tags are matched case-insensitively; unknown names are created. Requires the tags feature.'),
        ];
    }
}

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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Relaticle\Ink\Enums\PostStatus;
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Post;

#[Description('Update an existing blog post by ID. Only provided fields are updated.')]
#[IsIdempotent]
class UpdatePostTool extends BlogTool
{
    protected function ability(): string
    {
        return 'update';
    }

    protected function tokenAbility(): string
    {
        return 'posts:update';
    }

    protected function model(): string
    {
        return Post::class;
    }

    protected function resolveRecord(Request $request): Model|Response|null
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'You must provide the post ID to update.',
        ]);

        $post = Post::find($validated['id']);

        return $post ?? Response::error('Post not found.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        /** @var Post $post */
        $post = $record;

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'featured_image' => ['nullable', 'string', $this->featuredImagePathRule()],
            'category_id' => ['sometimes', 'integer', 'exists:blog_categories,id'],
            'status' => ['nullable', 'string', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ], [
            'id.required' => 'You must provide the post ID to update.',
        ]);

        $content = isset($validated['content'])
            ? $validated['content']
            : null;

        $data = array_filter([
            'title' => $validated['title'] ?? null,
            'content' => $content,
            'excerpt' => $validated['excerpt'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
        ], fn ($value) => $value !== null);

        if ($request->has('featured_image')) {
            $data['featured_image'] = $validated['featured_image'] ?? null;
        }

        if (isset($validated['status'])) {
            $data['status'] = PostStatus::from($validated['status']);
        }

        if (isset($validated['published_at'])) {
            $data['published_at'] = Carbon::parse($validated['published_at']);
        }

        $post->update($data);

        if (isset($validated['seo_title']) || isset($validated['seo_description'])) {
            $seoData = [];

            if (isset($validated['seo_title'])) {
                $seoData['title'] = $validated['seo_title'];
            }

            if (isset($validated['seo_description'])) {
                $seoData['description'] = $validated['seo_description'];
            }

            $post->seo->update($seoData);
        }

        $post->load('category');

        return Response::structured([
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status->value,
            'category' => $post->category?->name,
            'featured_image' => $post->featured_image,
            'seo_title' => $post->seo->title,
            'seo_description' => $post->seo->description,
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at->toIso8601String(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The post ID to update.')->required(),
            'title' => $schema->string()->description('New title.'),
            'content' => $schema->string()->description('New content in Markdown. Converted to HTML on save.'),
            'excerpt' => $schema->string()->description('New excerpt.'),
            'featured_image' => $schema->string()->description('New featured image path, as returned by upload-image (e.g. "ink/xxx.webp"). Pass null to clear it.'),
            'category_id' => $schema->integer()->description('New category ID.'),
            'status' => $schema->string()->enum(array_column(PostStatus::cases(), 'value'))->description('New status.'),
            'published_at' => $schema->string()->description('New publish date (ISO 8601).'),
            'seo_title' => $schema->string()->description('Custom SEO meta title (max 60 chars).'),
            'seo_description' => $schema->string()->description('Custom SEO meta description (max 160 chars).'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Post;

#[Description('Get a single blog post by ID or slug. Returns full post details including content.')]
#[IsReadOnly]
class GetPostTool extends BlogTool
{
    protected function ability(): string
    {
        return 'view';
    }

    protected function tokenAbility(): string
    {
        return 'posts:read';
    }

    protected function model(): string
    {
        return Post::class;
    }

    protected function resolveRecord(Request $request): Model|Response|null
    {
        $post = null;

        if ($id = $request->get('id')) {
            $post = Post::with(['category', 'seo'])->find($id);
        } elseif ($slug = $request->get('slug')) {
            $post = Post::with(['category', 'seo'])->where('slug', $slug)->first();
        }

        return $post ?? Response::error('Post not found. Provide a valid id or slug.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        /** @var Post $post */
        $post = $record;

        return Response::structured([
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'featured_image' => $post->featured_image,
            'status' => $post->status->value,
            'category_id' => $post->category_id,
            'category' => $post->category?->name,
            'author_id' => $post->author_id,
            'seo_title' => $post->seo->title,
            'seo_description' => $post->seo->description,
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at->toIso8601String(),
            'updated_at' => $post->updated_at->toIso8601String(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The post ID. Provide either id or slug.'),
            'slug' => $schema->string()
                ->description('The post slug. Provide either id or slug.'),
        ];
    }
}

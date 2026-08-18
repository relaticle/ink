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
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

#[Description('List all blog tags with their post counts. Check this before tagging a post so existing tags are reused instead of creating near-duplicates.')]
class ListTagsTool extends BlogTool
{
    /**
     * Tags are post metadata: readable by anyone who may list posts. Gating on
     * the Post policy keeps existing hosts working without registering a new
     * Tag policy.
     */
    protected function ability(): string
    {
        return 'viewAny';
    }

    protected function tokenAbility(): string
    {
        return 'posts:read';
    }

    protected function model(): string
    {
        return Post::class;
    }

    public function shouldRegister(): bool
    {
        return (bool) config('ink.features.tags');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        $tags = Tag::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        if ($tags->isEmpty()) {
            return Response::text('No tags exist yet.');
        }

        return Response::structured([
            'data' => $tags->map(fn (Tag $tag): array => [
                'id' => $tag->getKey(),
                'name' => $tag->name,
                'slug' => $tag->slug,
                'posts_count' => $tag->posts_count,
            ])->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

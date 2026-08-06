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

#[Description('Restore a previously soft-deleted blog post by ID.')]
class RestorePostTool extends BlogTool
{
    protected function ability(): string
    {
        return 'restore';
    }

    protected function tokenAbility(): string
    {
        return 'posts:delete';
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
            'id.required' => 'You must provide the post ID to restore.',
        ]);

        $post = Post::withTrashed()->find($validated['id']);

        return $post ?? Response::error('Post not found.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        /** @var Post $post */
        $post = $record;

        if (! $post->trashed()) {
            return Response::error('Post is not deleted. Nothing to restore.');
        }

        $post->restore();

        return Response::structured([
            'id' => $post->id,
            'title' => $post->title,
            'restored' => true,
            'message' => "Post '{$post->title}' has been restored.",
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The ID of the trashed post to restore.')->required(),
        ];
    }
}

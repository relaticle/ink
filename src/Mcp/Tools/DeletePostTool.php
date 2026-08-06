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

#[Description('Soft delete a blog post by ID. The post can be restored later. This does NOT permanently delete.')]
class DeletePostTool extends BlogTool
{
    protected function ability(): string
    {
        return 'delete';
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
            'id.required' => 'You must provide the post ID to delete.',
        ]);

        $post = Post::find($validated['id']);

        return $post ?? Response::error('Post not found.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        /** @var Post $post */
        $post = $record;

        $post->delete();

        return Response::structured([
            'id' => $post->id,
            'title' => $post->title,
            'deleted' => true,
            'message' => "Post '{$post->title}' has been soft deleted. Use restore-post to undo.",
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The post ID to soft delete.')->required(),
        ];
    }
}

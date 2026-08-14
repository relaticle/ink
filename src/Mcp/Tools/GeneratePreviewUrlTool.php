<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Post;

#[Description('Generate a temporary signed preview URL for a blog post. The URL expires in 1 hour and can be opened in a browser to visually verify the post rendering.')]
#[IsReadOnly]
class GeneratePreviewUrlTool extends BlogTool
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
        return Post::find($request->get('id')) ?? Response::error('Post not found.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        // Defensive fallback: InkServiceProvider registers blog.preview whenever either
        // public_routes or mcp is enabled, so this should never trip in a correctly
        // booted app. It exists so a misconfigured host gets an actionable error instead
        // of a masked "An internal server error occurred." from an uncaught
        // RouteNotFoundException.
        if (! Route::has('blog.preview')) {
            return Response::error('The preview route is not registered — enable ink.features.public_routes or ink.features.mcp.');
        }

        return Response::text(
            URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $record])
        );
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The post ID to generate a preview URL for.')
                ->required(),
        ];
    }
}

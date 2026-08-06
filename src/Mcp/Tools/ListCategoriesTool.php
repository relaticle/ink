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
use Relaticle\Ink\Models\Category;

#[Description('List all blog categories with their post counts.')]
#[IsReadOnly]
class ListCategoriesTool extends BlogTool
{
    protected function ability(): string
    {
        return 'viewAny';
    }

    protected function tokenAbility(): string
    {
        return 'categories:read';
    }

    protected function model(): string
    {
        return Category::class;
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {

        $perPage = min((int) ($request->get('per_page') ?? 20), 50);
        $page = max((int) ($request->get('page') ?? 1), 1);

        $paginator = Category::withCount('posts')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($paginator->isEmpty()) {
            return Response::text('No categories found.');
        }

        return Response::structured([
            'data' => $paginator->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'posts_count' => $category->posts_count,
            ])->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->description('Page number. Defaults to 1.'),
            'per_page' => $schema->integer()
                ->description('Results per page (1-50). Defaults to 20.'),
        ];
    }
}

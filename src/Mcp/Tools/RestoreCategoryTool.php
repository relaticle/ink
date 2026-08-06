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
use Relaticle\Ink\Models\Category;

#[Description('Restore a previously soft-deleted blog category by ID.')]
class RestoreCategoryTool extends BlogTool
{
    protected function ability(): string
    {
        return 'restore';
    }

    protected function tokenAbility(): string
    {
        return 'categories:delete';
    }

    protected function model(): string
    {
        return Category::class;
    }

    protected function resolveRecord(Request $request): Model|Response|null
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'You must provide the category ID to restore.',
        ]);

        $category = Category::withTrashed()->find($validated['id']);

        return $category ?? Response::error('Category not found.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        /** @var Category $category */
        $category = $record;

        if (! $category->trashed()) {
            return Response::error('Category is not deleted. Nothing to restore.');
        }

        $category->restore();

        return Response::structured([
            'id' => $category->id,
            'name' => $category->name,
            'restored' => true,
            'message' => "Category '{$category->name}' has been restored.",
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The ID of the trashed category to restore.')->required(),
        ];
    }
}

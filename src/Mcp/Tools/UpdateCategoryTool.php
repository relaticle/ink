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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Category;

#[Description('Update a blog category name by ID.')]
#[IsIdempotent]
class UpdateCategoryTool extends BlogTool
{
    protected function ability(): string
    {
        return 'update';
    }

    protected function tokenAbility(): string
    {
        return 'categories:update';
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
            'id.required' => 'You must provide the category ID to update.',
        ]);

        $category = Category::find($validated['id']);

        return $category ?? Response::error('Category not found.');
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        /** @var Category $category */
        $category = $record;

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'id.required' => 'You must provide the category ID to update.',
            'name.required' => 'A name is required to update a category.',
        ]);

        $category->update([
            'name' => $validated['name'],
        ]);

        return Response::structured([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'updated_at' => $category->updated_at->toIso8601String(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The category ID to update.')->required(),
            'name' => $schema->string()->description('New category name.')->required(),
        ];
    }
}

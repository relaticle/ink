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

#[Description('Create a new blog category. Slug is auto-generated from the name.')]
class CreateCategoryTool extends BlogTool
{
    protected function ability(): string
    {
        return 'create';
    }

    protected function tokenAbility(): string
    {
        return 'categories:create';
    }

    protected function model(): string
    {
        return Category::class;
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'A name is required to create a category.',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
        ]);

        return Response::structured([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'created_at' => $category->created_at->toIso8601String(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The category name.')->required(),
        ];
    }
}

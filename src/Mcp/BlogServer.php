<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use Relaticle\Ink\Mcp\Tools\CreateCategoryTool;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\DeleteCategoryTool;
use Relaticle\Ink\Mcp\Tools\DeletePostTool;
use Relaticle\Ink\Mcp\Tools\GeneratePreviewUrlTool;
use Relaticle\Ink\Mcp\Tools\GetCategoryTool;
use Relaticle\Ink\Mcp\Tools\GetPostTool;
use Relaticle\Ink\Mcp\Tools\ListCategoriesTool;
use Relaticle\Ink\Mcp\Tools\ListPostsTool;
use Relaticle\Ink\Mcp\Tools\RestoreCategoryTool;
use Relaticle\Ink\Mcp\Tools\RestorePostTool;
use Relaticle\Ink\Mcp\Tools\UpdateCategoryTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;

#[Name('Blog')]
#[Version('2.0.0')]
#[Instructions('Manage blog posts and categories. Full CRUD with soft delete (no hard delete). Posts carry title, markdown content, excerpt, category, status (draft/published) and published_at.')]
class BlogServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListPostsTool::class,
        GetPostTool::class,
        CreatePostTool::class,
        UpdatePostTool::class,
        DeletePostTool::class,
        RestorePostTool::class,
        GeneratePreviewUrlTool::class,
        ListCategoriesTool::class,
        GetCategoryTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,
        DeleteCategoryTool::class,
        RestoreCategoryTool::class,
    ];
}

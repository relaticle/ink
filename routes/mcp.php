<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Relaticle\Ink\Mcp\BlogServer;

Mcp::web(config('ink.mcp.path', '/mcp/blog'), BlogServer::class)
    ->middleware(config('ink.mcp.middleware', ['auth:sanctum']));

<?php

declare(strict_types=1);
use App\Models\User;

return [
    'prefix' => 'blog',

    'layout' => 'layouts.app',

    /*
     * Render your own views instead of the package's. Each null falls back to
     * the matching `ink::pages.*` view.
     */
    'views' => [
        'index' => null,
        'show' => null,
        'category' => null,
        'tag' => null,
        'preview' => null,
        'feed' => null,
    ],

    'middleware' => ['web'],

    'author_model' => User::class,

    'per_page' => 12,

    'features' => [
        'public_routes' => false,
        'feed' => false,
        'sitemap' => false,
        'tags' => false,
        'media_library' => false,
        'mcp' => false,
    ],

    'mcp' => [
        'path' => '/mcp/blog',
        'guard' => null,
        'middleware' => ['auth:sanctum'],
    ],

    /*
     * Where upload-image and the featured_image param on create/update-post-tool
     * store and validate images. Deliberately matches the Filament FileUpload
     * defaults on the featured image field (disk "public", directory "ink"), so
     * panel and MCP uploads land in one place.
     *
     * IMPORTANT — max_bytes and PHP's post_max_size: the base64 `data` upload
     * path is bound by PHP's post_max_size (and any webserver/proxy body-size
     * limit) BEFORE this app-level cap ever runs. base64 inflates the binary
     * size by ~4/3, so a max_bytes this high already assumes an ~5.5MB request
     * body just for the encoded image, before the surrounding JSON-RPC envelope.
     * On a typical 5-8M post_max_size, an oversized base64 payload is rejected
     * by PHP itself — the client gets a raw PHP warning in an HTTP 200 instead
     * of a clean JSON-RPC error, because Laravel never boots to handle it. No
     * app-level check can catch this; raise post_max_size (and any proxy limit)
     * to comfortably exceed max_bytes * 4/3, or keep max_bytes low enough for
     * common defaults (this default: 3MB binary ≈ 4MB base64, safely under a 5M
     * floor). The `url` path has no such ceiling — the image bytes are fetched
     * by this server's own HTTP client, not carried in the MCP request body —
     * so prefer it for anything larger than a few MB.
     */
    'uploads' => [
        'disk' => 'public',
        'directory' => 'ink',
        'max_bytes' => 3 * 1024 * 1024,
    ],

    'feed' => [
        'title' => null,
        'description' => null,
        'author_email' => null,
    ],

    'publisher' => [
        'name' => null,
        'url' => null,
        'logo' => null,
    ],

    'schema' => [
        'faq_auto' => true,
        'howto_auto' => false,
    ],

    'search' => [
        'callback' => null,
    ],

    'tables' => [
        'posts' => 'blog_posts',
        'categories' => 'blog_categories',
    ],
];

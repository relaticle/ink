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

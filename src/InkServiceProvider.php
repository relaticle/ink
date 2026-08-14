<?php

declare(strict_types=1);

namespace Relaticle\Ink;

use Illuminate\Support\Facades\Blade;
use Laravel\Mcp\Facades\Mcp as McpFacade;
use Livewire\Livewire;
use RalphJSmit\Laravel\SEO\Facades\SEOManager;
use RalphJSmit\Laravel\SEO\Schema\CustomSchema;
use RalphJSmit\Laravel\SEO\TagCollection;
use RalphJSmit\Laravel\SEO\TagManager;
use Relaticle\Ink\Livewire\BlogSearch;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Support\SchemaExtractor;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

class InkServiceProvider extends PackageServiceProvider
{
    public static string $name = 'ink';

    public static string $viewNamespace = 'ink';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasViews(static::$viewNamespace);
    }

    public function packageRegistered(): void
    {
        // TagManager must be a singleton so seo()->for() called in the
        // BlogController persists to the {!! seo() !!} call in the layout.
        $this->app->singleton(TagManager::class);
    }

    public function packageBooted(): void
    {
        Blade::componentNamespace('Relaticle\\Ink\\Components', 'ink');

        $mcpEnabled = config('ink.features.mcp') && class_exists(McpFacade::class);

        if (config('ink.features.public_routes')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/preview.php');
        } elseif ($mcpEnabled) {
            // GeneratePreviewUrlTool needs blog.preview even while the rest of the
            // public blog is dark — it's the only public_routes route MCP depends on.
            $this->loadRoutesFrom(__DIR__.'/../routes/preview.php');
        }

        // laravel/mcp is a suggestion, not a requirement: a host that enables the flag
        // without installing it gets no route rather than a fatal error.
        if ($mcpEnabled) {
            $this->loadRoutesFrom(__DIR__.'/../routes/mcp.php');
        }

        $this->registerHowToSchemaTransformer();
        $this->registerListingSchemaTransformer();

        if (class_exists(Livewire::class)) {
            Livewire::component('blog::search', BlogSearch::class);

            Livewire::resolveMissingComponent(function (string $name): ?string {
                if ($name === 'blog::search') {
                    return BlogSearch::class;
                }

                return null;
            });
        }
    }

    private function registerHowToSchemaTransformer(): void
    {
        SEOManager::tagTransformer(function (TagCollection $tags): TagCollection {
            if (! config('ink.schema.howto_auto', false)) {
                return $tags;
            }

            try {
                $route = request()->route();
                $param = $route?->parameter('post');

                $post = $param instanceof Post
                    ? $param
                    : (is_string($param)
                        ? Post::query()->published()->where('slug', $param)->first()
                        : null);

                if (! $post instanceof Post && ($slug = $route?->parameter('slug')) !== null) {
                    $post = Post::query()->published()->where('slug', $slug)->first();
                }

                if (! $post instanceof Post) {
                    return $tags;
                }

                $steps = SchemaExtractor::extractHowToSteps($post->renderedContent());

                if ($steps === []) {
                    return $tags;
                }

                $tags->push(new CustomSchema([
                    '@context' => 'https://schema.org',
                    '@type' => 'HowTo',
                    'name' => $post->title,
                    'step' => $steps,
                ]));

                return $tags;
            } catch (Throwable) {
                return $tags;
            }
        });
    }

    private function registerListingSchemaTransformer(): void
    {
        SEOManager::tagTransformer(function (TagCollection $tags): TagCollection {
            try {
                $route = request()->route();
                $routeName = $route?->getName();

                $isIndex = $routeName === 'blog.index';
                $isCategory = $routeName === 'blog.category';
                $isTag = $routeName === 'blog.tag';

                if (! ($isIndex || $isCategory || $isTag)) {
                    return $tags;
                }

                $publisher = config('ink.publisher');
                $publisherEntity = $publisher && ! empty($publisher['name']) ? [
                    '@type' => 'Organization',
                    'name' => $publisher['name'],
                    'url' => $publisher['url'] ?? null,
                ] : null;

                $collectionName = match (true) {
                    $isCategory => (string) ($route?->parameter('slug') ?? 'Category'),
                    $isTag => (string) ($route?->parameter('slug') ?? 'Tag'),
                    default => 'Blog',
                };

                $collectionPage = array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'url' => url()->current(),
                    'name' => $collectionName,
                    'isPartOf' => $publisherEntity ? [
                        '@type' => 'WebSite',
                        'name' => $publisher['name'],
                        'url' => $publisher['url'] ?? null,
                    ] : null,
                ], fn ($value) => $value !== null);

                $tags->push(new CustomSchema($collectionPage));

                if ($isIndex) {
                    $blog = array_filter([
                        '@context' => 'https://schema.org',
                        '@type' => 'Blog',
                        'url' => route('blog.index'),
                        'name' => $publisher && ! empty($publisher['name']) ? "{$publisher['name']} Blog" : 'Blog',
                        'publisher' => $publisherEntity,
                    ], fn ($value) => $value !== null);
                    $tags->push(new CustomSchema($blog));
                }

                return $tags;
            } catch (Throwable) {
                return $tags;
            }
        });
    }
}

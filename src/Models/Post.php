<?php

declare(strict_types=1);

namespace Relaticle\Ink\Models;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RalphJSmit\Laravel\SEO\Schema\ArticleSchema;
use RalphJSmit\Laravel\SEO\Schema\BreadcrumbListSchema;
use RalphJSmit\Laravel\SEO\Schema\FaqPageSchema;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Relaticle\Ink\Database\Factories\PostFactory;
use Relaticle\Ink\Enums\PostStatus;
use Relaticle\Ink\Support\SchemaExtractor;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model
{
    use HasFactory;
    use HasSEO;
    use HasSlug;
    use SoftDeletes;

    protected $table = 'blog_posts';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'category_id',
        'author_id',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if ($post->isDirty('content')) {
                Cache::forget("post-rendered:{$post->id}");
            }
        });
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag', 'post_id', 'tag_id')->withTimestamps();
    }

    /** @return BelongsTo<Model, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('ink.author_model'), 'author_id');
    }

    /** @return array{label: string, color: string} */
    public function displayStatus(): array
    {
        if ($this->status === PostStatus::Draft) {
            return ['label' => 'Draft', 'color' => 'gray'];
        }

        if ($this->published_at?->isFuture()) {
            return ['label' => 'Scheduled', 'color' => 'warning'];
        }

        return ['label' => 'Published', 'color' => 'success'];
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', PostStatus::Draft);
    }

    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query
            ->where('status', PostStatus::Published)
            ->where('published_at', '>', now());
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query
            ->where('status', PostStatus::Published)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $callback = config('ink.search.callback');

        if (is_callable($callback)) {
            $callback($query, $term);

            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }

    public function toHtml(): string
    {
        return Cache::rememberForever(
            "post-rendered:{$this->id}",
            // Post images live below the fold in a text-first layout, so they're
            // marked lazy at render time. MarkdownRenderer has no config hook for
            // image attributes, and CommonMark's image renderer always emits
            // `<img src="..."` (never a bare `<img>`), so a post-replace on that one
            // tag shape is reliable — the same technique the host app's own doc
            // renderer already uses.
            fn (): string => str_replace(
                '<img ',
                '<img loading="lazy" decoding="async" ',
                app(MarkdownRenderer::class)->toHtml($this->content),
            ),
        );
    }

    /**
     * Build a fragment => heading-text map from the rendered post.
     *
     * DOM-parsed rather than regex: heading text is taken from textContent so inline
     * markup (bold, code, links) survives, and entities decode exactly once. The
     * fragment comes from the injected permalink anchor — the heading's own id is
     * slugified from its inner HTML and unusable as a target.
     *
     * @return array<string, string>
     */
    public function tableOfContents(string $tag = 'h2'): array
    {
        if (! preg_match('/^h[1-6]$/', $tag)) {
            throw new InvalidArgumentException(
                "Invalid heading tag [{$tag}] passed to Post::tableOfContents(). Allowed values are h1, h2, h3, h4, h5, h6.",
            );
        }

        $html = $this->toHtml();

        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><div>'.$html.'</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toc = [];

        foreach (new DOMXPath($document)->query('//'.$tag) ?: [] as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $fragment = $this->headingFragment($heading);
            $text = $this->headingText($heading);

            if ($fragment === null || $text === '') {
                continue;
            }

            $toc[$fragment] = $text;
        }

        return $toc;
    }

    private function headingFragment(DOMElement $heading): ?string
    {
        foreach ($heading->getElementsByTagName('a') as $anchor) {
            $id = $anchor->getAttribute('id');

            if ($id !== '') {
                return $id;
            }
        }

        $ownId = $heading->getAttribute('id');

        return $ownId === '' ? null : $ownId;
    }

    private function headingText(DOMElement $heading): string
    {
        $clone = $heading->cloneNode(true);

        if (! $clone instanceof DOMElement) {
            return '';
        }

        foreach (iterator_to_array($clone->getElementsByTagName('a')) as $anchor) {
            if ($anchor->getAttribute('id') !== '') {
                $anchor->parentNode?->removeChild($anchor);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $clone->textContent) ?? '');
    }

    public function readingTime(int $wordsPerMinute = 200): int
    {
        $words = str_word_count(strip_tags((string) $this->content));

        return max(1, (int) ceil($words / $wordsPerMinute));
    }

    public function relatedPosts(int $limit = 3): Builder
    {
        // Eager-load category: every renderer of a related post shows its badge, and a
        // host with Model::preventLazyLoading() enabled would otherwise get a 500.
        return static::query()
            ->with('category')
            ->published()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->when(
                $this->category_id,
                fn (Builder $q) => $q->where('category_id', $this->category_id),
                fn (Builder $q) => $q->whereRaw('1 = 0'),
            )
            ->latest('published_at')
            ->limit($limit);
    }

    public function getUrl(): string
    {
        if ($this->status === PostStatus::Published && ! $this->published_at?->isFuture()) {
            return Route::has('blog.show')
                ? route('blog.show', $this->slug)
                : '#';
        }

        return Route::has('blog.preview')
            ? URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $this])
            : '#';
    }

    public function renderedContent(): string
    {
        return $this->processInternalLinks($this->toHtml());
    }

    private function processInternalLinks(string $html): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        if (! $host) {
            return $html;
        }

        return preg_replace_callback(
            '/<a\s([^>]*href="https?:\/\/'.preg_quote($host, '/').'(?=["\/?#])[^"]*"[^>]*)>/i',
            function (array $matches): string {
                $attrs = $matches[1];

                $attrs = preg_replace('/\s*target="_blank"/', '', $attrs);
                $attrs = preg_replace('/\s+nofollow\b|\bnofollow\s+|\bnofollow\b/', '', $attrs);
                $attrs = preg_replace('/rel="\s*"/', '', $attrs);
                $attrs = preg_replace('/\s{2,}/', ' ', $attrs);

                return '<a '.trim($attrs).'>';
            },
            $html,
        ) ?? $html;
    }

    public function getDynamicSEOData(): SEOData
    {
        $schema = SchemaCollection::initialize()
            ->addArticle(function (ArticleSchema $article): ArticleSchema {
                $article->type = 'BlogPosting';

                return $article->markup(function (Collection $markup): Collection {
                    $publisherConfig = config('ink.publisher');

                    if (! $publisherConfig['name']) {
                        return $markup;
                    }

                    return $markup->put('publisher', [
                        '@type' => 'Organization',
                        'name' => $publisherConfig['name'],
                        'url' => $publisherConfig['url'],
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => asset($publisherConfig['logo']),
                        ],
                    ]);
                });
            })
            ->addBreadcrumbs(function (BreadcrumbListSchema $breadcrumbs): BreadcrumbListSchema {
                $crumbs = ['Home' => url('/')];

                if (Route::has('blog.index')) {
                    $crumbs['Blog'] = route('blog.index');
                }

                if ($this->category && Route::has('blog.category')) {
                    $crumbs[$this->category->name] = route('blog.category', $this->category->slug);
                }

                return $breadcrumbs->prependBreadcrumbs($crumbs);
            });

        if (config('ink.schema.faq_auto', true)) {
            $faqEntities = SchemaExtractor::extractFaqEntities($this->renderedContent());

            if ($faqEntities !== []) {
                $schema = $schema->addFaqPage(function (FaqPageSchema $faq) use ($faqEntities): FaqPageSchema {
                    foreach ($faqEntities as $entity) {
                        $faq->addQuestion($entity['name'], $entity['acceptedAnswer']['text']);
                    }

                    return $faq;
                });
            }
        }

        return new SEOData(
            title: $this->title,
            description: $this->excerpt,
            author: $this->author?->name,
            image: $this->featured_image ? asset("storage/{$this->featured_image}") : null,
            published_time: $this->published_at,
            modified_time: $this->updated_at,
            articleBody: Str::limit(
                trim(preg_replace('/\s+/', ' ', strip_tags($this->content))),
                200,
            ),
            section: $this->category?->name,
            type: 'article',
            schema: $schema,
        );
    }
}

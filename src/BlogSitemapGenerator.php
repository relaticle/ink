<?php

declare(strict_types=1);

namespace Relaticle\Ink;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class BlogSitemapGenerator
{
    public static function addToSitemap(Sitemap $sitemap): Sitemap
    {
        if (! Route::has(Ink::routeName('index'))) {
            return $sitemap;
        }

        foreach (Ink::locales() as $locale) {
            self::addLocale($sitemap, $locale);
        }

        return $sitemap;
    }

    private static function addLocale(Sitemap $sitemap, string $locale): void
    {
        $isDefault = $locale === Ink::defaultLocale();
        $published = fn (Builder $query) => $query->published()->inLocale($locale);

        if (! $isDefault && ! Post::query()->published()->inLocale($locale)->exists()) {
            return;
        }

        $sitemap->add(
            Url::create(route(Ink::routeName('index', $locale)))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.8)
        );

        if (Route::has(Ink::routeName('category', $locale))) {
            Category::query()
                ->select(['id', 'slug'])
                ->whereHas('posts', $published)
                ->each(function (Category $category) use ($sitemap, $locale): void {
                    $sitemap->add(
                        Url::create(route(Ink::routeName('category', $locale), $category->slug))
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.6)
                    );
                });
        }

        if (Route::has(Ink::routeName('show', $locale))) {
            Post::query()
                ->select(['id', 'slug', 'locale', 'translation_group', 'status', 'published_at', 'updated_at'])
                ->published()
                ->inLocale($locale)
                ->latest('published_at')
                ->each(function (Post $post) use ($sitemap): void {
                    $url = Url::create($post->getUrl())
                        ->setLastModificationDate($post->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7);

                    foreach ($post->alternateUrls() as $hreflang => $href) {
                        $url->addAlternate($href, $hreflang);
                    }

                    $sitemap->add($url);
                });
        }

        if (Route::has(Ink::routeName('tag', $locale)) && config('ink.features.tags', false)) {
            Tag::query()
                ->select(['id', 'slug'])
                ->whereHas('posts', $published)
                ->each(function (Tag $tag) use ($sitemap, $locale): void {
                    $sitemap->add(
                        Url::create(route(Ink::routeName('tag', $locale), $tag->slug))
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.5)
                    );
                });
        }
    }
}

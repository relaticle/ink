<?php

declare(strict_types=1);

namespace Relaticle\Ink\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Sluggable\Actions\GenerateSlugAction;
use Spatie\Sluggable\SlugOptions;

/**
 * Lets a live record take a slug that only a soft-deleted record still holds.
 *
 * The parent action counts trashed rows as occupants, so deleting a post and
 * writing it again forever yields `my-post-1`, `my-post-2`, and the clean slug
 * is never reachable again. Here a trashed row is treated as vacant instead.
 *
 * The `slug` column is uniquely indexed, so vacating has to be real rather
 * than a query trick: a soft-deleted record whose slug gets claimed is
 * re-suffixed on the spot, and restoring it re-suffixes it again if the slug
 * it left behind is now occupied.
 */
final class ReclaimsTrashedSlugsAction extends GenerateSlugAction
{
    public function makeUnique(string $slug, Model $model, SlugOptions $options): string
    {
        $unique = parent::makeUnique($slug, $model, $options);

        if ($unique === $slug) {
            return $unique;
        }

        if (! $this->modelUsesSoftDeletes($model)) {
            return $unique;
        }

        if ($this->liveRecordExistsWithSlug($slug, $model, $options)) {
            return $unique;
        }

        $model->vacateTrashedSlug($slug);

        return $slug;
    }

    private function liveRecordExistsWithSlug(string $slug, Model $model, SlugOptions $options): bool
    {
        $query = $model->newQuery()
            ->withoutGlobalScopes()
            ->where($options->slugField, $slug);

        if ($options->extraScopeCallback !== null) {
            $query->where($options->extraScopeCallback);
        }

        if ($model->exists) {
            $query->where($model->getKeyName(), '!=', $model->getKey());
        }

        $query->withGlobalScope(SoftDeletingScope::class, new SoftDeletingScope);

        return $query->exists();
    }
}

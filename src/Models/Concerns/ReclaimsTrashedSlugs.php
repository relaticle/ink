<?php

declare(strict_types=1);

namespace Relaticle\Ink\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use Relaticle\Ink\Support\ReclaimsTrashedSlugsAction;
use Spatie\Sluggable\Actions\GenerateSlugAction;

/**
 * Makes a soft-deleted record's slug available to live records again.
 *
 * Pair with HasSlug and SoftDeletes. See ReclaimsTrashedSlugsAction for why a
 * trashed slug has to be actively vacated rather than merely ignored.
 */
trait ReclaimsTrashedSlugs
{
    public static function bootReclaimsTrashedSlugs(): void
    {
        // A slug set by hand never reaches the generator, so the trashed
        // occupant has to be cleared out here or the unique index rejects
        // the write.
        static::saving(function (self $model): void {
            $slugField = $model->getSlugOptions()->slugField;

            if (! $model->isDirty($slugField)) {
                return;
            }

            $slug = (string) $model->getAttribute($slugField);

            if ($slug === '') {
                return;
            }

            $heldByLiveRecord = static::query()
                ->withoutGlobalScopes()
                ->withGlobalScope(SoftDeletingScope::class, new SoftDeletingScope)
                ->where($slugField, $slug)
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($heldByLiveRecord) {
                return;
            }

            $model->vacateTrashedSlug($slug);
        });

        static::restoring(function (self $model): void {
            $options = $model->getSlugOptions();
            $slugField = $options->slugField;
            $slug = (string) $model->getAttribute($slugField);

            if ($slug === '') {
                return;
            }

            $taken = static::query()
                ->withoutGlobalScopes()
                ->where($slugField, $slug)
                ->whereKeyNot($model->getKey())
                ->exists();

            if (! $taken) {
                return;
            }

            $model->setAttribute(
                $slugField,
                (new GenerateSlugAction)->makeUnique($slug, $model, $options),
            );
        });
    }

    protected function generateSlugAction(): GenerateSlugAction
    {
        return new ReclaimsTrashedSlugsAction;
    }

    /**
     * Re-slug the trashed records sitting on this slug so a live record can
     * take it. They are moved aside rather than removed, since a restore has
     * to stay possible and the unique index tolerates no duplicates.
     */
    public function vacateTrashedSlug(string $slug): void
    {
        $options = $this->getSlugOptions();
        $action = new GenerateSlugAction;
        $suffixing = (clone $options)->useSuffixOnFirstOccurrence();

        $occupants = static::query()
            ->withoutGlobalScopes()
            ->where($options->slugField, $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->get();

        foreach ($occupants as $occupant) {
            $occupant->setAttribute(
                $options->slugField,
                $action->makeUnique($slug, $occupant, $suffixing),
            );

            $occupant->saveQuietly();
        }
    }
}

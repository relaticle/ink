<?php

declare(strict_types=1);

namespace Relaticle\Ink\Filament\Resources\PostResource\Pages\Concerns;

use Relaticle\Ink\Models\Post;

trait LinksTranslation
{
    protected function linkTranslation(): void
    {
        $source = Post::find($this->data['translation_of'] ?? null);

        if ($source instanceof Post) {
            $this->record->update(['translation_group' => $source->ensureTranslationGroup()]);
        }
    }
}

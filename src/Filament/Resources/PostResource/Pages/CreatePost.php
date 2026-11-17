<?php

declare(strict_types=1);

namespace Relaticle\Ink\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Relaticle\Ink\Filament\Resources\PostResource;
use Relaticle\Ink\Filament\Resources\PostResource\Pages\Concerns\LinksTranslation;

class CreatePost extends CreateRecord
{
    use LinksTranslation;

    protected static string $resource = PostResource::class;

    protected function afterCreate(): void
    {
        $this->linkTranslation();
    }
}

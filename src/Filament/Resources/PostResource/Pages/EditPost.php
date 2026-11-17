<?php

declare(strict_types=1);

namespace Relaticle\Ink\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Relaticle\Ink\Filament\Resources\PostResource;
use Relaticle\Ink\Filament\Resources\PostResource\Pages\Concerns\LinksTranslation;

class EditPost extends EditRecord
{
    use LinksTranslation;

    protected static string $resource = PostResource::class;

    protected function afterSave(): void
    {
        $this->linkTranslation();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}

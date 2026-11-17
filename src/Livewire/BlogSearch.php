<?php

declare(strict_types=1);

namespace Relaticle\Ink\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Relaticle\Ink\Ink;
use Relaticle\Ink\Models\Post;

class BlogSearch extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Locked]
    public string $locale = '';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale !== null && in_array($locale, Ink::locales(), true)
            ? $locale
            : Ink::requestLocale();
    }

    public function render(): View
    {
        $results = $this->query === ''
            ? new Collection
            : Post::query()
                ->with(['category', 'author'])
                ->published()
                ->inLocale($this->locale)
                ->search($this->query)
                ->latest('published_at')
                ->limit(20)
                ->get();

        return view('ink::livewire.blog-search', [
            'results' => $results,
        ]);
    }
}

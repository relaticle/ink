<?php

declare(strict_types=1);

namespace Relaticle\Ink\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Relaticle\Ink\Ink;

class FeedLink extends Component
{
    public bool $hasFeedRoute;

    public string $routeName;

    public function __construct()
    {
        $this->routeName = Ink::routeName('feed', Ink::requestLocale());
        $this->hasFeedRoute = Route::has($this->routeName);
    }

    public function render(): View
    {
        return view('ink::components.feed-link');
    }

    public function shouldRender(): bool
    {
        return $this->hasFeedRoute && config('ink.feed.enabled', true);
    }
}

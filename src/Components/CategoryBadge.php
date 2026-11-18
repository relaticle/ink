<?php

declare(strict_types=1);

namespace Relaticle\Ink\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Relaticle\Ink\Models\Category;

class CategoryBadge extends Component
{
    public function __construct(
        public Category $category,
        public bool $linked = true,
        public ?string $locale = null,
    ) {}

    public function render(): View
    {
        return view('ink::components.category-badge');
    }
}

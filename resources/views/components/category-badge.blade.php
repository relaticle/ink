@php($categoryRoute = \Relaticle\Ink\Ink::routeName('category', $locale ?? \Relaticle\Ink\Ink::requestLocale()))
@if($linked && \Illuminate\Support\Facades\Route::has($categoryRoute))
    <a href="{{ route($categoryRoute, $category->slug) }}"
       class="text-xs font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2.5 py-1 rounded-full hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors">
        {{ $category->name }}
    </a>
@else
    <span class="text-xs font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2.5 py-1 rounded-full">
        {{ $category->name }}
    </span>
@endif

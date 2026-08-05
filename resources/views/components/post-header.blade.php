<header class="mb-10">
    <div class="flex items-center gap-3 mb-4">
        @if($post->category)
            <x-ink::category-badge :category="$post->category" />
        @endif
    </div>

    <h1 class="text-3xl sm:text-4xl font-bold text-gray-950 dark:text-white leading-[1.15] tracking-tight mb-4 break-words">
        {{ $post->title }}
    </h1>

    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
        @if($post->author)
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $post->author->name }}</span>
            <span>&middot;</span>
        @endif
        @if($post->published_at)
            <time datetime="{{ $post->published_at->toIso8601String() }}">
                {{ $post->published_at->format('F j, Y') }}
            </time>
        @else
            <span>Draft</span>
        @endif
        <span>&middot;</span>
        <span>{{ $readTime }} min read</span>
    </div>
</header>

@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
@php
    $feedLocale = $locale ?? \Relaticle\Ink\Ink::requestLocale();
    $indexRoute = \Relaticle\Ink\Ink::routeName('index', $feedLocale);
    $feedRoute = \Relaticle\Ink\Ink::routeName('feed', $feedLocale);
    $showRoute = \Relaticle\Ink\Ink::routeName('show', $feedLocale);
@endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('ink.feed.title', config('app.name') . ' Blog') }}</title>
        <link>{{ \Illuminate\Support\Facades\Route::has($indexRoute) ? route($indexRoute) : url('/') }}</link>
        <description>{{ config('ink.feed.description', '') }}</description>
        <language>{{ str_replace('_', '-', $feedLocale) }}</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRfc2822String() }}</lastBuildDate>
        @if(\Illuminate\Support\Facades\Route::has($feedRoute))
            <atom:link href="{{ route($feedRoute) }}" rel="self" type="application/rss+xml" />
        @endif

        @foreach($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                @if(\Illuminate\Support\Facades\Route::has($showRoute))
                    <link>{{ route($showRoute, $post->slug) }}</link>
                    <guid isPermaLink="true">{{ route($showRoute, $post->slug) }}</guid>
                @endif
                <description><![CDATA[{{ $post->excerpt ?: Str::limit(strip_tags($post->content), 300) }}]]></description>
                <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
                @if($post->category)
                    <category>{{ $post->category->name }}</category>
                @endif
                @if(config('ink.feed.author_email'))
                    <author>{{ config('ink.feed.author_email') }} ({{ $post->author?->name }})</author>
                @endif
            </item>
        @endforeach
    </channel>
</rss>

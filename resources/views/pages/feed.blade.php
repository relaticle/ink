@php echo '<' . '?xml version="1.0" encoding="UTF-8"?>' . "\n"; @endphp
@php
    $feedLocale = $locale ?? \Relaticle\Ink\Ink::requestLocale();
    $feedRoute = \Relaticle\Ink\Ink::routeName('feed', $feedLocale);
    $showRoute = \Relaticle\Ink\Ink::routeName('show', $feedLocale);
@endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ config('ink.feed.title') ?? config('app.name') }}</title>
    <link>{{ url('/') }}</link>
    <description>{{ config('ink.feed.description') ?? '' }}</description>
    <language>{{ str_replace('_', '-', $feedLocale) }}</language>
    <atom:link href="{{ route($feedRoute) }}" rel="self" type="application/rss+xml" />
    @foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ \Illuminate\Support\Facades\Route::has($showRoute) ? route($showRoute, $post->slug) : url('/blog/'.$post->slug) }}</link>
            <guid isPermaLink="true">{{ \Illuminate\Support\Facades\Route::has($showRoute) ? route($showRoute, $post->slug) : url('/blog/'.$post->slug) }}</guid>
            <pubDate>{{ $post->published_at?->toRfc822String() }}</pubDate>
            <description><![CDATA[{{ $post->excerpt }}]]></description>
            @if (config('ink.feed.author_email'))
                <author>{{ config('ink.feed.author_email') }}</author>
            @endif
        </item>
    @endforeach
</channel>
</rss>

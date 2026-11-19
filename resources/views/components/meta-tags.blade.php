<meta property="og:title" content="{{ $post->title }}" />
<meta property="og:description" content="{{ $post->excerpt }}" />
<meta property="og:type" content="article" />
<meta property="og:url" content="{{ url()->current() }}" />
@if($post->featured_image)
    <meta property="og:image" content="{{ asset('storage/' . $post->featured_image) }}" />
@endif
<meta property="article:published_time" content="{{ $post->published_at?->toIso8601String() }}" />
<meta property="article:modified_time" content="{{ $post->updated_at?->toIso8601String() }}" />
@if($post->author)
    <meta property="article:author" content="{{ $post->author->name }}" />
@endif
@if($post->category)
    <meta property="article:section" content="{{ $post->category->name }}" />
@endif
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $post->title }}" />
<meta name="twitter:description" content="{{ $post->excerpt }}" />
@if($post->featured_image)
    <meta name="twitter:image" content="{{ asset('storage/' . $post->featured_image) }}" />
@endif
<link rel="canonical" href="{{ url()->current() }}" />

@foreach($post->alternateUrls() as $hreflang => $href)
    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
@endforeach

@extends(config('ink.layout', 'layouts.app'))

@section('content')
<meta name="robots" content="noindex, nofollow">
<article class="max-w-2xl mx-auto px-4 py-12 prose dark:prose-invert">
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-6 text-sm">
        Preview mode — this draft is not publicly visible.
    </div>
    <h1>{{ $post->title }}</h1>
    <div class="post-body">
        {!! \Illuminate\Support\Str::markdown($post->content ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
    </div>
</article>
@endsection

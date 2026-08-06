@extends(config('ink.layout', 'layouts.app'))

@section('content')
<x-ink::preview-banner :post="$post" :editUrl="$editUrl" />
<article class="max-w-2xl mx-auto px-4 py-12 prose dark:prose-invert">
    <h1>{{ $post->title }}</h1>
    <div class="post-body">
        {!! \Illuminate\Support\Str::markdown($post->content ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
    </div>
</article>
@endsection

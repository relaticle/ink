@extends(config('ink.layout', 'layouts.app'))

@section('content')
<article class="max-w-2xl mx-auto px-4 py-12 prose dark:prose-invert">
    <h1>{{ $post->title }}</h1>
    <div class="post-body">
        {!! $post->toSafeHtml() !!}
    </div>
</article>
@endsection

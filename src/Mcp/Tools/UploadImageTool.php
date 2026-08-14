<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp\Tools;

use finfo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Psr\Http\Message\StreamInterface;
use Relaticle\Ink\Mcp\BlogTool;
use Relaticle\Ink\Models\Post;
use Throwable;

#[Description('Upload an image for use in a post (as the featured image or embedded in markdown content). Provide either a fetchable url or base64-encoded data, not both. Returns a storage path (pass it as featured_image on create/update-post-tool), a public url, and a ready-to-paste markdown snippet.')]
class UploadImageTool extends BlogTool
{
    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    protected function ability(): string
    {
        return 'create';
    }

    protected function tokenAbility(): string
    {
        return 'posts:create';
    }

    protected function model(): string
    {
        return Post::class;
    }

    protected function run(Request $request, ?Model $record): Response|ResponseFactory
    {
        $validated = $request->validate([
            'url' => ['required_without:data', 'nullable', 'url'],
            'data' => ['required_without:url', 'nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $url = $validated['url'] ?? null;
        $data = $validated['data'] ?? null;

        if ($url !== null && $data !== null) {
            return Response::error('Provide either `url` or `data`, not both.');
        }

        $contents = $url !== null
            ? $this->fetch($url)
            : $this->decode($data);

        if ($contents instanceof Response) {
            return $contents;
        }

        $maxBytes = (int) config('ink.uploads.max_bytes', 5 * 1024 * 1024);

        if (strlen($contents) > $maxBytes) {
            return Response::error("The image exceeds the maximum upload size of {$maxBytes} bytes.");
        }

        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;

        if ($extension === null) {
            $allowed = implode(', ', array_keys(self::ALLOWED_MIME_TYPES));

            return Response::error("Unsupported image type [{$mimeType}]. Allowed types: {$allowed}.");
        }

        $disk = (string) config('ink.uploads.disk', 'public');
        $directory = trim((string) config('ink.uploads.directory', 'ink'), '/');
        $path = "{$directory}/".Str::ulid()->toBase32().".{$extension}";

        Storage::disk($disk)->put($path, $contents);

        $publicUrl = Storage::disk($disk)->url($path);
        $alt = $validated['alt'] ?? '';

        return Response::structured([
            'path' => $path,
            'url' => $publicUrl,
            'markdown' => "![{$alt}]({$publicUrl})",
        ]);
    }

    private function fetch(string $url): string|Response
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return Response::error('Only http and https URLs are supported.');
        }

        $maxBytes = (int) config('ink.uploads.max_bytes', 5 * 1024 * 1024);
        $contentLength = $this->contentLengthFor($url);

        if ($contentLength !== null && $contentLength > $maxBytes) {
            return Response::error("The image exceeds the maximum upload size of {$maxBytes} bytes.");
        }

        try {
            // `stream: true` stops Guzzle eagerly buffering the whole response body
            // into memory before get() returns — the actual transfer happens as
            // readCapped() below pulls chunks off the PSR-7 stream, so a server that
            // omits (or understates) Content-Length still can't force a full download
            // past the cap. The HEAD check above is only the cheap fast path.
            $response = Http::timeout(15)->withOptions(['stream' => true])->get($url);
        } catch (ConnectionException) {
            return Response::error('Could not fetch the image from the provided URL.');
        }

        if ($response->failed()) {
            return Response::error("Could not fetch the image from the provided URL (HTTP {$response->status()}).");
        }

        return $this->readCapped($response->toPsrResponse()->getBody(), $maxBytes);
    }

    /**
     * Reads the stream in bounded chunks and aborts the moment cumulative bytes exceed
     * the cap, instead of Response::body(), which buffers the entire stream into memory
     * before anything could be checked.
     */
    private function readCapped(StreamInterface $stream, int $maxBytes): string|Response
    {
        $buffer = '';

        try {
            while (! $stream->eof()) {
                $buffer .= $stream->read(65536);

                if (strlen($buffer) > $maxBytes) {
                    return Response::error("The image exceeds the maximum upload size of {$maxBytes} bytes.");
                }
            }
        } catch (Throwable) {
            return Response::error('Could not fetch the image from the provided URL.');
        } finally {
            $stream->close();
        }

        return $buffer;
    }

    /**
     * Best-effort: a HEAD request lets a well-behaved server's Content-Length reject an
     * oversized image before its body is ever downloaded. A missing/unreliable header
     * here is not fatal — the strlen() check in run() still catches an oversized body
     * that was fetched anyway.
     */
    private function contentLengthFor(string $url): ?int
    {
        try {
            $head = Http::timeout(5)->head($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $head->successful()) {
            return null;
        }

        $length = $head->header('Content-Length');

        return $length !== null && $length !== '' ? (int) $length : null;
    }

    private function decode(string $data): string|Response
    {
        $maxBytes = (int) config('ink.uploads.max_bytes', 5 * 1024 * 1024);

        // Base64 expands 3 raw bytes into 4 encoded characters. Reject an oversized
        // payload by its encoded length before ever calling base64_decode() on it,
        // instead of decoding a potentially huge string just to discard it a moment
        // later once the decoded size check runs.
        $maxEncodedLength = ((int) ceil($maxBytes / 3)) * 4 + 4;

        if (strlen($data) > $maxEncodedLength) {
            return Response::error("The image exceeds the maximum upload size of {$maxBytes} bytes.");
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return Response::error('The `data` parameter is not valid base64.');
        }

        return $decoded;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('A fetchable http(s) URL to download the image from. Provide this or `data`, not both.'),
            'data' => $schema->string()->description('Base64-encoded image bytes. Provide this or `url`, not both.'),
            'filename' => $schema->string()->description('Optional filename hint. The stored name and extension are always derived from the sniffed image content, not from this value.'),
            'alt' => $schema->string()->description('Alt text for the generated markdown snippet.'),
        ];
    }
}

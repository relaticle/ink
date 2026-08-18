<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use League\Flysystem\FilesystemException;
use Relaticle\Ink\Ink;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

/**
 * Base class for the blog tools.
 *
 * `handle()` is final so authorization cannot be forgotten by a tool added later —
 * the same shape Laravel uses for FormRequest::validateResolved() and Filament for
 * ListRecords::mount(): the base owns the entry point and subclasses declare hooks.
 */
abstract class BlogTool extends Tool
{
    final public function handle(Request $request): Response|ResponseFactory
    {
        $record = $this->resolveRecord($request);

        // A tool's own not-found branch runs before authorization, so a denial
        // cannot double as an existence oracle.
        if ($record instanceof Response) {
            return $record;
        }

        $this->authorizeAccess($request, $record);

        return $this->run($request, $record);
    }

    abstract protected function ability(): string;

    abstract protected function tokenAbility(): string;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function run(Request $request, ?Model $record): Response|ResponseFactory;

    /** Class-target tools (list, create) keep the default. */
    protected function resolveRecord(Request $request): Model|Response|null
    {
        return null;
    }

    /**
     * Two independent axes: the host's Gate decides whether this identity may manage
     * the blog, the Sanctum ability decides whether this credential may. Both throw —
     * laravel/mcp maps AuthenticationException and AuthorizationException to a tool
     * error by name in InteractsWithResponses::toErrorResponse().
     *
     * @throws AuthenticationException|AuthorizationException
     */
    protected function authorizeAccess(Request $request, ?Model $record): void
    {
        $caller = $this->caller($request) ?? throw new AuthenticationException;

        Gate::forUser($caller)->authorize($this->ability(), $record ?? $this->model());

        if (method_exists($caller, 'tokenCan') && ! $caller->tokenCan($this->tokenAbility())) {
            throw new AuthorizationException("Token missing required ability: {$this->tokenAbility()}");
        }
    }

    /**
     * Validation rules for the shared `tags` parameter. Rejects the parameter
     * outright when the host has the tags feature disabled, so an agent gets a
     * clear error instead of a silent no-op.
     *
     * @return array<string, list<mixed>>
     */
    protected function tagsRules(): array
    {
        return [
            'tags' => ['nullable', 'array', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! config('ink.features.tags')) {
                    $fail('Tags are disabled on this blog (ink.features.tags is false).');
                }
            }],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Sync a post's tags from a list of names. Existing tags are matched
     * case-insensitively so agents cannot fragment the vocabulary ("MCP" vs
     * "mcp"); unknown names are created. An empty list detaches everything.
     *
     * @param  list<string>  $names
     */
    protected function syncTagsFromNames(Post $post, array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $tag = Tag::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first() ?? Tag::create(['name' => $name]);

            $ids[$tag->getKey()] = true;
        }

        $post->tags()->sync(array_keys($ids));
    }

    protected function caller(Request $request): ?Authenticatable
    {
        return $request->user(config('ink.mcp.guard'));
    }

    /**
     * Returned rather than thrown: an unrecognised throwable is masked as "An internal
     * server error occurred." outside debug, which would hide the one message an
     * integrator needs to read.
     */
    protected function resolveAuthorOrFail(Request $request): Model|Response
    {
        $author = Ink::resolveAuthor($this->caller($request));

        return $author instanceof Model
            ? $author
            : Response::error('No author could be resolved for this caller. Configure Ink::resolveAuthorUsing().');
    }

    /**
     * A `featured_image` value must be a path this package itself produced via
     * `upload-image` — inside the configured uploads directory and present on the
     * configured disk. Shared by create/update post tools rather than duplicated,
     * since the same check also blocks path injection into the hardcoded
     * `asset('storage/…')` renderers (Post::getDynamicSEOData() and friends).
     *
     * A plain `Str::startsWith($value, "{$directory}/")` is not confinement: Flysystem's
     * local adapter normalizes `..` segments by default (`allow_relative_path_traversal`
     * defaults to `true`), so e.g. "ink/x/../../other/secret.png" starts with the confined
     * prefix as a string but resolves to a file outside it, and a value with more `..`
     * segments than depth (e.g. "ink/../../secret.png") throws an uncaught
     * `PathTraversalDetected` that would otherwise surface as a masked internal-server-error
     * response instead of a clean validation failure. Segments are rejected outright
     * rather than relying on Flysystem's own normalizer semantics, and the disk call is
     * still wrapped defensively in case some other shape reaches it.
     *
     * laravel/mcp does not validate `tools/call` arguments against the tool's
     * advertised JSON schema before invoking it, so `$value` here can be any JSON
     * type a caller sends — and Laravel's Validator does not bail on the sibling
     * `string` rule failing, so this closure still runs even when it did. Guard
     * explicitly rather than let str_replace()/explode() TypeError on a non-string
     * under strict_types, which — like the traversal exceptions above — would
     * surface as the same masked internal-server-error instead of a clean failure.
     */
    protected function featuredImagePathRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $directory = trim((string) config('ink.uploads.directory', 'ink'), '/');
            $invalid = "The featured image must be a path returned by upload-image, inside the [{$directory}] directory.";

            if (! is_string($value)) {
                $fail($invalid);

                return;
            }

            $segments = explode('/', str_replace('\\', '/', $value));

            if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
                $fail($invalid);

                return;
            }

            if (! Str::startsWith($value, $directory.'/')) {
                $fail($invalid);

                return;
            }

            try {
                $exists = Storage::disk(config('ink.uploads.disk', 'public'))->exists($value);
            } catch (FilesystemException) {
                $fail($invalid);

                return;
            }

            if (! $exists) {
                $fail('The featured image was not found. Upload it first with upload-image.');
            }
        };
    }
}

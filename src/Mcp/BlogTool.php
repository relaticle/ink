<?php

declare(strict_types=1);

namespace Relaticle\Ink\Mcp;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Relaticle\Ink\Ink;

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
}

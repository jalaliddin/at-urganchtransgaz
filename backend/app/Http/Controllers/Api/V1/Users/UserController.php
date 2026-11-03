<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use App\Support\Search\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $base = User::query()->with(UserResource::RELATIONS);
        SearchTerm::whereContains($base, ['username', 'name', 'email'], SearchTerm::fromRequest($request));

        $page = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::custom('organization', new OrganizationSubtreeFilter),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('role', fn (Builder $query, mixed $value) => $query->whereHas(
                    'roles', fn (Builder $q) => $q->whereIn('name', (array) $value),
                )),
            ])
            ->allowedSorts(['username', 'name', 'last_login_at', 'created_at'])
            ->defaultSort('username')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->users->create($request->validated(), $request->user());

        return ApiResponse::data(
            new UserResource($result['user']->load(UserResource::RELATIONS)),
            201,
            ['temporary_password' => $result['temporary_password']],
        );
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return ApiResponse::data(new UserResource($user->load(UserResource::RELATIONS)));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->users->update($user, $request->validated(), $request->user());

        return ApiResponse::data(new UserResource($user->load(UserResource::RELATIONS)));
    }
}

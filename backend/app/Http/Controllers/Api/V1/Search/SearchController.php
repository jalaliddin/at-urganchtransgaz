<?php

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Models\User;
use App\Services\Search\GlobalSearch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, GlobalSearch $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::data((object) $search->search($user, $request->string('q')->toString()));
    }
}

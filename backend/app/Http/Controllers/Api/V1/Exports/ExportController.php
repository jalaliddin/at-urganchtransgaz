<?php

namespace App\Http\Controllers\Api\V1\Exports;

use App\Http\Controllers\Controller;
use App\Http\Resources\Exports\ReportExportResource;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Exports\ExportService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The current user's report exports. */
class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    public function index(Request $request): JsonResponse
    {
        $page = ReportExport::query()->where('user_id', $this->user($request)->id)->with('file')
            ->latest('id')->paginate(Pagination::perPage($request));

        return ApiResponse::paginated($page, ReportExportResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ExportService::REPORTS))],
            'format' => ['required', 'string', Rule::in(ExportService::FORMATS)],
            'filters' => ['sometimes', 'nullable', 'array'],
        ]);
        $user = $this->user($request);
        $report = $this->exports->report($data['type']);
        if (! $user->can($report->permission())) {
            throw new AuthorizationException;
        }
        $filters = (array) ($data['filters'] ?? []);
        $unknown = array_diff(array_keys($filters), $report->filterNames());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['filters' => __('validation.in', ['attribute' => implode(', ', $unknown)])]);
        }

        $export = $this->exports->request($user, $data['type'], $data['format'], $filters);

        return ApiResponse::data(new ReportExportResource($export->load('file')), 202);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $export = ReportExport::query()->where('uuid', $uuid)->where('user_id', $this->user($request)->id)->with('file')->first()
            ?? throw new NotFoundHttpException;

        return ApiResponse::data(new ReportExportResource($export));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

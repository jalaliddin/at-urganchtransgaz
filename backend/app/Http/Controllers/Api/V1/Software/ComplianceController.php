<?php

namespace App\Http\Controllers\Api\V1\Software;

use App\Enums\AntivirusState;
use App\Enums\InstallationSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Software\SoftwareResource;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\Software;
use App\Models\SoftwarePolicy;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Software\ComplianceService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Antivirus reports, required-software policies and the compliance report. */
class ComplianceController extends Controller
{
    public function __construct(private readonly ComplianceService $compliance) {}

    /** Manual antivirus report for one device (asset route binding applies the scope). */
    public function antivirus(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('software.install');
        $data = $request->validate([
            'software_id' => ['nullable', 'integer', Rule::exists('software', 'id')],
            'product_name' => ['nullable', 'string', 'max:150'],
            'state' => ['required', Rule::enum(AntivirusState::class)],
            'definitions_updated_at' => ['nullable', 'date', 'before_or_equal:now'],
            'last_scan_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);
        if (! empty($data['software_id']) && ! Software::query()->whereKey($data['software_id'])->where('is_antivirus', true)->exists()) {
            throw ValidationException::withMessages(['software_id' => __('messages.not_an_antivirus')]);
        }
        $status = AntivirusStatus::query()->updateOrCreate(['asset_id' => $asset->id], [
            'software_id' => $data['software_id'] ?? null, 'product_name' => $data['product_name'] ?? null, 'state' => $data['state'],
            'definitions_updated_at' => $data['definitions_updated_at'] ?? null, 'last_scan_at' => $data['last_scan_at'] ?? null,
            'reported_at' => CarbonImmutable::now(), 'source' => InstallationSource::Manual, 'reported_by' => $this->user($request)->id,
        ]);

        return ApiResponse::data(SoftwareResource::antivirus($status->load('software')));
    }

    public function policies(): JsonResponse
    {
        Gate::authorize('software.use');
        $rows = SoftwarePolicy::query()->with(['software', 'organization', 'assetCategory'])->orderBy('id')->get();

        return ApiResponse::data($rows->map(fn (SoftwarePolicy $p) => SoftwareResource::policy($p))->values());
    }

    public function storePolicy(Request $request): JsonResponse
    {
        Gate::authorize('software.manage');
        $policy = SoftwarePolicy::create($request->validate($this->policyRules()));

        return ApiResponse::data(SoftwareResource::policy($policy->load(['software', 'organization', 'assetCategory'])), 201);
    }

    public function updatePolicy(Request $request, SoftwarePolicy $softwarePolicy): JsonResponse
    {
        Gate::authorize('software.manage');
        $softwarePolicy->update($request->validate($this->policyRules()));

        return ApiResponse::data(SoftwareResource::policy($softwarePolicy->load(['software', 'organization', 'assetCategory'])));
    }

    public function destroyPolicy(SoftwarePolicy $softwarePolicy): JsonResponse
    {
        Gate::authorize('software.manage');
        // A policy of an organization outside the user's scope is not theirs to see.
        abort_if($softwarePolicy->organization_id !== null && ! $this->user(request())->canAccessOrganization($softwarePolicy->organization_id), 404);
        $softwarePolicy->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** Per-organization counts of the user's devices. */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('software.use');
        $rows = $this->compliance->summary($this->user($request), $this->filters($request));

        return ApiResponse::data(['organizations' => $rows]);
    }

    /** Devices, optionally only those with a problem (missing_required, unprotected, outdated). */
    public function devices(Request $request): JsonResponse
    {
        Gate::authorize('software.use');
        $problem = $request->input('filter.problem');
        $devices = $this->compliance->devices($this->user($request), $this->filters($request))
            ->filter(fn (array $d) => ! in_array($problem, ComplianceService::PROBLEMS, true) || ComplianceService::hasProblem($d, (string) $problem))
            ->values();
        $perPage = Pagination::perPage($request);
        $page = new LengthAwarePaginator($devices->forPage($request->integer('page', 1), $perPage)->values(), $devices->count(), $perPage, $request->integer('page', 1));

        return ApiResponse::page($page, fn (array $d) => [
            'id' => $d['asset']->id, 'inventory_number' => $d['asset']->inventory_number, 'name' => $d['asset']->name,
            'organization' => ['id' => $d['asset']->organization->id, 'name' => $d['asset']->organization->name],
            'antivirus' => $d['antivirus']->value, 'product' => $d['product'], 'missing' => $d['missing'],
            'inventoried_at' => $d['asset']->software_inventoried_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return array_intersect_key((array) $request->input('filter', []), array_flip(['organization']));
    }

    /** @return array<string, mixed> */
    private function policyRules(): array
    {
        return [
            'software_id' => ['required', 'integer', Rule::exists('software', 'id')],
            'organization_id' => ['nullable', 'integer', new AccessibleOrganization],
            'asset_category_id' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

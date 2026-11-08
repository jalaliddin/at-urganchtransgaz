<?php

namespace App\Http\Controllers\Api\V1\Software;

use App\Enums\AuditEvent;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Software\SoftwareResource;
use App\Models\SoftwareInstallation;
use App\Models\SoftwareLicense;
use App\Rules\AccessibleOrganization;
use App\Services\Audit\AuditLogger;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Licences of the user's organizations (global scope). Keys are masked; reveal is audited. */
class LicenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('software.use');
        $query = SoftwareLicense::query()->with(['software', 'organization'])->withCount('installations')->orderBy('expires_at')->orderBy('id');
        if ($request->filled('filter.software')) {
            $query->where('software_id', $request->integer('filter.software'));
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        if ($request->boolean('filter.expiring')) {
            $query->whereNotNull('expires_at')->where('expires_at', '<=', CarbonImmutable::now((string) config('app.display_timezone'))
                ->addDays((int) config('software.license_notice_days'))->toDateString());
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (SoftwareLicense $l) => SoftwareResource::license($l, (int) $l->getAttribute('installations_count')));
    }

    public function show(SoftwareLicense $softwareLicense): JsonResponse
    {
        Gate::authorize('software.use');

        return $this->card($softwareLicense);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('software.manage');
        $license = SoftwareLicense::create($request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            ...$this->rules(true),
        ]));

        return $this->card($license, 201);
    }

    public function update(Request $request, SoftwareLicense $softwareLicense): JsonResponse
    {
        Gate::authorize('software.manage');
        $data = $request->validate($this->rules(false));
        DB::transaction(function () use ($softwareLicense, $data): void {
            $locked = SoftwareLicense::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($softwareLicense->id);
            $used = SoftwareInstallation::query()->where('license_id', $locked->id)->count();
            if (isset($data['seats']) && (int) $data['seats'] < max(1, $used)) {
                throw ValidationException::withMessages(['seats' => __('messages.license_seats_below_used', ['used' => $used])]);
            }
            if (isset($data['software_id']) && (int) $data['software_id'] !== $locked->software_id && $used > 0) {
                throw ValidationException::withMessages(['software_id' => __('messages.license_in_use')]);
            }
            if (array_key_exists('license_key', $data) && $data['license_key'] === '') {
                unset($data['license_key']);
            }
            $softwareLicense->update($data);
        });

        return $this->card($softwareLicense->refresh());
    }

    public function destroy(SoftwareLicense $softwareLicense): JsonResponse
    {
        Gate::authorize('software.manage');
        if (SoftwareInstallation::query()->where('license_id', $softwareLicense->id)->exists()) {
            throw new DomainConflictException(__('messages.license_in_use'));
        }
        $softwareLicense->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** Plain key for licence managers; each reveal is written to the audit log. */
    public function reveal(SoftwareLicense $softwareLicense, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('software.manage');
        $audit->record(AuditEvent::SecretRevealed, $softwareLicense, [], ['field' => 'license_key']);

        return ApiResponse::data(['license_key' => $softwareLicense->license_key]);
    }

    private function card(SoftwareLicense $license, int $status = 200): JsonResponse
    {
        $license->load(['software', 'organization'])->loadCount('installations');

        return ApiResponse::data(SoftwareResource::license($license, (int) $license->getAttribute('installations_count')), $status);
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'software_id' => [$req, 'integer', Rule::exists('software', 'id')],
            'number' => ['nullable', 'string', 'max:100'],
            'license_key' => ['nullable', 'string', 'max:2000'],
            'seats' => [$req, 'integer', 'min:1', 'max:1000000'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

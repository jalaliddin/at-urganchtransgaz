<?php

namespace App\Http\Controllers\Api\V1\Software;

use App\Enums\LicenseType;
use App\Enums\SoftwareCategory;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Software\SoftwareResource;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\Software;
use App\Models\SoftwareInstallation;
use App\Models\SoftwareLicense;
use App\Models\SoftwarePolicy;
use App\Models\User;
use App\Services\Software\SoftwareInstallations;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Software catalog (global reference data; counts limited to the user's devices). */
class SoftwareController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('software.use');
        $user = $this->user($request);
        $search = trim((string) $request->input('search', ''));
        $query = Software::query()
            ->withCount(['installations' => fn ($q) => $q->whereIn('asset_id', Asset::query()->visibleTo($user)->select('assets.id'))])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('vendor', 'like', "%{$search}%")))
            ->when($request->filled('filter.category'), fn ($q) => $q->where('category', (string) $request->input('filter.category')))
            ->when($request->boolean('filter.antivirus'), fn ($q) => $q->where('is_antivirus', true))
            ->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name');
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (Software $s) => SoftwareResource::software($s));
    }

    /** Title card: versions on the user's devices and licences in scope. */
    public function show(Request $request, Software $software): JsonResponse
    {
        Gate::authorize('software.use');
        $user = $this->user($request);
        $installations = SoftwareInstallation::query()->visibleTo($user)->where('software_id', $software->id);
        $versions = (clone $installations)->selectRaw('version, COUNT(*) AS devices')->groupBy('version')->orderByDesc('devices')->get()
            ->map(fn ($r) => ['version' => $r->getAttribute('version'), 'devices' => (int) $r->getAttribute('devices')]);
        $licenses = SoftwareLicense::query()->with(['software', 'organization'])->withCount('installations')->where('software_id', $software->id)
            ->orderBy('expires_at')->get();

        return ApiResponse::data([
            ...SoftwareResource::software($software->loadCount(['installations' => fn ($q) => $q->whereIn('asset_id', Asset::query()->visibleTo($user)->select('assets.id'))])),
            'versions' => $versions->values(),
            'licenses' => $licenses->map(fn (SoftwareLicense $l) => SoftwareResource::license($l, (int) $l->getAttribute('installations_count')))->values(),
        ]);
    }

    /** Names reported on the user's devices that match no catalog title, with device counts. */
    public function unrecognized(Request $request): JsonResponse
    {
        Gate::authorize('software.use');
        $rows = SoftwareInstallation::query()->visibleTo($this->user($request))->whereNull('software_id')
            ->selectRaw('normalized_name, MIN(name) AS name, MIN(vendor) AS vendor, COUNT(*) AS devices')
            ->groupBy('normalized_name')->orderByDesc('devices')->orderBy('normalized_name')->limit(500)->get();

        return ApiResponse::data($rows->map(fn ($r) => [
            'name' => (string) $r->getAttribute('name'), 'normalized_name' => (string) $r->getAttribute('normalized_name'),
            'vendor' => $r->getAttribute('vendor'), 'devices' => (int) $r->getAttribute('devices'),
        ])->values());
    }

    /** Links all unrecognised rows with this name to the catalog title. */
    public function adopt(Request $request, Software $software, SoftwareInstallations $installations): JsonResponse
    {
        Gate::authorize('software.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        return ApiResponse::data(['linked' => $installations->adopt($software, $data['name'])]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('software.manage');
        $software = Software::create($request->validate($this->rules(null)));

        return ApiResponse::data(SoftwareResource::software($software->refresh()), 201);
    }

    public function update(Request $request, Software $software): JsonResponse
    {
        Gate::authorize('software.manage');
        $software->update($request->validate($this->rules($software)));

        return ApiResponse::data(SoftwareResource::software($software));
    }

    public function destroy(Software $software): JsonResponse
    {
        Gate::authorize('software.manage');
        $used = SoftwareInstallation::query()->where('software_id', $software->id)->exists()
            || SoftwareLicense::query()->withoutGlobalScopes()->where('software_id', $software->id)->exists()
            || SoftwarePolicy::query()->where('software_id', $software->id)->exists()
            || AntivirusStatus::query()->where('software_id', $software->id)->exists();
        if ($used) {
            throw new DomainConflictException(__('messages.software_in_use'));
        }
        $software->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(?Software $software): array
    {
        $req = $software === null ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:150', function (string $attribute, mixed $value, Closure $fail) use ($software): void {
                $taken = Software::query()->where('normalized_name', Software::normalize((string) $value))
                    ->when($software !== null, fn ($q) => $q->whereKeyNot($software->id))->exists();
                if ($taken) {
                    $fail(__('messages.software_name_taken'));
                }
            }],
            'vendor' => ['nullable', 'string', 'max:150'],
            'category' => [$req, Rule::enum(SoftwareCategory::class)],
            'license_type' => [$req, Rule::enum(LicenseType::class)],
            'is_antivirus' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

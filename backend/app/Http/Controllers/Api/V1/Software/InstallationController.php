<?php

namespace App\Http\Controllers\Api\V1\Software;

use App\Http\Controllers\Controller;
use App\Http\Resources\Software\SoftwareResource;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\DeviceAgent;
use App\Models\SoftwareInstallation;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Services\Software\SoftwareInstallations;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Software, antivirus and agent of one device (asset route binding applies the scope). */
class InstallationController extends Controller
{
    private const RELATIONS = ['software', 'license'];

    public function __construct(private readonly SoftwareInstallations $installations) {}

    public function index(Asset $asset): JsonResponse
    {
        Gate::authorize('software.use');
        $agent = DeviceAgent::query()->where('asset_id', $asset->id)->first();

        return ApiResponse::data([
            'installations' => SoftwareInstallation::query()->with(self::RELATIONS)->where('asset_id', $asset->id)
                ->orderByRaw('software_id IS NULL')->orderBy('name')->get()->map(fn ($i) => SoftwareResource::installation($i))->values(),
            'antivirus' => SoftwareResource::antivirus(AntivirusStatus::query()->with('software')->where('asset_id', $asset->id)->first()),
            'inventoried_at' => $asset->software_inventoried_at?->toIso8601String(),
            'agent' => $agent === null ? null : [
                'id' => $agent->id, 'name' => $agent->name, 'platform' => $agent->platform, 'agent_version' => $agent->agent_version,
                'is_active' => $agent->is_active, 'last_checkin_at' => $agent->last_checkin_at?->toIso8601String(), 'last_ip' => $agent->last_ip,
            ],
        ]);
    }

    public function store(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('software.install');
        $data = $request->validate([
            'software_id' => ['required_without:name', 'nullable', 'integer', Rule::exists('software', 'id')],
            'name' => ['required_without:software_id', 'nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'version' => ['nullable', 'string', 'max:100'],
            'installed_at' => ['nullable', 'date_format:Y-m-d'],
            'license_id' => ['nullable', 'integer', $this->visibleLicense()],
        ]);
        $installation = $this->installations->add($asset, $data, $this->user($request));

        return ApiResponse::data(SoftwareResource::installation($installation->load(self::RELATIONS)), 201);
    }

    public function update(Request $request, SoftwareInstallation $softwareInstallation): JsonResponse
    {
        Gate::authorize('software.install');
        $data = $request->validate([
            'version' => ['sometimes', 'nullable', 'string', 'max:100'],
            'installed_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'license_id' => ['sometimes', 'nullable', 'integer', $this->visibleLicense()],
        ]);
        $this->installations->update($softwareInstallation, $data);

        return ApiResponse::data(SoftwareResource::installation($softwareInstallation->refresh()->load(self::RELATIONS)));
    }

    public function destroy(SoftwareInstallation $softwareInstallation): JsonResponse
    {
        Gate::authorize('software.install');
        $softwareInstallation->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** Licences of the user's organizations only (global scope). */
    private function visibleLicense(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! SoftwareLicense::query()->whereKey((int) $value)->exists()) {
                $fail(__('messages.license_other_software'));
            }
        };
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

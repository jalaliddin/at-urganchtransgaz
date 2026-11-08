<?php

namespace App\Http\Controllers\Api\V1\Software;

use App\Enums\AntivirusState;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use App\Models\Asset;
use App\Models\DeviceAgent;
use App\Models\User;
use App\Services\Software\AgentCheckinService;
use App\Services\Software\AgentService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Agents of the user's devices, and the agent check-in endpoint (token guard, no user). */
class AgentController extends Controller
{
    public function __construct(private readonly AgentService $agents) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('software.use');
        $user = $this->user($request);
        $page = DeviceAgent::query()->with('asset.organization')->whereIn('asset_id', Asset::query()->visibleTo($user)->select('assets.id'))
            ->latest('last_checkin_at')->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (DeviceAgent $a) => $this->row($a));
    }

    public function store(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('software.agents');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'platform' => ['nullable', 'string', 'max:50']]);
        [$agent, $token] = $this->agents->register($asset, $data['name'], $data['platform'] ?? null, $this->user($request));

        return ApiResponse::data([...$this->row($agent->load('asset.organization')), 'token' => $token], 201);
    }

    public function token(Request $request, DeviceAgent $deviceAgent): JsonResponse
    {
        Gate::authorize('software.agents');
        $token = $this->agents->issue($this->visible($request, $deviceAgent), $this->user($request));

        return ApiResponse::data([...$this->row($deviceAgent->refresh()->load('asset.organization')), 'token' => $token], 201);
    }

    public function revoke(Request $request, DeviceAgent $deviceAgent): JsonResponse
    {
        Gate::authorize('software.agents');
        $this->agents->revoke($this->visible($request, $deviceAgent), $this->user($request));

        return ApiResponse::data($this->row($deviceAgent->refresh()->load('asset.organization')));
    }

    public function checkins(Request $request, DeviceAgent $deviceAgent): JsonResponse
    {
        Gate::authorize('software.use');
        $rows = $this->visible($request, $deviceAgent)->checkins()->latest('received_at')->limit(50)->get();

        return ApiResponse::data($rows->map(fn ($c) => [
            'id' => $c->id, 'status' => $c->status, 'software_count' => $c->software_count, 'message' => $c->message,
            'ip' => $c->ip, 'received_at' => $c->received_at->toIso8601String(),
        ])->values());
    }

    /** POST /agent/checkin — the device behind the token reports its software and antivirus. */
    public function checkin(Request $request, AgentCheckinService $service): JsonResponse
    {
        /** @var DeviceAgent $agent */
        $agent = $request->attributes->get('agent');
        // Future dates would keep a device "updated" for ever; a client clock up to a day ahead is tolerated.
        $latest = 'before_or_equal:'.CarbonImmutable::now()->addDay()->toIso8601String();
        $validator = Validator::make($request->all(), [
            'platform' => ['nullable', 'string', 'max:50'],
            'agent_version' => ['nullable', 'string', 'max:50'],
            'software' => ['present', 'array', 'max:2000'],
            'software.*.name' => ['required', 'string', 'max:255'],
            'software.*.version' => ['nullable', 'string', 'max:100'],
            'software.*.vendor' => ['nullable', 'string', 'max:150'],
            'software.*.installed_at' => ['nullable', 'date_format:Y-m-d'],
            'antivirus' => ['nullable', 'array'],
            'antivirus.product' => ['nullable', 'string', 'max:150'],
            'antivirus.state' => ['required_with:antivirus', Rule::enum(AntivirusState::class)],
            'antivirus.definitions_updated_at' => ['nullable', 'date', $latest],
            'antivirus.last_scan_at' => ['nullable', 'date', $latest],
        ]);
        if ($validator->fails()) {
            $service->reject($agent, $request->ip(), (string) $validator->errors()->first());
            throw new ValidationException($validator);
        }
        /** @var array{platform?: string|null, agent_version?: string|null, software: list<array{name: string, version?: string|null, vendor?: string|null, installed_at?: string|null}>, antivirus?: array{product?: string|null, state: string, definitions_updated_at?: string|null, last_scan_at?: string|null}|null} $payload */
        $payload = $validator->validated();
        $count = $service->apply($agent, $payload, $request->ip());

        return ApiResponse::data(['accepted' => true, 'software' => $count]);
    }

    /** Agents are reachable only through a device in the user's scope. */
    private function visible(Request $request, DeviceAgent $agent): DeviceAgent
    {
        abort_unless(Asset::query()->visibleTo($this->user($request))->whereKey($agent->asset_id)->exists(), 404);

        return $agent;
    }

    /** @return array<string, mixed> */
    private function row(DeviceAgent $a): array
    {
        return [
            'id' => $a->id, 'name' => $a->name, 'platform' => $a->platform, 'agent_version' => $a->agent_version, 'is_active' => $a->is_active,
            'last_checkin_at' => $a->last_checkin_at?->toIso8601String(), 'last_ip' => $a->last_ip,
            'asset' => ['id' => $a->asset->id, 'inventory_number' => $a->asset->inventory_number, 'name' => $a->asset->name,
                'organization' => ['id' => $a->asset->organization->id, 'name' => $a->asset->organization->name]],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Requisitions;

use App\Enums\RequisitionDecision;
use App\Http\Controllers\Controller;
use App\Http\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Requisitions\RequisitionService;
use App\Services\Requisitions\RequisitionWorkflow;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** Workflow steps, comments and files of a requisition. */
class RequisitionActionController extends Controller
{
    public function __construct(
        private readonly RequisitionWorkflow $workflow,
        private readonly RequisitionService $service,
        private readonly RequisitionController $cards,
    ) {}

    public function submit(Request $request, Requisition $requisition): JsonResponse
    {
        return $this->card($this->workflow->submit($requisition, $this->user($request)), $request);
    }

    public function orgDecision(Request $request, Requisition $requisition): JsonResponse
    {
        $data = $this->decision($request);

        return $this->card($this->workflow->orgDecide($requisition, $data['decision'], $data['comment'], $this->user($request)), $request);
    }

    public function take(Request $request, Requisition $requisition): JsonResponse
    {
        return $this->card($this->workflow->take($requisition, $this->user($request)), $request);
    }

    public function requestInfo(Request $request, Requisition $requisition): JsonResponse
    {
        return $this->card($this->workflow->requestInfo($requisition, $this->commentText($request), $this->user($request)), $request);
    }

    public function provideInfo(Request $request, Requisition $requisition): JsonResponse
    {
        return $this->card($this->workflow->provideInfo($requisition, $this->commentText($request), $this->user($request)), $request);
    }

    public function decide(Request $request, Requisition $requisition): JsonResponse
    {
        $data = $this->decision($request, true);

        return $this->card($this->workflow->decide($requisition, $data['decision'], $data['comment'], $data['items'], $this->user($request)), $request);
    }

    public function ready(Request $request, Requisition $requisition): JsonResponse
    {
        return $this->card($this->workflow->markReady($requisition, $this->user($request)), $request);
    }

    public function comment(Request $request, Requisition $requisition): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = $this->service->comment($requisition, $data['body'], $this->user($request));

        return ApiResponse::data(['id' => $comment->id, 'body' => $comment->body], 201);
    }

    public function attach(Request $request, Requisition $requisition): JsonResponse
    {
        $request->validate(['files' => ['required', 'array', 'min:1', 'max:5'], 'files.*' => [new SafeUpload]]);
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files'), fn ($f) => $f instanceof UploadedFile));
        $created = $this->service->attach($requisition, $files, $this->user($request));

        return ApiResponse::data(array_map(fn ($a) => RequisitionResource::attachment($a->load(['file', 'uploader'])), $created), 201);
    }

    /** @return array{decision: RequisitionDecision, comment: string|null, items: list<array{id: int, approved_quantity: string|int|float}>} */
    private function decision(Request $request, bool $withItems = false): array
    {
        $data = $request->validate([
            'decision' => ['required', Rule::enum(RequisitionDecision::class)],
            'comment' => ['nullable', 'string', 'max:5000'],
            ...($withItems ? [
                'items' => ['sometimes', 'array', 'max:50'],
                'items.*.id' => ['required', 'integer'],
                'items.*.approved_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            ] : []),
        ]);

        /** @var list<array{id: int, approved_quantity: string|int|float}> $items */
        $items = array_values($data['items'] ?? []);

        return ['decision' => RequisitionDecision::from($data['decision']), 'comment' => $data['comment'] ?? null, 'items' => $items];
    }

    private function commentText(Request $request): ?string
    {
        return $request->validate(['comment' => ['nullable', 'string', 'max:5000']])['comment'] ?? null;
    }

    private function card(Requisition $requisition, Request $request): JsonResponse
    {
        return $this->cards->detail($requisition, $this->user($request));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

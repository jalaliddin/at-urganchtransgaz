<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Rules\SafeUpload;
use App\Services\Documents\DocumentLinkTargets;
use App\Services\Documents\DocumentService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use App\Support\Search\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DocumentController extends Controller
{
    private const RELATIONS = ['organization', 'currentVersion.file', 'currentVersion.uploader'];

    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentLinkTargets $targets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $base = Document::query()->with(self::RELATIONS);
        SearchTerm::whereContains($base, ['number', 'title'], SearchTerm::fromRequest($request));

        $page = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::custom('organization', new OrganizationSubtreeFilter),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('document_date', '>=', (string) $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('document_date', '<=', (string) $v)),
            ])
            ->allowedSorts(['document_date', 'title', 'number', 'created_at'])
            ->defaultSort('-document_date', '-id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, DocumentResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Document::class);
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            ...$this->rules(required: true),
            'file' => ['sometimes', new SafeUpload],
        ]);
        unset($data['file']);

        $document = $this->documents->create($data, $request->file('file'), $this->user($request));

        return ApiResponse::data(new DocumentResource($document->load(self::RELATIONS)), 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('view', $document);
        $resource = new DocumentResource($document->load([...self::RELATIONS, 'versions.file', 'versions.uploader']));
        $resource->links = $this->visibleLinks($document, $this->user($request));

        return ApiResponse::data($resource);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);
        $document->update($request->validate($this->rules(required: false)));

        return ApiResponse::data(new DocumentResource($document->load(self::RELATIONS)));
    }

    public function destroy(Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);
        $document->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'type' => [$req, Rule::enum(DocumentType::class)],
            'number' => ['nullable', 'string', 'max:100'],
            'title' => [$req, 'string', 'max:255'],
            'document_date' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function visibleLinks(Document $document, User $user): array
    {
        return $document->links()->orderBy('id')->get()
            ->map(function (DocumentLink $link) use ($user): ?array {
                $target = $this->targets->find($link->linkable_type, $link->linkable_id, $user);

                return $target === null ? null : [
                    'id' => $link->id, 'type' => $link->linkable_type, 'target' => $this->targets->summary($target),
                ];
            })->filter()->values()->all();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

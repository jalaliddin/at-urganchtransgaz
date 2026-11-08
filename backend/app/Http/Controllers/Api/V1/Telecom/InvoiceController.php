<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Telecom\InvoiceResource;
use App\Models\ItService;
use App\Models\ServiceInvoice;
use App\Models\ServicePayment;
use App\Models\StoredFile;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Files\FileStore;
use App\Services\Telecom\InvoiceService;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Service invoices of the user's organizations (global scope): entry (services.invoice), central approval
 * (services.approve), payments (services.pay).
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('services.use');
        $query = ServiceInvoice::query();
        $this->filter($query, $request);
        $totals = (clone $query)->toBase()->selectRaw('COALESCE(SUM(planned_amount), 0) AS planned, COALESCE(SUM(amount), 0) AS invoiced, COALESCE(SUM(paid_amount), 0) AS paid')->first();
        $page = $query->with(InvoiceResource::RELATIONS)->orderBy('due_date')->orderBy('id')->paginate(Pagination::perPage($request))->withQueryString();

        $response = ApiResponse::page($page, fn (ServiceInvoice $i) => InvoiceResource::invoice($i));
        $payload = (array) $response->getData(true);
        $payload['summary'] = [
            'planned' => Decimal::of((string) ($totals->planned ?? '0'), 2),
            'invoiced' => Decimal::of((string) ($totals->invoiced ?? '0'), 2),
            'paid' => Decimal::of((string) ($totals->paid ?? '0'), 2),
        ];

        return $response->setData($payload);
    }

    public function show(ServiceInvoice $serviceInvoice): JsonResponse
    {
        Gate::authorize('services.use');

        return $this->detail($serviceInvoice);
    }

    /** Invoice data as received from the provider. */
    public function update(Request $request, ServiceInvoice $serviceInvoice): JsonResponse
    {
        Gate::authorize('services.invoice');
        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'due_date' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->detail($this->invoices->enter($serviceInvoice, $data, $this->user($request)));
    }

    /** Additional one-off cost of a service. */
    public function storeExtra(Request $request, ItService $itService): JsonResponse
    {
        Gate::authorize('services.invoice');
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'due_date' => ['required', 'date'],
            'invoice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->detail($this->invoices->createExtra($itService, $data, $this->user($request)), 201);
    }

    public function approve(Request $request, ServiceInvoice $serviceInvoice): JsonResponse
    {
        Gate::authorize('services.approve');

        return $this->detail($this->invoices->approve($serviceInvoice, $this->user($request)));
    }

    public function pay(Request $request, ServiceInvoice $serviceInvoice): JsonResponse
    {
        Gate::authorize('services.pay');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->invoices->pay($serviceInvoice, $data, $this->user($request));

        return $this->detail($serviceInvoice, 201);
    }

    public function destroy(ServiceInvoice $serviceInvoice): JsonResponse
    {
        Gate::authorize('services.manage');
        $this->invoices->delete($serviceInvoice);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function destroyPayment(ServicePayment $servicePayment): JsonResponse
    {
        Gate::authorize('services.pay');
        $this->visibleInvoice($servicePayment);

        return $this->detail($this->invoices->deletePayment($servicePayment));
    }

    /** Scanned invoice; replaces the previous file. */
    public function attachFile(Request $request, ServiceInvoice $serviceInvoice, FileStore $files): JsonResponse
    {
        Gate::authorize('services.invoice');
        $this->replaceFile($request, $files, $serviceInvoice, $serviceInvoice->organization_id);

        return $this->detail($serviceInvoice->refresh());
    }

    /** Payment order / receipt; replaces the previous file. */
    public function attachPaymentFile(Request $request, ServicePayment $servicePayment, FileStore $files): JsonResponse
    {
        Gate::authorize('services.pay');
        $invoice = $this->visibleInvoice($servicePayment);
        $this->replaceFile($request, $files, $servicePayment, $invoice->organization_id);

        return $this->detail($invoice);
    }

    /** @param  Builder<ServiceInvoice>  $query */
    private function filter(Builder $query, Request $request): void
    {
        $status = InvoiceStatus::tryFrom((string) $request->input('filter.status'));
        if ($status !== null) {
            $query->where('status', $status->value);
        } elseif ($request->input('filter.status') === 'open') {
            $query->where('status', '!=', InvoiceStatus::Paid->value);
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        if ($request->filled('filter.service')) {
            $query->where('service_id', $request->integer('filter.service'));
        }
        $type = ServiceType::tryFrom((string) $request->input('filter.type'));
        if ($type !== null || $request->filled('filter.provider')) {
            $query->whereIn('service_id', ItService::query()->withoutGlobalScopes()->select('id')
                ->when($type !== null, fn ($q) => $q->where('type', $type?->value))
                ->when($request->filled('filter.provider'), fn ($q) => $q->where('provider_id', $request->integer('filter.provider'))));
        }
        $period = (string) $request->input('filter.period');
        if (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            $month = CarbonImmutable::parse($period.'-01');
            $query->where('period_start', '<=', $month->endOfMonth()->toDateString())->where('period_end', '>=', $month->toDateString());
        }
        foreach (['due_from' => '>=', 'due_to' => '<='] as $filter => $operator) {
            if ($request->filled("filter.{$filter}")) {
                $query->where('due_date', $operator, CarbonImmutable::parse((string) $request->input("filter.{$filter}"))->toDateString());
            }
        }
        match ((string) $request->input('filter.approval')) {
            'pending' => $query->whereNotNull('amount')->whereNull('approved_at'),
            'approved' => $query->whereNotNull('approved_at'),
            default => null,
        };
    }

    private function replaceFile(Request $request, FileStore $files, ServiceInvoice|ServicePayment $owner, int $organizationId): void
    {
        $request->validate(['file' => ['required', new SafeUpload]]);
        $upload = $request->file('file');
        abort_unless($upload instanceof UploadedFile, 422);
        $old = $owner->file_id ? StoredFile::query()->find($owner->file_id) : null;
        $stored = $files->store($upload, $organizationId, $this->user($request));
        DB::transaction(fn () => $owner->forceFill(['file_id' => $stored->id])->save());
        if ($old !== null) {
            $files->delete($old);
        }
    }

    /** A payment is reachable only through an invoice in the user's scope. */
    private function visibleInvoice(ServicePayment $payment): ServiceInvoice
    {
        return ServiceInvoice::query()->findOrFail($payment->invoice_id);
    }

    private function detail(ServiceInvoice $invoice, int $status = 200): JsonResponse
    {
        $invoice->refresh()->load([...InvoiceResource::RELATIONS, 'payments' => fn ($q) => $q->with(['creator', 'file'])->orderByDesc('paid_at')->orderByDesc('id')]);

        return ApiResponse::data([
            ...InvoiceResource::invoice($invoice),
            'payments' => $invoice->payments->map(fn (ServicePayment $p) => InvoiceResource::payment($p))->values(),
        ], $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

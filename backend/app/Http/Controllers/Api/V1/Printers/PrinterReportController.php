<?php

namespace App\Http\Controllers\Api\V1\Printers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Printers\PrinterReports;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Cartridge and printer consumption analysis (design P9). */
class PrinterReportController extends Controller
{
    /** Longest period the monthly breakdown covers. */
    private const MAX_MONTHS = 36;

    public function __invoke(Request $request, PrinterReports $reports): JsonResponse
    {
        Gate::authorize('printers.view');
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return ApiResponse::data($reports->report($user, self::validated($request)));
    }

    /** @return array<string, mixed> */
    public static function validated(Request $request): array
    {
        $validator = validator($request->query(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'group_by' => ['nullable', Rule::in(PrinterReports::GROUPS)],
            'organization' => ['nullable', 'integer'],
        ]);
        $validator->after(function (Validator $v) use ($request): void {
            if ($v->errors()->isNotEmpty() || ! $request->filled('date_from') || ! $request->filled('date_to')) {
                return;
            }
            $months = CarbonImmutable::parse((string) $request->query('date_from'))->startOfMonth()
                ->diffInMonths(CarbonImmutable::parse((string) $request->query('date_to'))->startOfMonth());
            if ($months >= self::MAX_MONTHS) {
                $v->errors()->add('date_to', __('messages.report_period_too_long', ['months' => self::MAX_MONTHS]));
            }
        });

        return $validator->validate();
    }
}

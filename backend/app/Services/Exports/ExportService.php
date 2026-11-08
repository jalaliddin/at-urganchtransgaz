<?php

namespace App\Services\Exports;

use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Registry of exportable reports and the entry point for export requests. */
class ExportService
{
    public const FORMATS = ['xlsx', 'pdf'];

    /** @var array<string, class-string<ReportDefinition>> */
    public const REPORTS = [
        'helpdesk_tickets' => TicketReport::class,
        'assets' => AssetReport::class,
        'stock_balances' => StockBalanceReport::class,
        'stock_movements' => StockMovementReport::class,
    ];

    public function report(string $type): ReportDefinition
    {
        $class = self::REPORTS[$type] ?? throw new InvalidArgumentException("Unknown report [{$type}].");

        return app($class);
    }

    /** @param  array<string, mixed>  $filters */
    public function request(User $user, string $type, string $format, array $filters): ReportExport
    {
        $export = ReportExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => $type,
            'format' => $format,
            'filters' => $filters,
            'status' => ExportStatus::Pending,
        ]);

        GenerateExportJob::dispatch($export->id);

        return $export->refresh();
    }
}

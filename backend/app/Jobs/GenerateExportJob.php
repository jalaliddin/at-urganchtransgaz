<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use App\Services\Exports\ExportService;
use App\Services\Exports\ExportTooLargeException;
use App\Services\Exports\TableWriter;
use App\Services\Files\FileStore;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds an export file. Runs without an authenticated user, so the report
 * applies the requesting user's scope itself (Ticket::visibleTo).
 */
class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $exportId) {}

    public function handle(ExportService $exports, TableWriter $writer, FileStore $files): void
    {
        $export = ReportExport::query()->find($this->exportId);
        $user = $export !== null ? User::query()->withoutGlobalScopes()->find($export->user_id) : null;
        if ($export === null || $export->status !== ExportStatus::Pending) {
            return;
        }
        if ($user === null || ! $user->is_active) {
            $export->forceFill(['status' => ExportStatus::Failed, 'error' => __('exports.failed'), 'finished_at' => CarbonImmutable::now()])->save();

            return;
        }

        $export->forceFill(['status' => ExportStatus::Processing, 'started_at' => CarbonImmutable::now()])->save();
        $locale = $user->locale;

        try {
            $report = $exports->report($export->type);
            $title = $report->title($locale);
            $rows = $report->rows($user, (array) $export->filters, $locale);
            $result = $export->format === 'pdf'
                ? $writer->pdf($title, $report->columns($locale), $rows, $locale)
                : $writer->xlsx($title, $report->columns($locale), $rows);

            $name = sprintf('%s_%s.%s', $export->type, now((string) config('app.display_timezone'))->format('Y-m-d_H-i'), $export->format);
            $mime = $export->format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $file = $files->storeContents($result['contents'], $name, $mime, null, $user);

            $export->forceFill([
                'status' => ExportStatus::Done, 'stored_file_id' => $file->id,
                'row_count' => $result['rows'], 'finished_at' => CarbonImmutable::now(),
            ])->save();
            $user->notify(new ExportReadyNotification($export));
        } catch (ExportTooLargeException $e) {
            $export->forceFill(['status' => ExportStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => CarbonImmutable::now()])->save();
        } catch (Throwable $e) {
            Log::error('Export failed', ['export' => $export->uuid, 'exception' => $e]);
            $export->forceFill(['status' => ExportStatus::Failed, 'error' => __('exports.failed', [], $locale), 'finished_at' => CarbonImmutable::now()])->save();
        }
    }
}

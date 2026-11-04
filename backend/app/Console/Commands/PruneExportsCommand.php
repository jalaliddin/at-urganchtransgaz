<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use App\Services\Files\FileStore;
use Illuminate\Console\Command;

/** Exports are short-lived: remove them (and their files) after a week. */
class PruneExportsCommand extends Command
{
    public const KEEP_DAYS = 7;

    protected $signature = 'exports:prune';

    protected $description = 'Delete report exports older than '.self::KEEP_DAYS.' days';

    public function handle(FileStore $files): int
    {
        ReportExport::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->with('file')
            ->chunkById(100, function ($exports) use ($files): void {
                foreach ($exports as $export) {
                    $file = $export->file;
                    $export->delete();
                    if ($file !== null) {
                        $files->delete($file);
                    }
                }
            });

        return self::SUCCESS;
    }
}

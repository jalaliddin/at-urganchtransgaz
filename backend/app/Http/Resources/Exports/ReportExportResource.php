<?php

namespace App\Http\Resources\Exports;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportExport */
class ReportExportResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportExport $export */
        $export = $this->resource;

        return [
            'uuid' => $export->uuid,
            'type' => $export->type,
            'format' => $export->format,
            'filters' => (object) ($export->filters ?? []),
            'status' => $export->status->value,
            'row_count' => $export->row_count,
            'error' => $export->error,
            'file' => $export->file ? [
                'uuid' => $export->file->uuid, 'name' => $export->file->original_name, 'size' => $export->file->size,
            ] : null,
            'created_at' => $this->iso($export->created_at),
            'finished_at' => $this->iso($export->finished_at),
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use App\Models\TicketSlaPolicy;
use Illuminate\Database\Seeder;

/**
 * Reference data for the Helpdesk in every environment. Idempotent: only missing
 * rows are created, so administrators' edits survive a reseed.
 */
class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{code: string, name: string, name_ru: string}> $categories */
        $categories = config('helpdesk.categories');
        foreach ($categories as $index => $category) {
            TicketCategory::firstOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'name_ru' => $category['name_ru'], 'sort_order' => ($index + 1) * 10],
            );
        }

        /** @var array<string, array{int, int}> $sla */
        $sla = config('helpdesk.default_sla');
        foreach ($sla as $priority => [$response, $resolution]) {
            TicketSlaPolicy::firstOrCreate(
                ['priority' => $priority, 'category_id' => null],
                ['response_minutes' => $response, 'resolution_minutes' => $resolution],
            );
        }
    }
}

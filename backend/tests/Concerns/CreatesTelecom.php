<?php

namespace Tests\Concerns;

use App\Enums\SimStatus;
use App\Models\ItService;
use App\Models\Organization;
use App\Models\ServiceProvider;
use App\Models\SimCard;

/** SIM card, provider and service fixtures. */
trait CreatesTelecom
{
    protected function operator(string $name = 'Beeline'): ServiceProvider
    {
        return ServiceProvider::query()->firstOrCreate(['name' => $name], ['is_mobile_operator' => true]);
    }

    protected function provider(string $name = 'Sarkor Telecom'): ServiceProvider
    {
        return ServiceProvider::query()->firstOrCreate(['name' => $name], ['is_mobile_operator' => false]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function simIn(Organization $organization, array $attributes = []): SimCard
    {
        $card = new SimCard([
            'organization_id' => $organization->id,
            'provider_id' => $this->operator()->id,
            'phone_number' => '99890'.fake()->unique()->numerify('#######'),
            'status' => SimStatus::InStock,
            ...$attributes,
        ]);
        $card->setIccid($attributes['iccid'] ?? '8999801'.fake()->unique()->numerify('############'));
        $card->save();

        return $card;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function serviceIn(Organization $organization, array $attributes = []): ItService
    {
        return ItService::query()->create([
            'organization_id' => $organization->id, 'provider_id' => $this->provider()->id, 'type' => 'internet',
            'name' => 'Internet 100 Mbit/s', 'fee' => '1500000.00', 'billing_cycle' => 'monthly', 'due_day' => 10,
            ...$attributes,
        ]);
    }
}

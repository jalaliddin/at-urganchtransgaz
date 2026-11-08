<?php

namespace App\Services\Sim;

use App\Enums\SimEvent;
use App\Enums\SimStatus;
use App\Exceptions\DomainConflictException;
use App\Models\SimCard;
use App\Models\SimHistory;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Registering and editing SIM cards; every change is written to the card's history (never the ICCID itself). */
class SimCards
{
    /** Fields whose changes are listed in the history payload. */
    private const TRACKED = ['provider_id', 'phone_number', 'tariff', 'account_number', 'monthly_fee', 'note'];

    /** @param  array<string, mixed>  $data  organization_id, provider_id, phone_number, iccid, tariff, account_number, monthly_fee, note */
    public function register(array $data, User $by): SimCard
    {
        return $this->guarded(fn () => DB::transaction(function () use ($data, $by): SimCard {
            $card = new SimCard([...$this->fields($data), 'organization_id' => $data['organization_id'], 'status' => SimStatus::InStock]);
            $this->applyNumbers($card, $data);
            $card->save();
            $this->record($card, SimEvent::Registered, $by, ['phone_number' => $card->phone_number, 'iccid_last4' => $card->iccid_last4]);

            return $card;
        }));
    }

    /** @param  array<string, mixed>  $data  provider_id, phone_number, tariff, account_number, monthly_fee, note */
    public function update(SimCard $card, array $data, User $by): SimCard
    {
        return $this->guarded(fn () => DB::transaction(function () use ($card, $data, $by): SimCard {
            $locked = SimCard::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($card->id);
            $locked->fill($this->fields($data));
            if (array_key_exists('phone_number', $data)) {
                $this->applyNumbers($locked, ['phone_number' => $data['phone_number']]);
            }
            $changes = [];
            foreach (self::TRACKED as $field) {
                if ($locked->isDirty($field)) {
                    $changes[$field] = ['from' => $locked->getOriginal($field), 'to' => $locked->getAttribute($field)];
                }
            }
            $locked->save();
            if ($changes !== []) {
                $this->record($locked, SimEvent::Updated, $by, $changes);
            }

            return $locked;
        }));
    }

    /** Only a card registered by mistake: still in stock, never assigned or allocated. */
    public function delete(SimCard $card): void
    {
        DB::transaction(function () use ($card): void {
            $locked = SimCard::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($card->id);
            $used = $locked->status !== SimStatus::InStock
                || $locked->assignments()->exists()
                || DB::table('requisition_fulfillments')->where('sim_card_id', $locked->id)->exists()
                || $locked->histories()->whereNotIn('event', [SimEvent::Registered->value, SimEvent::Updated->value])->exists();
            if ($used) {
                throw new DomainConflictException(__('messages.sim_in_use'));
            }
            $locked->delete();
        });
    }

    /** @param  array<string, mixed>  $payload */
    public function record(SimCard $card, SimEvent $event, ?User $by, array $payload = [], ?int $requisitionId = null): SimHistory
    {
        return SimHistory::query()->create([
            'sim_card_id' => $card->id, 'event' => $event, 'organization_id' => $card->organization_id,
            'payload' => $payload === [] ? null : $payload, 'requisition_id' => $requisitionId, 'user_id' => $by?->id,
        ]);
    }

    /**
     * Normalises the phone number and ICCID and checks they are not on another live card.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyNumbers(SimCard $card, array $data): void
    {
        if (array_key_exists('phone_number', $data)) {
            $phone = SimCard::normalizePhone(is_string($data['phone_number']) ? $data['phone_number'] : null);
            if ($phone === null) {
                throw ValidationException::withMessages(['phone_number' => __('messages.sim_phone_invalid')]);
            }
            $taken = SimCard::query()->withoutGlobalScopes()->where('phone_key', $phone)->when($card->exists, fn ($q) => $q->whereKeyNot($card->id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['phone_number' => __('messages.sim_phone_taken')]);
            }
            $card->phone_number = $phone;
        }
        if (array_key_exists('iccid', $data)) {
            $raw = is_string($data['iccid']) ? trim($data['iccid']) : '';
            $iccid = $raw === '' ? null : SimCard::normalizeIccid($raw);
            if ($raw !== '' && $iccid === null) {
                throw ValidationException::withMessages(['iccid' => __('messages.sim_iccid_invalid')]);
            }
            if ($iccid !== null && SimCard::query()->withoutGlobalScopes()->where('iccid_hash', SimCard::iccidHash($iccid))
                ->when($card->exists, fn ($q) => $q->whereKeyNot($card->id))->exists()) {
                throw ValidationException::withMessages(['iccid' => __('messages.sim_iccid_taken')]);
            }
            $card->setIccid($iccid);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return array_intersect_key($data, array_flip(['provider_id', 'tariff', 'account_number', 'monthly_fee', 'note']));
    }

    /**
     * Two saves racing on the same number or ICCID: the unique index decides, the loser gets a 422.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function guarded(callable $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintViolationException $e) {
            $field = str_contains($e->getMessage(), 'iccid') ? 'iccid' : 'phone_number';
            throw ValidationException::withMessages([$field => __('messages.sim_'.($field === 'iccid' ? 'iccid' : 'phone').'_taken')]);
        }
    }
}

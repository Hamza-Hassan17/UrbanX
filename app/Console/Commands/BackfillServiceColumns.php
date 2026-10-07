<?php

namespace App\Console\Commands;

use App\Models\PromoCode;
use App\Models\Ride;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Phase 4 of the admin workspace split. Backfills the new `service`
 * column from whatever relation actually exists -- nothing is guessed.
 *
 * - transactions: booking_id is only ever a chauffeur/rental booking
 *   (confirmed: no other table references it), so a non-null booking_id
 *   means service='rental'. A null booking_id is left null -- it's either
 *   a genuinely unlinked transaction or one whose booking was later
 *   hard-deleted (the known orphaned-transactions issue).
 * - promo_codes: promo_code_id is only ever referenced from `rides`
 *   (confirmed: no other table references it), so a promo code backfills
 *   to service='ride' only if EVERY ride that used it was ride_type='ride'.
 *   If it was ever used by a delivery ride too, or never used at all,
 *   it's left null rather than guessed.
 * - complains: has no ride/order/booking relation of any kind -- there is
 *   nothing to backfill from. Every existing complaint stays null-service
 *   (shown only in Platform, same as any other null-service row).
 */
class BackfillServiceColumns extends Command
{
    protected $signature = 'workspace:backfill-service-columns {--apply : Write changes instead of only listing them}';

    protected $description = 'Backfill the service column on complains/transactions/promo_codes from existing relations';

    public function handle(): int
    {
        $this->backfillTransactions();
        $this->backfillPromoCodes();

        $this->comment('complains: nothing to backfill -- no ride/order/booking relation exists on that table.');

        if (!$this->option('apply')) {
            $this->comment('Dry run only. Re-run with --apply to write these changes.');
        }

        return self::SUCCESS;
    }

    private function backfillTransactions(): void
    {
        $count = Transaction::whereNull('service')->whereNotNull('booking_id')->count();
        $this->info("Transactions to backfill as 'rental': {$count}");

        if ($this->option('apply') && $count > 0) {
            Transaction::whereNull('service')->whereNotNull('booking_id')->update(['service' => 'rental']);
        }
    }

    private function backfillPromoCodes(): void
    {
        $promoCodes = PromoCode::whereNull('service')->get(['id', 'name', 'code']);
        $toBackfill = [];

        foreach ($promoCodes as $promoCode) {
            $rideTypes = Ride::where('promo_code_id', $promoCode->id)->distinct()->pluck('ride_type');

            if ($rideTypes->count() === 1 && $rideTypes->first() === 'ride') {
                $toBackfill[] = $promoCode->id;
                $this->line("Promo code #{$promoCode->id} ({$promoCode->code}) -> service='ride'");
            }
        }

        $this->info('Promo codes to backfill: ' . count($toBackfill));

        if ($this->option('apply') && !empty($toBackfill)) {
            PromoCode::whereIn('id', $toBackfill)->update(['service' => 'ride']);
        }
    }
}

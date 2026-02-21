<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;

final class ReconcileProviderPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile payouts in Processing, Unknown, or ReconciliationRequired statuses';

    /**
     * Execute the console command.
     */
    public function handle(\App\Domains\Payouts\Actions\ProcessPayout $processPayout)
    {
        $this->info('Starting payout reconciliation...');

        \App\Models\Payout::query()
            ->whereIn('status', [
                \App\Enums\PayoutStatus::Processing,
                \App\Enums\PayoutStatus::Unknown,
                \App\Enums\PayoutStatus::ReconciliationRequired,
            ])
            ->chunkById(50, function ($payouts) use ($processPayout) {
                foreach ($payouts as $payout) {
                    $this->comment("Reconciling payout {$payout->id} (Ref: {$payout->reference})...");

                    try {
                        $processPayout->execute($payout);

                        if ($payout->refresh()->status->is(\App\Enums\PayoutStatus::Success)) {
                            $this->info("Payout {$payout->id} resolved to Success.");
                        } elseif ($payout->status->is(\App\Enums\PayoutStatus::Failed)) {
                            $this->warn("Payout {$payout->id} resolved to Failed.");
                        } else {
                            $this->line("Payout {$payout->id} still in {$payout->status->value} status.");
                        }
                    } catch (Exception $e) {
                        $this->error("Failed to reconcile payout {$payout->id}: ".$e->getMessage());
                    }
                }
            });

        $this->info('Payout reconciliation completed.');
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class SyncPaymentProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:providers-sync';

    /**
     * The console command description.
     *
     * @var string
     *
     * @phpstan-ignore-next-line
     */
    protected $description = 'Synchronize payment providers from configuration to the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $providers = config('payment.providers', []);

        if (empty($providers)) {
            $this->warn('No providers found in configuration (payment.providers).');

            return 0;
        }

        $this->info('Starting provider synchronization...');

        foreach ($providers as $providerData) {
            $provider = \App\Models\Provider::updateOrCreate(
                ['identifier' => $providerData['identifier']],
                [
                    'name' => $providerData['name'],
                    'is_active' => $providerData['is_active'] ?? true,
                    'is_healthy' => $providerData['is_healthy'] ?? true,
                    'supported_channels' => $providerData['supported_channels'] ?? [],
                    'metadata' => $providerData['metadata'] ?? null,
                ]
            );

            if ($provider->wasRecentlyCreated) {
                $this->line("Created provider: <info>{$provider->name}</info> ({$provider->identifier})");
            } else {
                $this->line("Updated provider: <comment>{$provider->name}</comment> ({$provider->identifier})");
            }
        }

        $this->info('Provider synchronization completed successfully.');

        return 0;
    }
}

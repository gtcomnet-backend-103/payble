<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Businesses\Actions\GenerateApiKeys;
use App\Enums\PaymentMode;
use App\Models\Business;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ApiSettings extends Page
{
    public ?array $data = [];

    protected string $view = 'filament.pages.api-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public function mount(): void
    {
        /** @var Business $tenant */
        $tenant = Filament::getTenant();

        $this->form->fill([
            'webhook_url' => $tenant->webhook_url,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('webhook_url')
                    ->label('Webhook URL')
                    ->url()
                    ->placeholder(' https://example.com/webhook')
                    ->helperText('This URL will receive payment event notifications.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var Business $tenant */
        $tenant = Filament::getTenant();

        $tenant->update([
            'webhook_url' => $data['webhook_url'],
        ]);

        Notification::make()
            ->title('Webhook URL updated successfully')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        /** @var Business $tenant */
        $tenant = Filament::getTenant();

        return [
            Action::make('generateTestKeys')
                ->label('Regenerate Test Keys')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Test API Keys')
                ->modalDescription('This will regenerate test API keys for this business. Existing test keys will be invalidated.')
                ->action(function () use ($tenant): void {
                    (new GenerateApiKeys())->execute($tenant, PaymentMode::Test);

                    Notification::make()
                        ->title('Test API keys regenerated successfully')
                        ->success()
                        ->send();
                }),

            Action::make('generateLiveKeys')
                ->label('Regenerate Live Keys')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Live API Keys')
                ->modalDescription('This will regenerate live API keys for this business. Existing live keys will be invalidated.')
                ->disabled(fn (): bool => ! $tenant->isVerified())
                ->tooltip(fn (): ?string => ! $tenant->isVerified() ? 'Business must be verified to generate live keys' : null)
                ->action(function () use ($tenant): void {
                    (new GenerateApiKeys())->execute($tenant, PaymentMode::Live);

                    Notification::make()
                        ->title('Live API keys regenerated successfully')
                        ->success()
                        ->send();
                }),
        ];
    }
}

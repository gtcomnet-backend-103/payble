<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Payouts\Actions\CreateRecipient;
use App\Enums\Currency;
use App\Models\Business;
use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\NoReturn;

final class BankAccountSettings extends Page
{
    use InteractsWithSchemas;

    public array $data = [];

    protected string $view = 'filament.pages.bank-account-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    public function mount(): void
    {
        $this->data = [
            'account_number' => Filament::getTenant()?->bankAccount?->account_number,
            'bank_code' => Filament::getTenant()?->bankAccount?->bank_code,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('account_number')
                ->placeholder('Enter your ten(10) digit account number'),
            Select::make('bank_code')
                ->options(fn () => Cache::remember('bank-lists.business.form', now()->addMonth(), function () {
                    $response = Http::withToken(config('services.paystack.secret'))
                        ->get('https://api.paystack.co/bank')
                        ->json();

                    $banks = $response['data'] ?? [];
                    $banks[] = [
                        'code' => '001',
                        'name' => 'Test Bank',
                    ];

                    return array_reduce($banks, function ($acc, $bank) {
                        $acc[$bank['code']] = $bank['name'];

                        return $acc;
                    }, []);
                }))
                ->placeholder('Select bank'),

        ])->statePath('data');
    }

    public function getChildSchema(Schema $schema): Schema
    {
        return $this->form($schema);
    }

    #[NoReturn]
    public function save(): void
    {
        try {
            /** @var Business $business */
            $business = Filament::getTenant();
            app(CreateRecipient::class)->execute($business, ['currency' => Currency::NGN->value, ...$this->data]);
            Notification::make()->title('Account updated')->success()->send();
        } catch (Exception $exception) {
            Log::error($exception);
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}

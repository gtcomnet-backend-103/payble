<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Webhook URL Form --}}
        <x-filament::section>
            <x-slot name="heading">
                Webhook Configuration
            </x-slot>

            <x-slot name="description">
                Configure the webhook URL to receive payment event notifications.
            </x-slot>

            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button type="submit">
                        Save Changes
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- API Keys Display --}}
        <x-filament::section>
            <x-slot name="heading">
                API Keys
            </x-slot>

            <x-slot name="description">
                Your API keys for authenticating requests. Keep these secure and never share them publicly.
            </x-slot>

            @php
                $tenant = \Filament\Facades\Filament::getTenant();
                $apiTokens = $tenant->apiTokens;
            @endphp

            <div class="space-y-4">
                @foreach([App\Enums\PaymentMode::Live, App\Enums\PaymentMode::Test] as $mode)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h3 class="text-sm font-semibold mb-3">
                            {{ $mode === App\Enums\PaymentMode::Live ? 'Live' : 'Test' }} Keys
                        </h3>

                        @if($mode === App\Enums\PaymentMode::Live && !$tenant->isVerified())
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3 mb-3">
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Business Verification Required</p>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">Your business must be verified before you can generate live API keys. Please contact support to complete verification.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-3">
                            @foreach([App\Enums\AccessLevel::Public, App\Enums\AccessLevel::Secret] as $level)
                                @php
                                    $token = $apiTokens->where('mode', $mode)->where('access_level', $level)->first();
                                    $key = $token ? App\Domains\Businesses\Actions\GenerateApiKeys::formatKey($token) : 
                                           ($mode === App\Enums\PaymentMode::Live && !$tenant->isVerified() ? 'Verification required' : 'Not generated');
                                @endphp

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ $level === App\Enums\AccessLevel::Public ? 'Public' : 'Secret' }} Key
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded text-sm font-mono break-all">
                                            {{ $key }}
                                        </code>
                                        @if($token)
                                            <x-filament::button
                                                color="gray"
                                                size="sm"
                                                x-data
                                                x-on:click="
                                                    navigator.clipboard.writeText('{{ $key }}');
                                                    $tooltip('Copied!', { timeout: 2000 });
                                                "
                                            >
                                                Copy
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <x-filament::section.description>
                    Use your public keys for client-side operations and secret keys for server-side operations.
                    Test keys allow you to simulate payments without processing real transactions.
                </x-filament::section.description>
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>

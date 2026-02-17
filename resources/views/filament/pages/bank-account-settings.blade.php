<x-filament-panels::page>
    <x-filament::section>
        <form class="space-y-4" wire:submit="save">
            {{ $this->form }}
            <x-filament::button type="submit">
                Save Changes
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>

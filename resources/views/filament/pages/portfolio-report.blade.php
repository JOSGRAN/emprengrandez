<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php($totals = $this->getTotals())

        <x-filament::grid :default="1" :sm="3" class="gap-4">
            <x-filament::section>
                <div class="text-sm text-gray-500">Total prestado (rango)</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['loaned'], 2, '.', ',') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Total recuperado (rango)</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['recovered'], 2, '.', ',') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Total pendiente (actual)</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['pending'], 2, '.', ',') }}</div>
            </x-filament::section>
        </x-filament::grid>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

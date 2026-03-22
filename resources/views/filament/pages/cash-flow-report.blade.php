<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php($totals = $this->getTotals())

        <x-filament::grid :default="1" :sm="3" class="gap-4">
            <x-filament::section>
                <div class="text-sm text-gray-500">Ingresos</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['income'], 2, '.', ',') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Egresos</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['expense'], 2, '.', ',') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Neto</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['net'], 2, '.', ',') }}</div>
            </x-filament::section>
        </x-filament::grid>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

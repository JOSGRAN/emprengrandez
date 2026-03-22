<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php($totals = $this->getTotals())

        <x-filament::grid :default="1" :sm="3" class="gap-4">
            <x-filament::section>
                <div class="text-sm text-gray-500">Clientes morosos</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['customers'], 0, '.', ',') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Cuotas vencidas</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['overdue_installments'], 0, '.', ',') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">Total en mora</div>
                <div class="text-2xl font-semibold">S/ {{ number_format($totals['overdue_amount'], 2, '.', ',') }}</div>
            </x-filament::section>
        </x-filament::grid>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

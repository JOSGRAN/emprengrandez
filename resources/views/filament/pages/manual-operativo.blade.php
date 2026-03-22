<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="space-y-2">
                <div class="text-lg font-semibold">Objetivo</div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Usar Emprengrandez como sistema operativo del negocio: inventario real, ventas financiadas, cobranza y caja real (billeteras).
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-lg font-semibold">Checklist inicial</div>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="font-medium">Configuración</div>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                        <li>Moneda: PEN</li>
                        <li>Billetera por defecto: Caja principal</li>
                        <li>WhatsApp: plantillas activas y notificaciones habilitadas</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="font-medium">Inventario</div>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                        <li>Categorías</li>
                        <li>Productos</li>
                        <li>Variantes (talla / color) con stock y precio</li>
                    </ul>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-lg font-semibold">Reglas de oro</div>
            <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-gray-600 dark:text-gray-300">
                <li>La billetera es tu dinero real disponible. Todo ingreso/egreso debe pasar por una billetera.</li>
                <li>Un crédito es una venta financiada: debe tener productos, variantes y cantidades.</li>
                <li>No se crean créditos si el cliente tiene cuotas vencidas.</li>
                <li>Primero cobras, luego registras el resto. La cobranza manda la caja.</li>
            </ul>
        </x-filament::section>

        <x-filament::section>
            <div class="text-lg font-semibold">Rutina diaria (15 minutos)</div>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="font-medium">Durante el día</div>
                    <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                        <li>Registrar pagos (preferir Pago rápido).</li>
                        <li>Registrar gastos al momento y con billetera.</li>
                        <li>Revisar cuotas vencidas y priorizar cobranza.</li>
                    </ol>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="font-medium">Cierre del día</div>
                    <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                        <li>Pagos del día OK (sin pendientes).</li>
                        <li>Gastos del día OK.</li>
                        <li>Saldo de billeteras revisado.</li>
                        <li>Vencidas / por vencer revisadas.</li>
                    </ol>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-lg font-semibold">10 recomendaciones rápidas</div>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">1) Configura moneda, billetera por defecto y WhatsApp.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">2) Registra inventario primero (categorías → productos → variantes).</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">3) No crees créditos sin productos.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">4) Cobra con Pago rápido: cuota más antigua primero.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">5) Revisa morosidad a diario.</div>
                </div>
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">6) Todo gasto debe tener billetera asignada.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">7) No edites montos “a mano”: usa los flujos del sistema.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">8) Usa WhatsApp solo para por vencer / vencidas.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">9) Revisa saldo de billeteras antes de gastar.</div>
                    <div class="rounded-xl border border-gray-200 bg-white/50 p-4 dark:border-white/10 dark:bg-white/5">10) Cierre diario: pagos + gastos + saldo + vencidas.</div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>


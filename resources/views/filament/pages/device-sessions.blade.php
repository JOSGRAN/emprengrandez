<x-filament-panels::page>
    <x-filament::section>
        <div class="space-y-2">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Aquí ves los dispositivos donde se ha usado tu cuenta. Si ves algo que no reconoces, cambia tu contraseña.
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($sessions as $session)
                    <div class="flex flex-col gap-2 p-4 md:flex-row md:items-center md:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <div class="font-medium">
                                    {{ $session->device_label }}
                                </div>
                                @if ($currentSessionId && $session->session_id === $currentSessionId)
                                    <x-filament::badge color="success">Este dispositivo</x-filament::badge>
                                @elseif ($session->isActive($activeMinutes))
                                    <x-filament::badge color="primary">Activo</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Inactivo</x-filament::badge>
                                @endif
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                IP: {{ $session->ip_address ?? '-' }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 md:justify-end">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                Última actividad: {{ optional($session->last_seen_at)->format('d/m/Y H:i') }}
                            </div>

                            @if (! $currentSessionId || $session->session_id !== $currentSessionId)
                                <x-filament::button
                                    size="sm"
                                    color="danger"
                                    x-on:click="if (confirm('¿Cerrar sesión en este dispositivo?')) { $wire.revokeSession({{ $session->id }}) }"
                                >
                                    Cerrar
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-sm text-gray-600 dark:text-gray-300">
                        Aún no hay registros de sesiones para tu cuenta.
                    </div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>

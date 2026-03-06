<x-filament-panels::page>

    {{-- Channel picker --}}
    <div class="flex flex-wrap gap-2">
        @forelse ($this->availableChannels() as $ch)
            <button
                wire:click="selectChannel('{{ $ch }}')"
                wire:loading.class="pointer-events-none opacity-50"
                wire:target="selectChannel"
                @class([
                    'flex cursor-pointer select-none items-center gap-2 rounded-lg border-2 px-5 py-2.5 text-sm font-semibold shadow-sm transition-all duration-150',
                    // Active
                    'border-amber-500 bg-amber-500 text-white shadow-amber-300/50 dark:shadow-amber-800/40'
                        => $this->activeChannel === $ch,
                    // Inactive — clearly looks like a clickable button
                    'border-gray-200 bg-white text-gray-500 hover:border-amber-400 hover:bg-amber-50 hover:text-amber-700 hover:shadow dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-amber-500 dark:hover:bg-gray-700 dark:hover:text-amber-300'
                        => $this->activeChannel !== $ch,
                ])
            >
                <span class="text-base leading-none">{{ $ch === 'global' ? '🌐' : '💬' }}</span>
                {{ $ch === 'global' ? 'Global Chat' : $ch }}
            </button>
        @empty
            <p class="text-sm italic text-gray-400 dark:text-gray-500">No messages yet.</p>
        @endforelse
    </div>

    {{ $this->table }}

</x-filament-panels::page>
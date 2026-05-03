<div class="flex items-center gap-3">
    <div class="relative flex items-center justify-center">
        <!-- O $val neste caso contém o ID do node, porque configuramos a 'key' => 'id' lá no Controller! -->
        <span class="absolute w-full h-full rounded-full bg-gray-500 opacity-20 animate-ping node-ping-{{ $val }}"></span>
        <span class="relative w-2.5 h-2.5 rounded-full bg-gray-500 shadow-[0_0_8px_#6b7280] node-indicator-{{ $val }}"></span>
    </div>
    <span class="text-[12px] font-bold text-gray-400 uppercase tracking-widest node-text-{{ $val }}">Conectando...</span>
</div>
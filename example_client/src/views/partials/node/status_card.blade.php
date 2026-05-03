@if(isset($resource) && isset($resource->id))
    <div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col mb-8 break-inside-avoid w-full">
        <!-- Cabeçalho do Card (Mais escuro/Sidebar) -->
        <div class="px-8 py-6 bg-sidebar flex justify-between items-center">
            <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">Status em Tempo Real</h3>

            <div class="flex items-center gap-3">
                <div class="relative flex items-center justify-center">
                    <span class="absolute w-full h-full rounded-full bg-textSub opacity-20 node-ping-{{ $resource->id }}"></span>
                    <span class="relative w-2.5 h-2.5 rounded-full bg-textSub shadow-[0_0_8px_rgba(159,176,192,0.5)] node-indicator-{{ $resource->id }}"></span>
                </div>
                <span class="text-[12px] font-bold text-textSub uppercase tracking-widest node-text-{{ $resource->id }}">Conectando...</span>
            </div>
        </div>

        <div class="p-8 flex flex-col gap-6">
            <!-- Grid de Informações do Sistema -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Itens usando bg-bgBase para destacar dentro do bg-cards -->
                <div class="bg-bgBase p-4 rounded-xl shadow-inner">
                    <span class="text-[10px] text-textSub uppercase font-bold tracking-wider block mb-1">Uso de RAM</span>
                    <span class="text-xl font-mono text-textValue node-ram-{{ $resource->id }}">--</span>
                </div>
                <div class="bg-bgBase p-4 rounded-xl shadow-inner">
                    <span class="text-[10px] text-textSub uppercase font-bold tracking-wider block mb-1">Carga de CPU</span>
                    <span class="text-xl font-mono text-textValue node-cpu-{{ $resource->id }}">--</span>
                </div>
                <div class="bg-bgBase p-4 rounded-xl shadow-inner">
                    <span class="text-[10px] text-textSub uppercase font-bold tracking-wider block mb-1">Sistema Operacional</span>
                    <span class="text-sm font-medium text-textSub node-os-{{ $resource->id }}">--</span>
                </div>
                <div class="bg-bgBase p-4 rounded-xl shadow-inner">
                    <span class="text-[10px] text-textSub uppercase font-bold tracking-wider block mb-1">Uptime</span>
                    <span class="text-sm font-medium text-textSub node-uptime-{{ $resource->id }}">--</span>
                </div>
            </div>
        </div>
    </div>
@endif
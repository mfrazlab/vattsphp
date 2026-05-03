<div class="grid grid-cols-1 xl:grid-cols-3 gap-8 items-start break-inside-avoid w-full">

    <!-- ESQUERDA: Lista de Alocações Existentes -->
    <div class="xl:col-span-2 bg-cards shadow-main rounded-md overflow-hidden flex flex-col w-full">
        <!-- Header mais escuro (sidebar) -->
        <div class="px-6 py-5 bg-sidebar flex items-center justify-between">
            <h3 class="text-[13px] font-black text-textValue uppercase tracking-wider flex items-center gap-2">
                Alocações Existentes
            </h3>
            <button type="submit" formaction="/admin/nodes/{{ $node->id }}/allocations/aliases" formmethod="POST" class="bg-bgBase hover:bg-terciary text-textSub hover:text-textValue px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm border-none">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                Salvar Aliases
            </button>
        </div>

        <div class="p-0 overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[600px]">
                <thead>
                <!-- Cabeçalho da tabela seguindo o tom escuro -->
                <tr class="bg-sidebar/50">
                    <th class="px-6 py-4 text-[11px] font-black text-textSub uppercase tracking-widest">Endereço IP</th>
                    <th class="px-4 py-4 text-[11px] font-black text-textSub uppercase tracking-widest w-1/3">IP Externo (Alias)</th>
                    <th class="px-4 py-4 text-[11px] font-black text-textSub uppercase tracking-widest">Porta</th>
                    <th class="px-4 py-4 text-[11px] font-black text-textSub uppercase tracking-widest">Atribuído A</th>
                    <th class="px-6 py-4 text-center text-[11px] font-black text-textSub uppercase tracking-widest">Ações</th>
                </tr>
                </thead>
                <tbody class="divide-none">
                @forelse($allocations as $alloc)
                    <tr class="hover:bg-terciary/20 transition-colors group data-row">
                        <!-- IP -->
                        <td class="px-6 py-4">
                            <span class="text-textValue font-medium text-sm">{{ $alloc->ip }}</span>
                        </td>

                        <!-- ALIAS EDITÁVEL -->
                        <td class="px-4 py-4">
                            <input type="text" name="aliases[{{ $alloc->id }}]" value="{{ $alloc->externalIp }}" placeholder="Nenhum alias" class="w-full bg-bgBase rounded-xl px-4 py-2.5 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 border-none shadow-inner">
                        </td>

                        <!-- PORTA -->
                        <td class="px-4 py-4">
                                <span class="bg-bgBase px-3 py-1.5 rounded-lg text-primary font-mono text-sm font-bold shadow-inner">
                                    {{ $alloc->port }}
                                </span>
                        </td>

                        <!-- ATRIBUÍDO A -->
                        <td class="px-4 py-4">
                            @if($alloc->assignedTo)
                                <span class="text-[11px] font-bold text-info hover:brightness-110 cursor-pointer underline decoration-info/30 underline-offset-4">
                                        Servidor
                                    </span>
                            @else
                                <span class="text-[12px] font-medium text-textSub italic">
                                        Disponível
                                    </span>
                            @endif
                        </td>

                        <!-- LIXEIRA -->
                        <td class="px-6 py-4 flex justify-center">
                            @if(!$alloc->assignedTo)
                                <a href="/admin/nodes/{{ $node->id }}/allocations/{{ $alloc->id }}/delete" onclick="return confirm('Tem certeza que deseja remover a porta {{ $alloc->port }}?');" class="bg-danger/10 text-danger hover:bg-danger hover:text-textValue p-2.5 rounded-xl transition-all shadow-sm">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <span class="text-textSub text-sm font-medium">Nenhuma alocação cadastrada neste node.</span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- DIREITA: Formulário de Adicionar Novas -->
    <div class="xl:col-span-1 bg-cards shadow-main rounded-md overflow-hidden flex flex-col w-full ">
        <div class="px-6 py-5 bg-sidebar">
            <h3 class="text-[13px] font-black text-textValue uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Atribuir Novas Alocações
            </h3>
        </div>

        <div class="p-6 flex flex-col gap-6">

            <!-- Endereço IP -->
            <div class="flex flex-col gap-2">
                <label class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">
                    Endereço IP <span class="text-primary ml-1 text-sm">*</span>
                </label>
                <input type="text" name="allocation_ip" placeholder="Ex: 192.168.1.1" value="{{ $node->ip }}" class="w-full bg-bgBase rounded-xl px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none">
                <p class="text-[11px] font-medium text-textSub mt-1 ml-1 leading-relaxed">IP onde as portas serão atribuídas.</p>
            </div>

            <!-- IP Externo -->
            <div class="flex flex-col gap-2">
                <label class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">IP Externo (Alias)</label>
                <input type="text" name="allocation_external_ip" placeholder="Ex: node01.host.com" class="w-full bg-bgBase rounded-xl px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none">
                <p class="text-[11px] font-medium text-textSub mt-1 ml-1 leading-relaxed">Se quiser atribuir um alias padrão (FQDN), digite aqui.</p>
            </div>

            <!-- Portas (Aceita vírgulas e traços) -->
            <div class="flex flex-col gap-2">
                <label class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">
                    Portas <span class="text-primary ml-1 text-sm">*</span>
                </label>
                <textarea name="allocation_ports" rows="2" placeholder="25565, 25566-25570" class="w-full bg-bgBase rounded-xl px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 resize-none shadow-inner border-none"></textarea>
                <p class="text-[11px] font-medium text-textSub mt-1 ml-1 leading-relaxed">
                    Insira portas individuais ou intervalos separados por vírgula (ex: 25565-25570, 25585).
                </p>
            </div>

            <hr class="border-terciary/30">

            <!-- Botão Adicionar -->
            <button type="submit" formaction="/admin/nodes/{{ $node->id }}/allocations" formmethod="POST" class="w-full bg-primary hover:brightness-110 text-textValue px-8 py-4 rounded-xl text-sm font-bold shadow-main transition-all duration-300 h-[52px] flex items-center justify-center transform hover:-translate-y-0.5 border-none">
                Atribuir Portas
            </button>

        </div>
    </div>
</div>
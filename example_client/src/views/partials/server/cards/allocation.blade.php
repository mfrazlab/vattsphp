<div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col break-inside-avoid w-full mb-8 lg:col-span-2">
    <!-- Cabeçalho mais escuro -->
    <div class="px-8 py-6 bg-sidebar">
        <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">Alocação do Sistema</h3>
    </div>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-2 gap-10">
        <!-- Hidden fields -->
        <input type="hidden" name="ownerId" id="server-owner-id" value="{{ isset($resource) ? data_get($resource, 'ownerId', '') : '' }}">
        <input type="hidden" name="nodeId" id="server-node-id" value="{{ isset($resource) ? data_get($resource, 'nodeUuid', '') : (isset($resource) ? data_get($resource, 'nodeId', '') : '') }}">
        <input type="hidden" name="allocationId" id="server-allocation-id" value="{{ isset($resource) ? data_get($resource, 'allocationId', '') : '' }}">

        <!-- LADO ESQUERDO: Proprietário -->
        <div class="flex flex-col gap-4">
            <div>
                <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Proprietário</div>
                <div class="text-[12px] font-medium text-textSub ml-2 mt-1">
                    {{ isset($resource) ? 'Altere o proprietário buscando por e-mail (opcional).' : 'Busque por e-mail e selecione o usuário dono.' }}
                </div>
            </div>

            <div class="relative">
                <input
                        type="text"
                        id="server-owner-search"
                        class="w-full bg-sidebar rounded-xl px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none"
                        placeholder="Digite o e-mail do usuário..."
                        autocomplete="off"
                        value="{{ isset($ownerUser) ? $ownerUser->email : (isset($resource) ? data_get($resource, 'ownerEmail', '') : '') }}"
                >
                <div id="server-owner-dropdown" class="absolute left-0 right-0 mt-2 bg-sidebar rounded-xl shadow-main overflow-hidden hidden z-50 border border-terciary/20">
                    <div class="p-4 text-sm text-textSub">Digite para buscar...</div>
                </div>
            </div>
        </div>

        <!-- LADO DIREITO: Node e Alocação -->
        <div class="flex flex-col gap-8">

            <!-- LISTA DE NODES (Só aparece na Criação) -->
            @if(!isset($resource))
                <div class="flex flex-col gap-4">
                    <div>
                        <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Selecionar Node</div>
                        <div class="text-[12px] font-medium text-textSub ml-2 mt-1">
                            Escolha o servidor físico onde a instância será criada.
                        </div>
                    </div>

                    <!-- Select Real de Nodes -->
                    <div class="relative">
                        <select
                                id="server-node-select"
                                class="w-full bg-sidebar rounded-xl px-5 py-4 text-sm text-textValue font-medium focus:ring-2 focus:ring-primary outline-none transition-all duration-300 appearance-none shadow-inner border-none disabled:opacity-50"
                        >
                            <option value="">Carregando infraestrutura...</option>
                        </select>
                        <div class="absolute right-5 top-1/2 -translate-y-1/2 text-textSub pointer-events-none">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Seleção de Porta -->
            <div class="flex flex-col gap-4" id="server-allocation-container">
                <div>
                    <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Porta de Alocação</div>
                    <div class="text-[12px] font-medium text-textSub ml-2 mt-1">
                        {{ isset($resource) ? 'Alterar porta (somente no mesmo node).' : 'Selecione uma porta disponível no node.' }}
                    </div>
                </div>

                <div class="relative">
                    <select
                            id="server-allocation-select"
                            class="w-full bg-sidebar rounded-xl px-5 py-4 text-sm text-textValue font-medium focus:ring-2 focus:ring-primary outline-none transition-all duration-300 appearance-none shadow-inner border-none disabled:opacity-50"
                            {{ !isset($resource) ? 'disabled' : '' }}
                    >
                        @if(isset($resource))
                            <option value="{{ data_get($resource, 'allocationId') }}">Carregando portas...</option>
                        @else
                            <option value="">Selecione um Node primeiro...</option>
                        @endif
                    </select>
                    <div class="absolute right-5 top-1/2 -translate-y-1/2 text-textSub pointer-events-none">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ownerSearch = document.getElementById('server-owner-search');
        const ownerIdInput = document.getElementById('server-owner-id');
        const ownerDropdown = document.getElementById('server-owner-dropdown');
        const serverId = "{{ isset($resource) ? data_get($resource, 'id', '') : '' }}";

        const nodeSelect = document.getElementById('server-node-select');
        const nodeIdInput = document.getElementById('server-node-id');
        const allocationSelect = document.getElementById('server-allocation-select');
        const allocationIdInput = document.getElementById('server-allocation-id');

        let debounceTimer;

        // Função de carregar alocações
        const loadAllocations = (nodeId) => {
            if (!nodeId) return;

            allocationSelect.innerHTML = '<option value="">Buscando portas...</option>';
            allocationSelect.disabled = true;

            fetch(`/admin/api/allocations/list?nodeId=${encodeURIComponent(nodeId)}&serverId=${encodeURIComponent(serverId)}`)
                .then(res => res.json())
                .then(data => {
                    allocationSelect.innerHTML = '';

                    if (!Array.isArray(data) || data.length === 0) {
                        allocationSelect.innerHTML = '<option value="">Sem portas disponíveis</option>';
                        return;
                    }

                    allocationSelect.disabled = false;
                    const placeholder = document.createElement('option');
                    placeholder.value = "";
                    placeholder.textContent = "Selecione a porta principal...";
                    allocationSelect.appendChild(placeholder);

                    data.forEach(alloc => {
                        const option = document.createElement('option');
                        option.value = alloc.id;
                        const isCurrent = (allocationIdInput.value == alloc.id || alloc.isAssignedToMe);
                        option.textContent = `${alloc.ip}:${alloc.port} ${alloc.externalIp ? `(${alloc.externalIp})` : ''} ${isCurrent ? '• Atual' : ''}`;
                        if (isCurrent) {
                            option.selected = true;
                            allocationIdInput.value = alloc.id;
                        }
                        allocationSelect.appendChild(option);
                    });
                });
        };

        // Evento de seleção de Node no SELECT
        if (nodeSelect) {
            nodeSelect.addEventListener('change', (e) => {
                nodeIdInput.value = e.target.value;
                allocationIdInput.value = '';

                if(e.target.value) {
                    loadAllocations(e.target.value);
                } else {
                    allocationSelect.innerHTML = '<option value="">Selecione um Node primeiro...</option>';
                    allocationSelect.disabled = true;
                }
            });

            // Carregar lista inicial de Nodes
            fetch('/admin/api/nodes/list')
                .then(res => res.json())
                .then(data => {
                    nodeSelect.innerHTML = '';

                    // Filtra apenas nodes online
                    const onlineNodes = (data || []).filter(node => node.online);

                    if (!onlineNodes || onlineNodes.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = "";
                        opt.textContent = "Nenhum node disponível/online";
                        nodeSelect.appendChild(opt);
                        nodeSelect.disabled = true;
                        return;
                    }

                    const placeholder = document.createElement('option');
                    placeholder.value = "";
                    placeholder.textContent = "Selecione um Node...";
                    nodeSelect.appendChild(placeholder);

                    onlineNodes.forEach(node => {
                        const opt = document.createElement('option');
                        opt.value = node.id;
                        opt.textContent = `${node.name} (${node.location || 'GLOBAL'})`;
                        nodeSelect.appendChild(opt);
                    });
                });
        } else if (nodeIdInput.value) {
            // Se estiver editando, apenas carrega as portas do node já salvo
            loadAllocations(nodeIdInput.value);
        }

        // Busca de Proprietário
        if (ownerSearch) {
            ownerSearch.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                const query = e.target.value.trim();
                if (query.length < 2) { ownerDropdown.classList.add('hidden'); return; }

                debounceTimer = setTimeout(() => {
                    fetch(`/admin/api/users/search?email=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(data => {
                            ownerDropdown.innerHTML = '';
                            if (!data.length) {
                                ownerDropdown.innerHTML = '<div class="p-4 text-sm text-textSub">Nenhum usuário...</div>';
                            } else {
                                data.forEach(user => {
                                    const div = document.createElement('div');
                                    div.className = 'p-4 text-sm hover:bg-primary hover:text-textValue cursor-pointer transition-colors';
                                    div.textContent = `${user.name} (${user.email})`;
                                    div.onclick = () => {
                                        ownerSearch.value = user.email;
                                        ownerIdInput.value = user.uuid || user.id;
                                        ownerDropdown.classList.add('hidden');
                                    };
                                    ownerDropdown.appendChild(div);
                                });
                            }
                            ownerDropdown.classList.remove('hidden');
                        });
                }, 300);
            });
        }

        if (allocationSelect) {
            allocationSelect.addEventListener('change', (e) => {
                allocationIdInput.value = e.target.value;
            });
        }
    });
</script>
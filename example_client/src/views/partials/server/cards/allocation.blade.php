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

            <!-- ALOCAÇÕES ADICIONAIS (FIXED) -->
            <div class="flex flex-col gap-4" id="server-additional-fixed-container">
                <div>
                    <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Alocações adicionais (FIXED)</div>
                    <div class="text-[12px] font-medium text-textSub ml-2 mt-1">
                        Clique para abrir a lista, selecione quantas portas quiser e depois salve o servidor.
                    </div>
                </div>

                <div class="flex gap-3">
                    <button
                        type="button"
                        id="server-additional-fixed-toggle"
                        class="flex-1 px-5 py-4 rounded-xl text-sm font-bold text-textValue bg-sidebar hover:bg-terciary transition-all shadow-main flex items-center justify-between gap-3"
                    >
                        <span>Selecionar alocações livres</span>
                        <span id="server-additional-fixed-count" class="text-[11px] font-black text-textSub uppercase tracking-widest">0 selecionadas</span>
                    </button>
                    <button
                        type="button"
                        id="server-additional-fixed-clear"
                        class="px-5 py-4 rounded-xl text-sm font-bold text-danger hover:text-textValue bg-danger/10 hover:bg-danger transition-all shadow-main"
                    >
                        Limpar
                    </button>
                </div>

                <div id="server-additional-fixed-panel" class="hidden bg-sidebar rounded-xl p-4 shadow-inner border border-terciary/20 flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Disponíveis</div>
                            <div class="text-[12px] font-medium text-textSub ml-1 mt-1">Clique em uma porta para adicioná-la aos FIXED.</div>
                        </div>
                        <div class="text-[11px] font-black text-textSub uppercase tracking-widest" id="server-additional-fixed-counter">0</div>
                    </div>

                    <input
                        type="text"
                        id="server-additional-fixed-search"
                        class="w-full bg-cards rounded-xl px-4 py-3 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none"
                        placeholder="Filtrar por porta, IP ou nome..."
                    >

                    <div id="server-additional-fixed-options" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto custom-scrollbar pr-1">
                        <div class="text-textSub text-sm">Carregando opções...</div>
                    </div>
                </div>

                <div class="bg-sidebar rounded-xl p-4 text-sm text-textSub" id="server-additional-fixed-list">
                    Nenhuma allocation fixa adicionada.
                </div>

                <input type="hidden" name="additionalAllocationsFixed" id="server-additional-fixed" value="[]">
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

        const additionalFixedField = document.getElementById('server-additional-fixed');
        const additionalFixedList = document.getElementById('server-additional-fixed-list');
        const additionalFixedToggle = document.getElementById('server-additional-fixed-toggle');
        const additionalFixedClear = document.getElementById('server-additional-fixed-clear');
        const additionalFixedPanel = document.getElementById('server-additional-fixed-panel');
        const additionalFixedSearch = document.getElementById('server-additional-fixed-search');
        const additionalFixedOptions = document.getElementById('server-additional-fixed-options');
        const additionalFixedCount = document.getElementById('server-additional-fixed-count');
        const additionalFixedCounter = document.getElementById('server-additional-fixed-counter');

        let fixedOptions = [];
        let debounceTimer;
        let fixedSearchTerm = '';

        const initialFixed = @json((function () {
            $raw = isset($resource) ? ($resource->additionalAllocations ?? '') : '';
            $data = json_decode($raw, true) ?: [];
            return $data['FIXED'] ?? [];
        })());

        let fixedIds = Array.isArray(initialFixed) ? initialFixed.map((v) => parseInt(v, 10)).filter(Boolean) : [];

        const getFixedLabel = (item) => `${item.ip}:${item.port}${item.externalIp ? ` (${item.externalIp})` : ''}`;

        const renderFixedList = () => {
            if (!additionalFixedList) return;

            if (!fixedIds.length) {
                additionalFixedList.textContent = 'Nenhuma allocation fixa adicionada.';
                return;
            }

            additionalFixedList.innerHTML = '';
            fixedIds.forEach((id) => {
                const item = fixedOptions.find((opt) => String(opt.id) === String(id));
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between py-2 border-b border-terciary/20 last:border-none gap-3';
                row.innerHTML = `
                    <span class="truncate">${item ? getFixedLabel(item) : `#${id}`}</span>
                    <button type="button" data-fixed-remove="${id}" class="text-danger hover:text-textValue transition-colors shrink-0">Remover</button>
                `;
                additionalFixedList.appendChild(row);
            });
        };

        const syncFixedField = () => {
            if (additionalFixedField) {
                additionalFixedField.value = JSON.stringify(fixedIds);
            }
            if (additionalFixedCount) additionalFixedCount.textContent = `${fixedIds.length} selecionadas`;
            if (additionalFixedCounter) additionalFixedCounter.textContent = String(fixedIds.length);
        };

        const refreshFixedOptionsSelection = () => {
            if (!additionalFixedOptions) return;
            const term = fixedSearchTerm.toLowerCase();
            const selected = new Set(fixedIds.map((id) => String(id)));
            additionalFixedOptions.innerHTML = '';

            const filtered = fixedOptions.filter((opt) => {
                const haystack = `${opt.label} ${opt.ip} ${opt.port} ${opt.externalIp || ''}`.toLowerCase();
                return haystack.includes(term);
            });

            if (!filtered.length) {
                additionalFixedOptions.innerHTML = '<div class="text-sm text-textSub">Nenhuma alocação encontrada.</div>';
                return;
            }

            filtered.forEach((opt) => {
                const isSelected = selected.has(String(opt.id));
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.fixedId = String(opt.id);
                button.className = `text-left w-full rounded-xl px-4 py-3 transition-all border text-sm font-medium ${isSelected ? 'bg-primary text-textValue border-primary shadow-main' : 'bg-cards text-textValue border-terciary/20 hover:bg-terciary'}`;
                button.innerHTML = `
                    <div class="flex items-center justify-between gap-3">
                        <span class="truncate">${opt.label}</span>
                        <span class="text-[10px] font-black uppercase tracking-widest ${isSelected ? 'text-white/80' : 'text-textSub'}">${isSelected ? 'Selecionada' : 'Adicionar'}</span>
                    </div>
                `;
                additionalFixedOptions.appendChild(button);
            });
        };

        const applyFixedSelection = (id) => {
            const allocId = parseInt(String(id), 10);
            if (!allocId) return;
            if (fixedIds.includes(allocId)) return;
            fixedIds = Array.from(new Set([...fixedIds, allocId]));
            renderFixedList();
            syncFixedField();
            refreshFixedOptionsSelection();
        };

        const removeFixedSelection = (id) => {
            const allocId = parseInt(String(id), 10);
            fixedIds = fixedIds.filter((item) => item !== allocId);
            renderFixedList();
            syncFixedField();
            refreshFixedOptionsSelection();
        };

        // Função de carregar alocações
        const loadAllocations = (nodeId, forceRefreshSelect = true) => {
            if (!nodeId) return;

            if (forceRefreshSelect) {
                allocationSelect.innerHTML = '<option value="">Buscando portas...</option>';
                allocationSelect.disabled = true;
            }

            fetch(`/admin/api/allocations/list?nodeId=${encodeURIComponent(nodeId)}&serverId=${encodeURIComponent(serverId)}`)
                .then(res => res.json())
                .then((data) => {
                    if (forceRefreshSelect) {
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
                            const isCurrent = (String(allocationIdInput.value) === String(alloc.id));
                            option.textContent = `${alloc.ip}:${alloc.port} ${alloc.externalIp ? `(${alloc.externalIp})` : ''} ${isCurrent ? '• Atual' : ''}`;
                            if (isCurrent) {
                                option.selected = true;
                            }
                            allocationSelect.appendChild(option);
                        });
                    }

                    const availableFixed = data.filter((alloc) => String(alloc.id) !== String(allocationIdInput.value));
                    fixedOptions = availableFixed.map((alloc) => ({
                        id: String(alloc.id),
                        label: getFixedLabel(alloc),
                        ip: alloc.ip,
                        port: alloc.port,
                        externalIp: alloc.externalIp || null,
                    }));

                    renderFixedList();
                    refreshFixedOptionsSelection();
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
                .then((data) => {
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
                const newVal = e.target.value;
                allocationIdInput.value = newVal;

                const newPrimary = parseInt(newVal, 10);
                if (newPrimary && fixedIds.includes(newPrimary)) {
                    fixedIds = fixedIds.filter((item) => item !== newPrimary);
                    renderFixedList();
                    syncFixedField();
                }

                // Atualiza apenas os labels do select sem reconstruir tudo (evita flicker e perda de foco)
                Array.from(allocationSelect.options).forEach(opt => {
                    if (!opt.value) return;
                    const isCurrent = String(opt.value) === String(newVal);
                    // Remove o sufixo " • Atual" se existir e readiciona se for o atual
                    let text = opt.textContent.replace(' • Atual', '');
                    if (isCurrent) text += ' • Atual';
                    opt.textContent = text;
                });

                // Recarrega apenas as opções de FIXED para refletir a mudança da porta principal
                loadAllocations(nodeIdInput.value, false);
            });
        }

        renderFixedList();
        syncFixedField();
        refreshFixedOptionsSelection();

        if (additionalFixedToggle && additionalFixedPanel) {
            additionalFixedToggle.addEventListener('click', () => {
                additionalFixedPanel.classList.toggle('hidden');
                refreshFixedOptionsSelection();
            });
        }

        if (additionalFixedSearch) {
            additionalFixedSearch.addEventListener('input', (e) => {
                fixedSearchTerm = e.target.value || '';
                refreshFixedOptionsSelection();
            });
        }

        if (additionalFixedOptions) {
            additionalFixedOptions.addEventListener('click', (event) => {
                const target = event.target.closest('[data-fixed-id]');
                if (!target) return;
                const id = target.dataset.fixedId;
                if (!id) return;
                if (fixedIds.includes(parseInt(id, 10))) {
                    removeFixedSelection(id);
                } else {
                    applyFixedSelection(id);
                }
            });
        }

        if (additionalFixedClear) {
            additionalFixedClear.addEventListener('click', () => {
                fixedIds = [];
                renderFixedList();
                syncFixedField();
                refreshFixedOptionsSelection();
            });
        }

        if (additionalFixedList) {
            additionalFixedList.addEventListener('click', (event) => {
                const target = event.target;
                if (!target || !target.dataset || !target.dataset.fixedRemove) return;
                removeFixedSelection(target.dataset.fixedRemove);
            });
        }
    });
</script>
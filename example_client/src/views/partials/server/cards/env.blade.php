<div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col break-inside-avoid w-full mb-8 lg:col-span-2">
    <!-- Cabeçalho escuro conforme o padrão -->
    <div class="px-8 py-6 bg-sidebar">
        <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">Variáveis de Ambiente</h3>
    </div>

    <div class="p-8">
        <div class="mb-6">
            <div class="text-sm text-textValue font-bold">
                {{ isset($resource) ? 'Edite as variáveis do core selecionado' : 'Configure as variáveis do core selecionado' }}
            </div>
            <div class="text-xs text-textSub mt-1">As variáveis aparecem em grid e o valor padrão do core é aplicado automaticamente.</div>
        </div>

        <!-- Grid de variáveis -->
        <div id="server-env-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="text-sm text-textSub md:col-span-2 italic">Selecione um core para carregar as variáveis...</div>
        </div>
    </div>
</div>

<script>
    (function(){
        function debounce(fn, wait){
            let t;
            return function(...args){
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            }
        }

        function escapeHtml(str){
            return (str ?? '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseRules(ruleString){
            const out = { required: false, def: null, regex: null, in: null };
            if (!ruleString) return out;
            const parts = String(ruleString).split('|').map(p => p.trim()).filter(Boolean);
            parts.forEach((p) => {
                if (p === 'required') out.required = true;
                else if (p.startsWith('default:')) out.def = p.substring('default:'.length);
                else if (p.startsWith('regex:')) out.regex = p.substring('regex:'.length);
                else if (p.startsWith('in:')) {
                    out.in = p.substring('in:'.length).split(',').map(s => s.trim());
                }
            });
            return out;
        }

        document.addEventListener('DOMContentLoaded', function(){
            const ownerHidden = document.getElementById('server-owner-id');
            const ownerSearch = document.getElementById('server-owner-search');
            const ownerDropdown = document.getElementById('server-owner-dropdown');
            const nodeHidden = document.getElementById('server-node-id');
            const nodeList = document.getElementById('server-node-list');
            const coreHidden = document.getElementById('server-core-id');
            const coreList = document.getElementById('server-core-list');
            const dockerHidden = document.getElementById('server-docker-image');
            const dockerSelect = document.getElementById('server-docker-select');
            const dockerCustom = document.getElementById('server-docker-custom');

            const startupInput = document.getElementById('server-startup-command');
            const originalStartupInput = document.getElementById('original-startup-command');
            const originalStartupContainer = document.getElementById('original-startup-container');

            const envContainer = document.getElementById('server-env-container');

            if (!envContainer) return;

            // -------------------- Owner typeahead --------------------
            if (ownerHidden && ownerSearch && ownerDropdown) {
                const renderOwnerDropdown = (items) => {
                    ownerDropdown.innerHTML = '';
                    if (!items.length) {
                        ownerDropdown.innerHTML = '<div class="p-4 text-sm text-textSub">Nenhum usuário...</div>';
                    } else {
                        items.forEach(u => {
                            const row = document.createElement('button');
                            row.type = 'button';
                            row.className = 'w-full text-left px-5 py-4 hover:bg-primary hover:text-textValue transition-all flex justify-between items-center';
                            row.innerHTML = `<div><div class="font-bold">${escapeHtml(u.email)}</div><div class="text-xs opacity-70">${escapeHtml(u.name || '')}</div></div>`;
                            row.addEventListener('click', () => {
                                ownerHidden.value = u.uuid || u.id;
                                ownerSearch.value = u.email;
                                ownerDropdown.classList.add('hidden');
                            });
                            ownerDropdown.appendChild(row);
                        });
                    }
                    ownerDropdown.classList.remove('hidden');
                };
                const doOwnerSearch = debounce((term) => {
                    if (term.trim().length < 2) { ownerDropdown.classList.add('hidden'); return; }
                    fetch(`/admin/api/users/search?email=${encodeURIComponent(term)}`)
                        .then(r => r.json()).then(renderOwnerDropdown);
                }, 300);
                ownerSearch.addEventListener('input', (e) => doOwnerSearch(e.target.value));
            }

            // -------------------- Nodes --------------------
            if (nodeList && !nodeList.children.length) {
                fetch('/admin/api/nodes/list').then(r => r.json()).then(nodes => {
                    nodeList.innerHTML = '';
                    nodes.forEach(n => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.dataset.nodeId = n.id;
                        btn.className = 'w-full text-left bg-sidebar rounded-xl px-5 py-4 flex items-center justify-between hover:bg-terciary transition-all';
                        btn.innerHTML = `<div><div class="text-textValue font-bold">${escapeHtml(n.name)}</div><div class="text-xs text-textSub">${escapeHtml(n.ip)}</div></div><div class="text-[10px] font-black uppercase ${n.online ? 'text-primary' : 'text-textSub'}">${n.online ? 'online' : 'offline'}</div>`;
                        btn.onclick = () => {
                            nodeHidden.value = n.id;
                            nodeList.querySelectorAll('[data-node-id]').forEach(x => x.classList.remove('ring-2','ring-primary'));
                            btn.classList.add('ring-2','ring-primary');
                        };
                        nodeList.appendChild(btn);
                    });
                });
            }

            // -------------------- Core & Env Logic --------------------
            const envSaved = (() => {
                try {
                    const raw = {!! json_encode(isset($resource) ? data_get($resource, 'envVars', '') : '') !!};
                    return raw ? JSON.parse(raw) : {};
                } catch (e) { return {}; }
            })();

            const renderEnvVars = (vars) => {
                envContainer.innerHTML = '';
                if (!vars.length) {
                    envContainer.innerHTML = '<div class="text-sm text-textSub md:col-span-2">Sem variáveis definidas.</div>';
                    return;
                }
                vars.forEach(v => {
                    const key = v.envVariable;
                    const rules = parseRules(v.rules || '');
                    let value = envSaved[key] ?? rules.def ?? '';

                    const fieldHtml = rules.in ? `
                        <select name="env[${escapeHtml(key)}]" class="w-full bg-sidebar rounded-xl px-4 py-3 text-sm text-textValue focus:ring-2 focus:ring-primary outline-none appearance-none border-none">
                            ${rules.in.map(opt => `<option value="${escapeHtml(opt)}" ${String(value) === String(opt) ? 'selected' : ''}>${escapeHtml(opt)}</option>`).join('')}
                        </select>
                    ` : `
                        <input type="text" name="env[${escapeHtml(key)}]" value="${escapeHtml(value)}" class="w-full bg-sidebar rounded-xl px-4 py-3 text-sm text-textValue focus:ring-2 focus:ring-primary outline-none border-none shadow-inner">
                    `;

                    const card = document.createElement('div');
                    card.className = 'bg-bgBase rounded-xl p-5 shadow-inner flex flex-col gap-3';
                    card.innerHTML = `
                        <div>
                            <div class="text-textValue font-bold text-xs uppercase tracking-wider">${escapeHtml(v.name || key)} ${rules.required ? '<span class="text-primary">*</span>' : ''}</div>
                            <div class="text-[10px] text-textSub mt-1 leading-tight">${escapeHtml(v.description || '')}</div>
                        </div>
                        <div class="relative">${fieldHtml}</div>
                    `;
                    envContainer.appendChild(card);
                });
            };

            const renderCores = (cores) => {
                if (!coreList) return;
                coreList.innerHTML = '';
                cores.forEach(c => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.coreId = c.id;
                    btn.className = 'w-full text-left bg-sidebar rounded-xl px-5 py-4 flex items-center justify-between hover:bg-terciary transition-all group';
                    btn.innerHTML = `<div><div class="text-textValue font-bold">${escapeHtml(c.name)}</div><div class="text-xs text-textSub group-hover:text-textValue transition-colors">${escapeHtml(c.description || '')}</div></div><div class="text-xs text-textSub font-mono">#${c.id}</div>`;

                    btn.onclick = () => {
                        coreHidden.value = c.id;
                        coreList.querySelectorAll('[data-core-id]').forEach(x => x.classList.remove('ring-2','ring-primary','bg-terciary/40'));
                        btn.classList.add('ring-2','ring-primary', 'bg-terciary/40');

                        // Atualiza imagens docker
                        if (dockerSelect) {
                            dockerSelect.innerHTML = '';
                            (c.dockerImages || []).forEach(img => {
                                const val = typeof img === 'string' ? img : (img.image || img.value);
                                const lab = typeof img === 'string' ? img : (img.name || img.label || val);
                                const opt = document.createElement('option');
                                opt.value = val; opt.textContent = lab;
                                dockerSelect.appendChild(opt);
                            });
                        }
                        // Sugere startup customizado (somente se o input customizado estiver vazio)
                        if (startupInput && !startupInput.value) {
                            startupInput.value = c.startupCommand || c.startup_command || '';
                        }

                        // AQUI TÁ A MÁGICA DO COMANDO ORIGINAL:
                        const originalStartupInput = document.getElementById('original-startup-command');
                        const originalStartupContainer = document.getElementById('original-startup-container');

                        if (originalStartupInput && originalStartupContainer) {
                            // Pega do JSON exatamente a propriedade "startupCommand"
                            originalStartupInput.value = c.startupCommand || c.startup_command || 'Nenhum comando padrão definido no Core.';
                            originalStartupContainer.classList.remove('hidden');
                        }

                        renderEnvVars(c.variables || []);
                    };
                    coreList.appendChild(btn);
                });

                if (coreHidden.value) {
                    const active = coreList.querySelector(`[data-core-id="${coreHidden.value}"]`);
                    if (active) active.click();
                }
            };

            fetch('/admin/api/cores/list').then(r => r.json()).then(renderCores);
        });
    })();
</script>
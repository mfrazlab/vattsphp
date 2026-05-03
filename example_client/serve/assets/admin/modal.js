/**
 * Hight Cloud - Modal de Confirmação Dinâmico
 * Segue a identidade visual do painel admin.
 */

window.AdminModal = {
    /**
     * Exibe o modal de confirmação de exclusão.
     * * @param {Object} options Configurações do modal
     * @param {string} options.title Título do modal (ex: 'Excluir Usuário')
     * @param {string} options.message Mensagem de aviso (ex: 'Tem certeza que deseja excluir o usuário X?')
     * @param {function} options.onConfirm Função executada ao clicar em "Excluir"
     */
    confirmDelete: function(options) {
        // Valores padrão
        const title = options.title || 'Excluir Registro';
        const message = options.message || 'Tem certeza que deseja excluir este registro? Essa ação não pode ser desfeita.';

        // Remove modal anterior se existir (prevenção)
        const existingModal = document.getElementById('hightcloud-delete-modal');
        if (existingModal) existingModal.remove();

        // Estrutura HTML do Modal usando as classes Tailwind do seu projeto
        const modalHtml = `
            <div id="hightcloud-delete-modal" class="fixed inset-0 z-50 flex items-center justify-center opacity-0 transition-opacity duration-300" style="pointer-events: none;">
                <!-- Backdrop com blur -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm modal-backdrop"></div>
                
                <!-- Caixa do Modal -->
                <div class="relative bg-surface w-full max-w-md rounded-[2rem] shadow-[0_20px_50px_rgba(0,0,0,0.7)] p-8 transform scale-95 transition-transform duration-300 modal-content border border-white/5">
                    
                    <!-- Cabeçalho / Ícone -->
                    <div class="flex gap-5 mb-6">
                        <div class="w-14 h-14 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500 shadow-inner flex-shrink-0">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <div class="flex flex-col justify-center">
                            <h3 class="text-xl font-black text-white tracking-tight">${title}</h3>
                            <p class="text-sm font-medium text-gray-400 mt-1 leading-relaxed">${message}</p>
                        </div>
                    </div>
                    
                    <!-- Botões de Ação -->
                    <div class="flex items-center justify-end gap-3 mt-8">
                        <button id="hc-modal-cancel" class="px-6 py-3.5 rounded-2xl text-sm font-bold text-gray-400 bg-inputBg hover:text-white hover:bg-surfaceHover transition-all shadow-md">
                            Cancelar
                        </button>
                        <button id="hc-modal-confirm" class="px-6 py-3.5 rounded-2xl text-sm font-bold text-white bg-red-500 hover:bg-red-600 transition-all flex items-center gap-2 shadow-[0_8px_24px_rgba(239,68,68,0.25)] hover:shadow-[0_12px_32px_rgba(239,68,68,0.4)] transform hover:-translate-y-0.5">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 114 0v2" />
                            </svg>
                            Sim, Excluir
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Adiciona ao final do body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modalEl = document.getElementById('hightcloud-delete-modal');
        const contentEl = modalEl.querySelector('.modal-content');
        const btnCancel = document.getElementById('hc-modal-cancel');
        const btnConfirm = document.getElementById('hc-modal-confirm');
        const backdrop = modalEl.querySelector('.modal-backdrop');

        // Função para fechar com animação
        const closeModal = () => {
            modalEl.classList.remove('opacity-100');
            contentEl.classList.remove('scale-100');
            modalEl.classList.add('opacity-0');
            contentEl.classList.add('scale-95');

            setTimeout(() => {
                modalEl.remove();
            }, 300); // Tempo da transição do Tailwind
        };

        // Animação de entrada (pequeno delay para o navegador processar o DOM)
        setTimeout(() => {
            modalEl.style.pointerEvents = 'auto';
            modalEl.classList.remove('opacity-0');
            contentEl.classList.remove('scale-95');
            modalEl.classList.add('opacity-100');
            contentEl.classList.add('scale-100');
        }, 10);

        // Eventos de clique
        btnCancel.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);

        btnConfirm.addEventListener('click', () => {
            // Desabilita o botão para evitar cliques duplos e adiciona status de loading (opcional)
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = `<svg class="animate-spin h-5 w-5 mr-2 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Excluindo...`;

            if (options.onConfirm && typeof options.onConfirm === 'function') {
                options.onConfirm();
                // Se a função onConfirm não lidar com o redirecionamento imediato,
                // você pode chamar closeModal() aqui dentro se precisar.
            }
        });

        // Fechar com ESC
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        });
    }
};
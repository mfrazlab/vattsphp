<div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col break-inside-avoid w-full mb-8 lg:col-span-2">
    <!-- Cabeçalho mais escuro que o card -->
    <div class="px-8 py-6 bg-sidebar">
        <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">Core & Docker</h3>
    </div>

    <div class="p-8 grid grid-cols-1 lg:grid-cols-2 gap-10">
        <!-- Hidden fields controlled by JS -->
        <input type="hidden" name="coreId" id="server-core-id" value="{{ isset($resource) ? data_get($resource, 'coreId', '') : '' }}">
        <input type="hidden" name="dockerImage" id="server-docker-image" value="{{ isset($resource) ? data_get($resource, 'dockerImage', '') : '' }}">

        <!-- LADO ESQUERDO: Seleção de Core -->
        <div class="flex flex-col gap-4">
            <div>
                <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Selecionar Core</div>
                <div class="text-[12px] font-medium text-textSub ml-2 mt-1">
                    {{ isset($resource) ? 'Altere o core do servidor. O core dita as configurações base.' : 'O core define as variáveis e sugestões do servidor.' }}
                </div>
            </div>

            <!-- Lista Real de Cores com Scroll -->
            <div id="server-core-list" class="flex flex-col gap-2 max-h-[320px] overflow-y-auto pr-2 custom-scrollbar bg-bgBase/30 p-2 rounded-xl">
                <div class="text-sm text-textSub p-4 italic">Carregando cores...</div>
            </div>
        </div>

        <!-- LADO DIREITO: Docker -->
        <div class="flex flex-col gap-6">
            <div>
                <div class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Imagem Docker</div>
                <div class="text-[12px] font-medium text-textSub ml-2 mt-1">
                    {{ isset($resource) ? 'Altere a imagem Docker ou escolha uma nova sugestão.' : 'Escolha uma sugestão do core ou informe uma imagem custom.' }}
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <div class="relative">
                    <select
                            id="server-docker-select"
                            class="w-full bg-sidebar rounded-xl px-5 py-4 text-sm text-textValue font-medium focus:ring-2 focus:ring-primary outline-none transition-all duration-300 appearance-none shadow-inner border-none"
                    >
                        <option value="">Selecione um Core primeiro...</option>
                    </select>
                    <div class="absolute right-5 top-1/2 -translate-y-1/2 text-textSub pointer-events-none">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                    </div>
                </div>

                <input
                        type="text"
                        id="server-docker-custom"
                        class="w-full bg-sidebar rounded-xl px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner hidden border-none"
                        placeholder="Imagem personalizada (repo/image:tag)"
                >
            </div>

            <!-- Box de Dica usando o fundo da base para contraste -->
            <div class="bg-bgBase rounded-xl px-5 py-4 text-xs text-textSub leading-relaxed shadow-inner">
                <strong class="text-primary uppercase text-[10px] block mb-1">Dica de Arquiteto:</strong>
                Ao trocar o core, as variáveis de ambiente e o comando de startup podem ser sugeridos automaticamente conforme o template.
            </div>
        </div>
    </div>
</div>

@if(isset($resource))
    <!-- Na edição, os cards de Inicialização e Variáveis de Ambiente vêm para dentro dessa aba unificada -->
    @include('partials.server.cards.startup')
    @include('partials.server.cards.env')
@endif
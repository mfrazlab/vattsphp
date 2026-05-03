<div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col break-inside-avoid w-full mb-8 lg:col-span-2">
    <!-- Cabeçalho mais escuro -->
    <div class="px-8 py-6 bg-sidebar">
        <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">Comando de Startup</h3>
    </div>

    <div class="p-8 flex flex-col gap-6">
        <!-- Input do Comando Customizado -->
        <div class="flex flex-col gap-3">
            <label for="server-startup-command" class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">
                Comando Customizado
            </label>
            <input
                    type="text"
                    name="startupCommand"
                    id="server-startup-command"
                    value="{{ isset($resource) ? data_get($resource, 'startupCommand', '') : '' }}"
                    class="w-full bg-sidebar rounded-xl px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none selection:bg-primary selection:text-textValue"
                    placeholder="Ex.: java -Xms128M -jar server.jar"
            >
            <p class="text-[11px] font-medium text-textSub mt-1 ml-2 leading-relaxed">
                Personalize o comando de inicialização. Se deixado vazio, o sistema usará a base do Core.
            </p>
        </div>

        <!-- Seção do Comando Original (Controlada via JavaScript) -->
        <div id="original-startup-container" class="flex flex-col gap-3 pt-4 border-t border-terciary/20 hidden">
            <label class="text-[11px] font-black text-primary uppercase tracking-widest ml-1">
                Comando Original do Core
            </label>

            <div class="relative">
                <input
                        type="text"
                        id="original-startup-command"
                        value=""
                        readonly
                        onclick="this.select()"
                        class="w-full rounded-xl px-5 py-4 text-sm text-textValue font-mono border-none shadow-inner focus:outline-none selection:bg-primary selection:text-textValue cursor-text"
                        style="background-color: var(--color-console);"
                        title="Clique para selecionar e copiar"
                >
            </div>

            <p class="text-[11px] font-medium text-textSub mt-1 ml-2">
                Este é o comando padrão do core. Útil para referência caso precise restaurar.
            </p>
        </div>
    </div>
</div>
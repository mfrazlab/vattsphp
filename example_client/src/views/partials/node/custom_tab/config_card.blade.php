<!-- Só exibe o card da configuração se o node já estiver criado (tiver ID) -->
@if(isset($resource) && isset($resource->id))
    <div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col mb-8 break-inside-avoid w-full">
        <!-- Cabeçalho mais escuro conforme sua preferência -->
        <div class="px-8 py-6 bg-sidebar">
            <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">Arquivo config.yml</h3>
        </div>
        <div class="p-8 flex flex-col gap-7">
            <div class="flex flex-col gap-2.5 w-full">
                <label class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">Configuração do Daemon</label>

                <div class="relative group">
                    <!-- Container Monaco com a cor de console do seu :root -->
                    <div id="monaco-config-editor" class="w-full h-64 rounded-md overflow-hidden shadow-inner bg-console"></div>
                </div>

                <p class="text-[12px] font-medium text-textSub mt-1 ml-2 leading-relaxed">
                    Copie o conteúdo acima e cole no arquivo <span class="text-primary font-mono">config.yml</span> no servidor onde o node está instalado para vinculá-lo a este painel.
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts do Monaco Editor via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});

            require(['vs/editor/editor.main'], function() {
                // Tema customizado seguindo estritamente as cores do seu :root
                monaco.editor.defineTheme('hightCloudTheme', {
                    base: 'vs-dark',
                    inherit: true,
                    rules: [],
                    colors: {
                        'editor.background': '#0f1419',      // var(--color-console)
                        'editor.lineHighlightBackground': '#1c2530', // var(--color-navbar) para um destaque sutil
                        'editorLineNumber.foreground': '#556476',    // var(--color-terciary)
                        'editorIndentGuide.background': '#26323d',   // var(--color-sidebar)
                        'editorIndentGuide.activeBackground': '#3f4d5c' // var(--color-secondary)
                    }
                });

                const configContent = [
                    "# Gerado Automaticamente",
                    "node:",
                    "  id: {{ $resource->id }}",
                    "  token: {{ $resource->token }}",
                    "  name: \"{{ $resource->name }}\"",
                    "  host: \"{{ $resource->ip }}\"",
                    "  port: {{ $resource->port }}",
                    "  sftp: {{ $resource->sftp }}",
                    "  ssl: {{ $resource->ssl === 'https' ? 'true' : 'false' }}"
                ].join('\n');

                const editor = monaco.editor.create(document.getElementById('monaco-config-editor'), {
                    value: configContent,
                    language: 'yaml',
                    theme: 'hightCloudTheme',
                    readOnly: true,
                    minimap: { enabled: false },
                    scrollBeyondLastLine: false,
                    automaticLayout: true,
                    padding: { top: 16, bottom: 16 },
                    fontSize: 13,
                    fontFamily: "'JetBrains Mono', 'Fira Code', 'Courier New', monospace",
                    renderLineHighlight: 'all',
                    matchBrackets: 'never',
                    hideCursorInOverviewRuler: true
                });
            });
        });
    </script>
@endif
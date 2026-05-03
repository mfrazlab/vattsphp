<!-- Card Customizado de Importação e Exportação JSON - Full Width -->
<div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col lg:flex-row items-center justify-between gap-6 p-6 mb-10 w-full" style="column-span: all;">

    <!-- Seção de Informações (Esquerda) -->
    <div class="flex items-center gap-5 w-full lg:w-auto">
        <div class="w-14 h-14 rounded-2xl bg-sidebar flex items-center justify-center text-primary flex-shrink-0 shadow-inner">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
        </div>
        <div>
            <h3 class="text-[14px] font-black text-textValue uppercase tracking-[0.15em]">Sincronização JSON</h3>
            <p class="text-xs text-textSub font-medium mt-1">Exporte ou importe as configurações completas deste Core rapidamente.</p>
        </div>
    </div>

    <!-- Seção de Ações e Inputs (Direita) - Fundo mais escuro para destaque -->
    <div class="flex flex-col sm:flex-row items-center gap-4 w-full lg:w-auto bg-sidebar p-2.5 rounded-xl">

        <!-- Botão de Exportar -->
        <a href="/admin/cores/{{ $resource->id }}/export" target="_blank" class="w-full sm:w-auto px-6 py-3.5 bg-terciary hover:bg-opacity-80 rounded-xl text-sm font-bold text-textValue transition-all duration-300 flex items-center justify-center gap-2 shadow-sm">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Exportar Backup
        </a>

        <!-- Divisor Sutil -->
        <div class="hidden sm:block w-[1px] h-8 bg-terciary/50"></div>

        <!-- Área de Importação -->
        <div class="w-full sm:w-auto flex flex-col sm:flex-row items-center gap-3 flex-1">
            <input
                    type="file"
                    name="json_file"
                    accept=".json,application/json"
                    class="hidden"
                    id="json_upload_{{ $resource->id }}"
                    onchange="
                    const btn = document.getElementById('import_btn_{{ $resource->id }}');
                    const labelText = document.getElementById('upload_label_text_{{ $resource->id }}');
                    if(this.files.length > 0) {
                        btn.disabled = false;
                        labelText.innerText = this.files[0].name;
                        labelText.classList.add('text-primary');
                    } else {
                        btn.disabled = true;
                        labelText.innerText = 'Selecionar JSON';
                        labelText.classList.remove('text-primary');
                    }
                "
            >
            <label for="json_upload_{{ $resource->id }}" class="w-full sm:w-auto cursor-pointer px-6 py-3.5 bg-terciary/40 hover:bg-terciary/60 rounded-xl text-sm font-bold text-textSub hover:text-textValue transition-all duration-300 flex items-center justify-center gap-2 flex-1 group">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="group-hover:text-primary transition-colors"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span id="upload_label_text_{{ $resource->id }}" class="truncate max-w-[120px]">Selecionar JSON</span>
            </label>

            <button type="button" id="import_btn_{{ $resource->id }}" disabled onclick="
                const fileInput = document.getElementById('json_upload_{{ $resource->id }}');
                if(!fileInput.files.length) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/admin/cores/{{ $resource->id }}/import';
                form.enctype = 'multipart/form-data';
                form.style.display = 'none';

                form.appendChild(fileInput);
                document.body.appendChild(form);
                form.submit();
            " class="w-full sm:w-auto px-8 py-3.5 bg-primary hover:brightness-110 disabled:opacity-30 disabled:bg-sidebar disabled:text-textSub disabled:cursor-not-allowed rounded-xl text-sm font-bold text-textValue transition-all duration-300 flex items-center justify-center shadow-main disabled:shadow-none">
                Importar
            </button>
        </div>
    </div>
</div>
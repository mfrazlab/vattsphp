@if(isset($resource) && isset($deleteUrl))
    <div class="flex flex-col gap-3">
        <button type="button" onclick="window.AdminModal.confirmDelete({
            title: 'Safe Delete',
            message: 'Vai tentar deletar no node primeiro. Se falhar, o registro nao sera removido do banco. Deseja continuar?',
            onConfirm: function() {
                window.location.href = '/admin/{{ str_replace("[id]", $resource->id, $deleteUrl) }}&mode=safe';
            }
        })" class="px-5 py-3 rounded-xl text-sm font-bold text-warning hover:text-textValue bg-warning/10 hover:bg-warning transition-all shadow-main">
            Safe Delete
        </button>
        <button type="button" onclick="window.AdminModal.confirmDelete({
            title: 'Force Delete',
            message: 'Vai deletar do banco mesmo que o node falhe. Deseja continuar?',
            onConfirm: function() {
                window.location.href = '/admin/{{ str_replace("[id]", $resource->id, $deleteUrl) }}&mode=force';
            }
        })" class="px-5 py-3 rounded-xl text-sm font-bold text-danger hover:text-textValue bg-danger/10 hover:bg-danger transition-all shadow-main">
            Force Delete
        </button>
    </div>
@endif


@extends('layouts.admin')

@section('title', 'Não Encontrado')
@section('page_category', 'Erro')
@section('page_name', 'Recurso Indisponível')

@section('content')
    <div class="flex flex-col items-center justify-center min-h-[65vh] animate-[fadeIn_0.3s_ease-out]">
        <div class="w-full max-w-md bg-section rounded-3xl border border-white/[0.03] shadow-2xl p-10 flex flex-col items-center text-center relative overflow-hidden">

            <!-- Efeito de brilho de fundo -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-40 h-40 bg-purple/10 blur-[60px] rounded-full pointer-events-none"></div>

            <!-- Ícone -->
            <div class="w-20 h-20 rounded-2xl bg-darker border border-white/5 flex items-center justify-center text-gray-500 mb-6 shadow-inner relative z-10">
                <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>

            <!-- Textos -->
            <h2 class="text-2xl font-black text-white mb-3 relative z-10 tracking-tight">Registro não encontrado</h2>
            <p class="text-gray-400 text-sm mb-8 relative z-10 leading-relaxed">
                O registro de <strong class="text-purple font-bold">{{ $title ?? 'recurso' }}</strong> que você está tentando acessar não existe, foi removido ou você não tem permissão para visualizá-lo.
            </p>

            <!-- Botão de Ação -->
            <a href="/admin{{ $backTo ?? '/users' }}" class="w-full bg-darker hover:bg-white/[0.03] border border-white/[0.05] hover:border-purple/30 text-gray-300 hover:text-white px-8 py-3.5 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-3 relative z-10 group">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" class="transition-transform group-hover:-translate-x-1 text-purple"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Voltar para a página anterior
            </a>
        </div>
    </div>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
@endsection
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') - Hight Cloud</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="/assets/admin/modal.js"></script>
    <style>
        :root {
            /* Fontes */
            --font-inter: "Inter", ui-sans-serif, system-ui, sans-serif;
            --font-mono: "JetBrains Mono", monospace;

            /* accent principal (verde moderno) */
            --color-primary: #22c55e;

            /* BASE (mais clara, levemente azulada) */
            --color-background: #384350;

            /* CARDS (tem que saltar do fundo) */
            --color-secondary: #4f5e72;
            --color-terciary: #3a495a;

            /* HIERARQUIA DE LAYOUT (aqui tá o segredo) */
            --color-navbar: #1c2530;   /* MAIS ESCURO (topo pesado) */
            --color-sidebar: #26323d;  /* menos escuro que navbar */

            /* console separado */
            --color-console: #0f1419;
            --color-console-command: #151b21;

            /* feedback */
            --color-success: #22c55e;
            --color-info: #38bdf8;
            --color-warning: #facc15;
            --color-danger: #ef4444;

            /* texto */
            --color-text-label: #e6edf3;
            --color-text-value: #ffffff;
            --color-text-sub: #9fb0c0;

            /* sombra mais visível */
            --card-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
        }

        body {
            background-color: var(--color-background);
            color: var(--color-text-value);
            font-family: var(--font-inter);
        }

        /* Layout Structure Transitions */
        .admin-sidebar {
            width: 288px; /* w-72 para dar espaço ao novo design */
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .admin-content {
            margin-left: 288px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Collapsed Sidebar Styles (90px como no React) */
        body.sidebar-collapsed .admin-sidebar { width: 90px; }
        body.sidebar-collapsed .admin-content { margin-left: 90px; }
        body.sidebar-collapsed .sidebar-text { display: none; }
        body.sidebar-collapsed .sidebar-logo-text { display: none; }
        body.sidebar-collapsed .sidebar-logo-icon { display: block !important; }

        /* Categorias quando fechado (Linha pontilhada estilo hr) */
        body.sidebar-collapsed .sidebar-category { font-size: 0; text-align: center; margin-bottom: 0.75rem; opacity: 1; }
        body.sidebar-collapsed .sidebar-category::after {
            content: ""; display: block; width: 16px; height: 2px;
            background-color: var(--color-text-label); opacity: 0.5;
            margin: 0 auto; border-radius: 9999px;
        }

        /* Ajustes dos Links Fechados */
        body.sidebar-collapsed .nav-link { justify-content: center; padding-left: 0; padding-right: 0; gap: 0; }
        body.sidebar-collapsed .nav-link:hover:not(.active) { transform: none; }

        /* Botão de Toggle Fechado */
        .sidebar-chevron-right { display: none; }
        body.sidebar-collapsed .sidebar-chevron-left { display: none; }
        body.sidebar-collapsed .sidebar-chevron-right { display: block; margin: 0 auto; }
        body.sidebar-collapsed #sidebarToggleBottom { justify-content: center; padding-left: 0; padding-right: 0; }

        /* Scrollbar exclusiva e elegante para a Sidebar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--color-terciary); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--color-primary); }

        *:focus { outline: none; }

        /* === NOVO ESTILO DOS LINKS (Design React) === */
        .nav-link {
            position: relative;
            padding: 12px 16px;
            border-radius: 12px; /* rounded-xl */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--color-text-label);
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
            font-weight: 700;
            overflow: hidden;
        }

        .nav-link:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.02);
            transform: translateX(4px);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.04);
            color: var(--color-text-value);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        /* Fundo com 3% de opacidade primaria para o card ativo */
        .nav-link.active::after {
            content: ''; position: absolute; inset: 0;
            background-color: var(--color-primary);
            opacity: 0.03; z-index: 0; pointer-events: none;
        }

        /* Linha brilhante lateral do item ativo */
        .nav-link.active::before {
            content: ''; position: absolute; left: 0; top: 25%; bottom: 25%;
            width: 3px; background-color: var(--color-primary);
            border-radius: 0 9999px 9999px 0;
            box-shadow: 0 0 15px var(--color-primary);
            z-index: 1;
        }

        /* Eleva os filhos pra cima do ::after */
        .nav-link > * { position: relative; z-index: 10; }

        /* Cor do ícone ativo */
        .nav-link.active .sidebar-link-icon { color: var(--color-primary); }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: 'var(--color-primary)',
                        bgBase: 'var(--color-background)',
                        cards: 'var(--color-secondary)',
                        terciary: 'var(--color-terciary)',
                        navbar: 'var(--color-navbar)',
                        sidebar: 'var(--color-sidebar)',
                        textLabel: 'var(--color-text-label)',
                        textValue: 'var(--color-text-value)',
                        textSub: 'var(--color-text-sub)',
                        success: 'var(--color-success)',
                        danger: 'var(--color-danger)'
                    },
                    boxShadow: {
                        main: 'var(--card-shadow)'
                    }
                }
            }
        }
    </script>

    <!-- Script para prevenir "pulo" visual ao carregar a página se a sidebar estiver recolhida -->
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
</head>
<body class="antialiased overflow-x-hidden selection:bg-primary/30 selection:text-textValue">

<!-- SIDEBAR INTEGRADA -->
<aside class="admin-sidebar bg-sidebar flex flex-col z-30 fixed left-0 top-0 h-screen shadow-main">
    <!-- Logo -->
    <div class="h-16 px-6 flex items-center bg-sidebar shrink-0">
        <a href="/" class="flex items-center gap-3 w-full overflow-hidden">
            <!-- Ícone visível apenas quando recolhido -->
            <span class="sidebar-logo-icon hidden text-[22px] font-black text-primary w-full text-center tracking-tighter">HC</span>
            <!-- Texto visível quando expandido -->
            <span class="sidebar-logo-text text-[18px] font-black tracking-tight text-textValue whitespace-nowrap">Hight Cloud</span>
        </a>
    </div>

    <!-- Navegação (Adicionado px-4) -->
    <nav class="flex-1 py-6 overflow-y-auto custom-scrollbar flex flex-col px-4">

        <!-- Categoria: Visão Geral -->
        <div class="flex flex-col mb-8">
            <p class="sidebar-category px-4 text-[10px] font-black text-textLabel uppercase tracking-[0.25em] mb-3 transition-all opacity-50">Visão Geral</p>

            <div class="flex flex-col gap-1 p-2 rounded-2xl bg-black/10 shadow-inner">
                <a href="/admin" class="nav-link {{ (isset($request) && $request->is('/admin')) ? 'active' : '' }}" title="Dashboard">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="sidebar-link-icon shrink-0"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>
                    <span class="sidebar-text whitespace-nowrap">Dashboard</span>
                </a>

                <a href="/admin/settings" class="nav-link {{ (isset($request) && str_starts_with($request->getPath(), '/admin/settings')) ? 'active' : '' }}" title="Configurações">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="sidebar-link-icon shrink-0"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span class="sidebar-text whitespace-nowrap">Configurações</span>
                </a>
            </div>
        </div>

        <!-- Categoria: Gerenciamento -->
        <div class="flex flex-col mb-8">
            <p class="sidebar-category px-4 text-[10px] font-black text-textLabel uppercase tracking-[0.25em] mb-3 transition-all opacity-50">Gerenciamento</p>

            <div class="flex flex-col gap-1 p-2 rounded-2xl bg-black/10 shadow-inner">
                <a href="/admin/nodes" class="nav-link {{ (isset($request) && str_starts_with($request->getPath(), '/admin/nodes')) ? 'active' : '' }}" title="Nodes">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="sidebar-link-icon shrink-0"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    <span class="sidebar-text whitespace-nowrap">Nodes</span>
                </a>

                <a href="/admin/servers" class="nav-link {{ (isset($request) && str_starts_with($request->getPath(), '/admin/servers')) ? 'active' : '' }}" title="Servidores">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="sidebar-link-icon shrink-0"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                    <span class="sidebar-text whitespace-nowrap">Servidores</span>
                </a>

                <a href="/admin/users" class="nav-link {{ (isset($request) && str_starts_with($request->getPath(), '/admin/users')) ? 'active' : '' }}" title="Usuários">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="sidebar-link-icon shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span class="sidebar-text whitespace-nowrap">Usuários</span>
                </a>

                <a href="/admin/cores" class="nav-link {{ (isset($request) && str_starts_with($request->getPath(), '/admin/cores')) ? 'active' : '' }}" title="Cores">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="sidebar-link-icon shrink-0"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
                    <span class="sidebar-text whitespace-nowrap">Cores</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Botão de Minimizar/Maximizar no rodapé -->
    <div class="mt-auto pt-4 px-4 pb-4">
        <button id="sidebarToggleBottom" class="flex items-center justify-between w-full py-3 px-4 rounded-xl text-sm font-bold transition-all duration-300 hover:bg-white/[0.04] text-textLabel">
            <span class="sidebar-text whitespace-nowrap">Recolher Menu</span>
            <svg class="sidebar-chevron-left shrink-0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
            <svg class="sidebar-chevron-right shrink-0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
</aside>

<!-- CONTEÚDO PRINCIPAL -->
<div class="admin-content flex flex-col relative min-h-screen">

    <!-- NAVBAR INTEGRADA SUPERIOR -->
    <header class="h-16 px-6 flex items-center justify-between sticky top-0 bg-navbar z-20 shadow-main">
        <!-- Lado Esquerdo: Hambúrguer e Breadcrumbs -->
        <div class="flex items-center gap-6">
            <button id="sidebarToggle" class="text-textSub hover:text-textValue transition-colors" title="Alternar Menu">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>

            <div class="hidden sm:flex items-center gap-2 text-[12px] font-bold uppercase tracking-wider text-textSub">
                <span>@yield('page_category', 'Administração')</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-terciary"><path d="M9 18l6-6-6-6"/></svg>
                <span class="text-textValue">@yield('page_name', 'Visão Geral')</span>
            </div>
        </div>

        <!-- Lado Direito: Perfil de Usuário e Ações -->
        <div class="flex items-center gap-4">

            <!-- Barra de Pesquisa Rápida -->
            @if(isset($resources) || isset($showSearch))
                <form action="" method="GET" class="relative group mr-2 hidden md:block">
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-textSub">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg>
                    </div>
                    <input
                            type="text"
                            name="search"
                            value="{{ isset($request) && isset($request->getQuery()['search']) ? $request->getQuery()['search'] : '' }}"
                            placeholder="Buscar..."
                            class="bg-terciary rounded-lg pl-9 pr-3 py-1.5 text-sm w-48 focus:w-64 outline-none transition-all text-textValue placeholder-textSub border-none shadow-inner"
                    >
                </form>
            @endif

            <!-- Usuário Info -->
            <div class="flex items-center gap-3 pl-2">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(($user->first_name ?? 'A') . ' ' . ($user->last_name ?? 'D')) }}&background=22c55e&color=fff" alt="Avatar" class="w-8 h-8 rounded-full shadow-main">
                <span class="text-sm font-medium text-textValue hidden md:block">{{ $user->first_name ?? 'Admin' }} {{ $user->last_name ?? '' }}</span>
            </div>

            <!-- Ações Extras -->
            <div class="flex items-center gap-1">
                <a href="/" class="p-2 text-textSub hover:text-textValue hover:bg-terciary rounded-lg transition-colors" title="Painel do Cliente">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                </a>

                <form action="/logout" method="POST" class="m-0 p-0">
                    <button type="submit" class="p-2 text-textSub hover:text-danger hover:bg-danger/10 rounded-lg transition-colors" title="Sair">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="p-8 md:p-10 flex-1">
        <!-- Alertas de Sucesso -->
        @if(isset($success))
            <div class="mb-8 p-5 rounded-2xl bg-success/10 text-success text-sm font-bold flex items-center gap-4 shadow-main">
                <div class="w-10 h-10 rounded-xl bg-success/20 flex items-center justify-center flex-shrink-0">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                {{ $success }}
            </div>
        @endif
        <!-- Alertas de Feedback -->
        @if(isset($error))
            <div class="mb-8 p-5 rounded-2xl bg-danger/10 text-danger text-sm font-bold flex items-center gap-4 shadow-main">
                <div class="w-10 h-10 rounded-xl bg-danger/20 flex items-center justify-center flex-shrink-0">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                </div>
                {{ $error }}
            </div>
        @endif

        <!-- CONTEÚDO DINÂMICO AQUI -->
        @yield('content')
    </main>

    <footer class="px-10 py-6 text-textSub flex items-center justify-between">
        <span class="text-xs font-medium">Hight Cloud Admin &copy; {{ date('Y') }}</span>
        <span class="text-xs">Versão 1.0.0</span>
    </footer>
</div>

<!-- Lógica de Toggle da Sidebar -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Agora vincula os dois botões: o da navbar e o novo da sidebar
        const toggleBtns = document.querySelectorAll('#sidebarToggle, #sidebarToggleBottom');

        // Aplica classe caso tenha sido salvo no localStorage
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }

        toggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-collapsed');

                // Salva a preferência
                if (document.body.classList.contains('sidebar-collapsed')) {
                    localStorage.setItem('sidebar-collapsed', 'true');
                } else {
                    localStorage.setItem('sidebar-collapsed', 'false');
                }
            });
        });
    });
</script>
</body>
</html>
@extends('layouts.admin')

@section('title', (isset($resource) ? 'Editar ' : 'Criar ') . $title)
@section('page_category', 'Gerenciamento')
@section('page_name', (isset($resource) ? 'Editar ' : 'Criar ') . $title)

@section('content')
    <div class="flex flex-col max-w-[1400px] mx-auto animate-[fadeIn_0.4s_ease-out]">
        <!-- Cabeçalho da Página -->
        <div class="flex justify-between items-end mb-10">
            <div>
                <div class="flex items-center gap-4 mb-2">
                    <a href="/admin{{ $backTo }}" class="w-10 h-10 rounded-2xl bg-cards hover:bg-terciary flex items-center justify-center text-textSub hover:text-textValue transition-all shadow-main transform hover:-translate-x-1">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    </a>
                    <h1 class="text-4xl font-black tracking-tight text-textValue">{{ isset($resource) ? 'Editar' : 'Criar' }} {{ $title }}</h1>
                </div>
                <p class="text-textSub text-sm font-medium ml-14">
                    Preencha os campos abaixo para {{ isset($resource) ? 'atualizar os dados do registro' : 'adicionar um novo registro' }}.
                </p>
            </div>
        </div>

        <!-- Formulário Principal -->
        <form action="" method="POST" class="flex flex-col gap-8">
            @php
                $resourceExists = isset($resource);
                $mapCategories = (isset($map) && is_array($map)) ? $map : [];
                $customCardList = (isset($custom_cards) && is_array($custom_cards)) ? $custom_cards : [];
                $customTabList = (isset($custom_tabs) && is_array($custom_tabs)) ? $custom_tabs : [];

                $isBelow = isset($isBelow) ? $isBelow : false;

                // Restaura a lógica padrão e adiciona uma flag opcional 'force_tabs'
                $forceTabs = isset($force_tabs) ? $force_tabs : false;
                $tabsEnabled = ($resourceExists || $forceTabs) && (count($mapCategories) > 0 || count($customTabList) > 0);

                $tabs = [];

                $makeKey = function ($value, $fallback) {
                    $key = strtolower((string) $value);
                    $key = preg_replace('/[^a-z0-9]+/i', '-', $key);
                    $key = trim($key, '-');

                    return $key !== '' ? $key : $fallback;
                };

                foreach ($mapCategories as $categoryName => $fields) {
                    $tabs[] = [
                        'key' => 'category-' . $makeKey($categoryName, 'category-' . count($tabs)),
                        'label' => $categoryName,
                        'type' => 'category',
                        'fields' => $fields,
                    ];
                }

                foreach ($customTabList as $index => $customTab) {
                    $fallbackKey = 'custom-tab-' . $index;
                    $customTabData = is_array($customTab) ? $customTab : [];

                    if (is_string($customTab)) {
                        $customTabData = [
                            'view' => $customTab,
                            'label' => basename(str_replace('\\', '/', $customTab)),
                        ];
                    }

                    if (!is_array($customTabData) || empty($customTabData)) {
                        continue;
                    }

                    $tabs[] = [
                        'key' => $makeKey($customTabData['key'] ?? $customTabData['id'] ?? $customTabData['label'] ?? $fallbackKey, $fallbackKey),
                        'label' => $customTabData['label'] ?? ('Custom ' . ($index + 1)),
                        'type' => 'custom',
                        'view' => $customTabData['view'] ?? $customTabData['template'] ?? null,
                        'content' => $customTabData['content'] ?? null,
                        'data' => $customTabData['data'] ?? [],
                    ];
                }

                $activeTabKey = $tabs[0]['key'] ?? null;
            @endphp

                    <!-- Cards customizados renderizados ACIMA -->
            @if(!$isBelow && count($customCardList) > 0)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    @foreach($customCardList as $card)
                        @include($card)
                    @endforeach
                </div>
            @endif

            @if($tabsEnabled)
                <div class="flex flex-col gap-6" data-resource-tabs>
                    <div class="bg-cards shadow-main rounded-md p-3 flex flex-wrap gap-3">
                        @foreach($tabs as $tab)
                            <button
                                    type="button"
                                    data-tab-button
                                    data-tab-target="{{ $tab['key'] }}"
                                    class="px-5 py-3 rounded-md text-sm font-bold transition-all duration-300 {{ $tab['key'] === $activeTabKey ? 'bg-primary text-textValue shadow-main' : 'bg-sidebar text-textSub hover:text-textValue hover:bg-terciary' }}"
                                    aria-selected="{{ $tab['key'] === $activeTabKey ? 'true' : 'false' }}"
                            >
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-8">
                        @foreach($tabs as $tab)
                            <div
                                    id="{{ $tab['key'] }}"
                                    data-tab-panel
                                    data-tab-panel-key="{{ $tab['key'] }}"
                                    class="{{ $tab['key'] === $activeTabKey ? '' : 'hidden' }} animate-[fadeIn_0.25s_ease-out]"
                            >
                                @if($tab['type'] === 'custom')
                                    @if(!empty($tab['view']))
                                        @include($tab['view'], $tab['data'])
                                    @elseif(!empty($tab['content']))
                                        {!! $tab['content'] !!}
                                    @else
                                        <div class="bg-cards shadow-main rounded-md p-8 text-textSub">
                                            Nenhum conteúdo definido para esta aba.
                                        </div>
                                    @endif
                                @else
                                    <div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col break-inside-avoid w-full">
                                        <div class="px-8 py-6 bg-sidebar">
                                            <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">{{ $tab['label'] }}</h3>
                                        </div>
                                        <div class="p-8 flex flex-col gap-7">
                                            @foreach($tab['fields'] as $field)
                                                @php
                                                    if(isset($field['form']) && $field['form'] === false) continue;

                                                    if(isset($field['onlyShowInEdit']) && $field['onlyShowInEdit'] && !isset($resource)) continue;

                                                    $key = $field['key'];
                                                    $label = $field['label'];
                                                    $type = $field['type'] ?? 'text';
                                                    $desc = $field['desc'] ?? null;
                                                    $placeholder = $field['placeholder'] ?? '';

                                                    $value = isset($resource)
                                                        ? data_get($resource, $key, $field['default'] ?? '')
                                                        : ($field['default'] ?? '');

                                                    $isReadonly = isset($field['readonly']) && $field['readonly'];
                                                    $readonlyClass = $isReadonly ? 'opacity-60 cursor-not-allowed pointer-events-none' : '';
                                                    $readonlyAttr = $isReadonly ? 'readonly tabindex="-1"' : '';
                                                @endphp

                                                <div class="flex flex-col gap-2.5 {{ isset($field['full_width']) && $field['full_width'] ? 'w-full' : '' }}">
                                                    <label for="{{ $key }}" class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">
                                                        {{ $label }}
                                                        @if(isset($field['required']) && $field['required'])
                                                            <span class="text-primary ml-1 text-sm">*</span>
                                                        @endif
                                                    </label>

                                                    @if($type === 'select' || str_starts_with($type, 'enum'))
                                                        <div class="relative">
                                                            <select
                                                                    id="{{ $key }}"
                                                                    name="{{ $key }}"
                                                                    class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium focus:ring-2 focus:ring-primary outline-none transition-all duration-300 appearance-none shadow-inner border-none {{ $readonlyClass }}"
                                                                    {{ (isset($field['required']) && $field['required']) ? 'required data-tab-original-required="1"' : '' }}
                                                                    {!! $readonlyAttr !!}
                                                            >
                                                                @if(isset($field['options']) && is_array($field['options']))
                                                                    @foreach($field['options'] as $optValue => $optLabel)
                                                                        <option value="{{ $optValue }}" {{ (string)$value === (string)$optValue ? 'selected' : '' }}>
                                                                            {{ $optLabel }}
                                                                        </option>
                                                                    @endforeach
                                                                @endif
                                                            </select>
                                                            <div class="absolute right-5 top-1/2 -translate-y-1/2 text-textSub pointer-events-none">
                                                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                                                            </div>
                                                        </div>

                                                    @elseif(str_starts_with($type, 'monaco:'))
                                                        @php
                                                            $monacoLang = explode(':', $type)[1] ?? 'javascript';
                                                        @endphp
                                                        <div class="relative w-full rounded-md overflow-hidden shadow-inner bg-console focus-within:ring-2 focus-within:ring-primary transition-all duration-300 border-none" style="height: 350px;">
                                                            <div class="monaco-container w-full h-full"
                                                                 data-input-id="{{ $key }}"
                                                                 data-language="{{ $monacoLang }}"
                                                                    {!! $isReadonly ? 'data-readonly="true"' : '' !!}></div>
                                                            <textarea
                                                                    id="{{ $key }}"
                                                                    name="{{ $key }}"
                                                                    style="position: absolute; bottom: 0; opacity: 0; z-index: -1; pointer-events: none;"
                                                                    {{ (isset($field['required']) && $field['required']) ? 'required data-tab-original-required="1"' : '' }}
                                                            >{!! $value !!}</textarea>
                                                        </div>

                                                    @elseif($type === 'textarea')
                                                        <textarea
                                                                id="{{ $key }}"
                                                                name="{{ $key }}"
                                                                rows="4"
                                                                placeholder="{{ $placeholder }}"
                                                                class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 resize-y shadow-inner border-none {{ $readonlyClass }}"
                                                        {{ (isset($field['required']) && $field['required']) ? 'required data-tab-original-required="1"' : '' }}
                                                                {!! $readonlyAttr !!}
                                                    >{{ $value }}</textarea>

                                                    @elseif($type === 'password')
                                                        <input
                                                                type="password"
                                                                id="{{ $key }}"
                                                                name="{{ $key }}"
                                                                placeholder="••••••••••••"
                                                                class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner tracking-[0.3em] border-none {{ $readonlyClass }}"
                                                                {{ (!isset($resource) && isset($field['required']) && $field['required']) ? 'required data-tab-original-required="1"' : '' }}
                                                                {!! $readonlyAttr !!}
                                                        >

                                                    @else
                                                        <input
                                                                type="{{ $type === 'user' ? 'text' : $type }}"
                                                                id="{{ $key }}"
                                                                name="{{ $key }}"
                                                                value="{{ $value }}"
                                                                placeholder="{{ $placeholder }}"
                                                                class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none {{ $readonlyClass }}"
                                                                {{ (isset($field['required']) && $field['required']) ? 'required data-tab-original-required="1"' : '' }}
                                                                {!! $readonlyAttr !!}
                                                        >
                                                    @endif

                                                    @if(!empty($desc))
                                                        <p class="text-[12px] font-medium text-textSub mt-1 ml-2 leading-relaxed">{{ $desc }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <!-- Grid de Categorias (Campos Auto-Gerados Padrão) -->
                <div class="columns-1 lg:columns-2 gap-8">

                    <!-- Campos Auto-Gerados (Padrão) -->
                    @foreach($mapCategories as $categoryName => $fields)
                        <div class="bg-cards shadow-main rounded-md overflow-hidden flex flex-col mb-8 break-inside-avoid w-full">
                            <div class="px-8 py-6 bg-sidebar">
                                <h3 class="text-[12px] font-black text-textValue uppercase tracking-[0.2em]">{{ $categoryName }}</h3>
                            </div>
                            <div class="p-8 flex flex-col gap-7">
                                @foreach($fields as $field)
                                    @php
                                        if(isset($field['form']) && $field['form'] === false) continue;

                                        if(isset($field['onlyShowInEdit']) && $field['onlyShowInEdit'] && !isset($resource)) continue;

                                        $key = $field['key'];
                                        $label = $field['label'];
                                        $type = $field['type'] ?? 'text';
                                        $desc = $field['desc'] ?? null;
                                        $placeholder = $field['placeholder'] ?? '';

                                        $value = isset($resource)
                                            ? data_get($resource, $key, $field['default'] ?? '')
                                            : ($field['default'] ?? '');

                                        $isReadonly = isset($field['readonly']) && $field['readonly'];
                                        $readonlyClass = $isReadonly ? 'opacity-60 cursor-not-allowed pointer-events-none' : '';
                                        $readonlyAttr = $isReadonly ? 'readonly tabindex="-1"' : '';
                                    @endphp

                                    <div class="flex flex-col gap-2.5 {{ isset($field['full_width']) && $field['full_width'] ? 'w-full' : '' }}">
                                        <label for="{{ $key }}" class="text-[11px] font-black text-textSub uppercase tracking-widest ml-1">
                                            {{ $label }}
                                            @if(isset($field['required']) && $field['required'])
                                                <span class="text-primary ml-1 text-sm">*</span>
                                            @endif
                                        </label>

                                        @if($type === 'select' || str_starts_with($type, 'enum'))
                                            <div class="relative">
                                                <select
                                                        id="{{ $key }}"
                                                        name="{{ $key }}"
                                                        class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium focus:ring-2 focus:ring-primary outline-none transition-all duration-300 appearance-none shadow-inner border-none {{ $readonlyClass }}"
                                                        {{ (isset($field['required']) && $field['required']) ? 'required' : '' }}
                                                        {!! $readonlyAttr !!}
                                                >
                                                    @if(isset($field['options']) && is_array($field['options']))
                                                        @foreach($field['options'] as $optValue => $optLabel)
                                                            <option value="{{ $optValue }}" {{ (string)$value === (string)$optValue ? 'selected' : '' }}>
                                                                {{ $optLabel }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                <div class="absolute right-5 top-1/2 -translate-y-1/2 text-textSub pointer-events-none">
                                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                                                </div>
                                            </div>

                                        @elseif(str_starts_with($type, 'monaco:'))
                                            @php
                                                $monacoLang = explode(':', $type)[1] ?? 'javascript';
                                            @endphp
                                            <div class="relative w-full rounded-md overflow-hidden shadow-inner bg-console focus-within:ring-2 focus-within:ring-primary transition-all duration-300 border-none" style="height: 350px;">
                                                <div class="monaco-container w-full h-full"
                                                     data-input-id="{{ $key }}"
                                                     data-language="{{ $monacoLang }}"
                                                        {!! $isReadonly ? 'data-readonly="true"' : '' !!}></div>
                                                <textarea
                                                        id="{{ $key }}"
                                                        name="{{ $key }}"
                                                        style="position: absolute; bottom: 0; opacity: 0; z-index: -1; pointer-events: none;"
                                                        {{ (isset($field['required']) && $field['required']) ? 'required' : '' }}
                                                >{!! $value !!}</textarea>
                                            </div>

                                        @elseif($type === 'textarea')
                                            <textarea
                                                    id="{{ $key }}"
                                                    name="{{ $key }}"
                                                    rows="4"
                                                    placeholder="{{ $placeholder }}"
                                                    class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 resize-y shadow-inner border-none {{ $readonlyClass }}"
                                            {{ (isset($field['required']) && $field['required']) ? 'required' : '' }}
                                                    {!! $readonlyAttr !!}
                                        >{{ $value }}</textarea>

                                        @elseif($type === 'password')
                                            <input
                                                    type="password"
                                                    id="{{ $key }}"
                                                    name="{{ $key }}"
                                                    placeholder="••••••••••••"
                                                    class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner tracking-[0.3em] border-none {{ $readonlyClass }}"
                                                    {{ (!isset($resource) && isset($field['required']) && $field['required']) ? 'required' : '' }}
                                                    {!! $readonlyAttr !!}
                                            >

                                        @else
                                            <input
                                                    type="{{ $type === 'user' ? 'text' : $type }}"
                                                    id="{{ $key }}"
                                                    name="{{ $key }}"
                                                    value="{{ $value }}"
                                                    placeholder="{{ $placeholder }}"
                                                    class="w-full bg-sidebar rounded-md px-5 py-4 text-sm text-textValue font-medium placeholder-textSub focus:ring-2 focus:ring-primary outline-none transition-all duration-300 shadow-inner border-none {{ $readonlyClass }}"
                                                    {{ (isset($field['required']) && $field['required']) ? 'required' : '' }}
                                                    {!! $readonlyAttr !!}
                                            >
                                        @endif

                                        @if(!empty($desc))
                                            <p class="text-[12px] font-medium text-textSub mt-1 ml-2 leading-relaxed">{{ $desc }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                </div>
            @endif

            <!-- Barra de Ações -->
            @if($isBelow && count($customCardList) > 0)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    @foreach($customCardList as $card)
                        @include($card)
                    @endforeach
                </div>

                <!-- Separador Visual -->
                <div class="w-full h-[2px] bg-terciary rounded-full opacity-50 mt-4"></div>
            @endif

            <div class="bg-cards shadow-main rounded-md p-7 flex flex-col sm:flex-row items-center justify-between gap-5 mt-4">
                <div>
                    @if(isset($canDelete) && $canDelete && isset($deleteUrl))
                        <button type="button" onclick="window.AdminModal.confirmDelete({
                            title: 'Excluir Registro',
                            message: 'Tem certeza que deseja excluir este registro?',
                            onConfirm: function() {
                                window.location.href = '/admin/{{ str_replace("[id]", $resource->id, $deleteUrl) }}';
                            }
                        })" class="px-6 py-3.5 rounded-2xl text-sm font-bold text-danger hover:text-textValue bg-danger/10 hover:bg-danger transition-all flex items-center gap-3 group">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" class="group-hover:animate-bounce"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 114 0v2"/></svg>
                            Remover Registro
                        </button>
                    @endif
                </div>

                <div class="flex items-center gap-4 w-full sm:w-auto justify-end">
                    <a href="/admin{{ $backTo }}" class="px-7 py-3.5 rounded-2xl text-sm font-bold text-textSub hover:text-textValue bg-sidebar hover:bg-terciary transition-all shadow-main">
                        Cancelar
                    </a>
                    <button type="submit" class="bg-primary hover:brightness-110 text-textValue px-9 py-3.5 rounded-2xl text-sm font-bold shadow-main transition-all duration-300 flex items-center gap-2 transform hover:-translate-y-1">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        {{ isset($resource) ? 'Salvar Alterações' : 'Criar Registro' }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabsRoot = document.querySelector('[data-resource-tabs]');
            if (!tabsRoot) return;

            const buttons = Array.from(tabsRoot.querySelectorAll('[data-tab-button]'));
            const panels = Array.from(tabsRoot.querySelectorAll('[data-tab-panel]'));
            if (!buttons.length || !panels.length) return;

            const panelFieldSelector = 'input, select, textarea';

            const snapshotRequiredState = () => {
                panels.forEach((panel) => {
                    panel.querySelectorAll(panelFieldSelector).forEach((field) => {
                        if (field.hasAttribute('required')) {
                            field.dataset.originalRequired = '1';
                        }
                    });
                });
            };

            const syncPanelRequirements = (activeKey) => {
                panels.forEach((panel) => {
                    const isActive = panel.dataset.tabPanelKey === activeKey;

                    panel.querySelectorAll(panelFieldSelector).forEach((field) => {
                        if (field.dataset.originalRequired === '1') {
                            if (isActive) {
                                field.setAttribute('required', 'required');
                            } else {
                                field.removeAttribute('required');
                            }
                        }
                    });
                });
            };

            const setActiveTab = (tabKey, updateHash = true) => {
                buttons.forEach((button) => {
                    const isActive = button.dataset.tabTarget === tabKey;
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');

                    // Modificado aqui pro JS acompanhar suas novas classes de cores
                    button.classList.toggle('bg-primary', isActive);
                    button.classList.toggle('text-textValue', isActive);
                    button.classList.toggle('shadow-main', isActive);

                    button.classList.toggle('bg-sidebar', !isActive);
                    button.classList.toggle('text-textSub', !isActive);
                    button.classList.toggle('hover:text-textValue', !isActive);
                    button.classList.toggle('hover:bg-terciary', !isActive);
                });

                panels.forEach((panel) => {
                    const isActive = panel.dataset.tabPanelKey === tabKey;
                    panel.classList.toggle('hidden', !isActive);
                });

                syncPanelRequirements(tabKey);

                const activePanel = panels.find((panel) => panel.dataset.tabPanelKey === tabKey) || null;
                document.dispatchEvent(new CustomEvent('vatts:resource-tab-changed', {
                    detail: {
                        tabKey,
                        panel: activePanel,
                    }
                }));

                if (updateHash && tabKey) {
                    history.replaceState(null, '', `#${tabKey}`);
                }
            };

            snapshotRequiredState();

            const hashKey = window.location.hash ? window.location.hash.replace('#', '') : '';
            const defaultKey = buttons[0].dataset.tabTarget;
            const initialKey = buttons.some((button) => button.dataset.tabTarget === hashKey) ? hashKey : defaultKey;

            setActiveTab(initialKey, false);

            buttons.forEach((button) => {
                button.addEventListener('click', function() {
                    setActiveTab(this.dataset.tabTarget);
                });
            });
        });

        // INICIALIZAÇÃO DO MONACO EDITOR
        document.addEventListener('DOMContentLoaded', function() {
            const monacoContainers = document.querySelectorAll('.monaco-container');

            if (monacoContainers.length > 0) {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js';

                script.onload = () => {
                    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});
                    require(['vs/editor/editor.main'], function() {
                        monacoContainers.forEach(container => {
                            const inputId = container.dataset.inputId;
                            const hiddenInput = document.getElementById(inputId);
                            const isReadonly = container.dataset.readonly === 'true';

                            const editor = monaco.editor.create(container, {
                                value: hiddenInput.value,
                                language: container.dataset.language,
                                theme: 'vs-dark',
                                automaticLayout: true,
                                readOnly: isReadonly,
                                minimap: { enabled: false },
                                scrollBeyondLastLine: false,
                                padding: { top: 16, bottom: 16 },
                                fontSize: 14,
                                fontFamily: "'JetBrains Mono', 'Fira Code', 'Courier New', monospace"
                            });

                            editor.onDidChangeModelContent(() => {
                                hiddenInput.value = editor.getValue();
                            });
                        });
                    });
                };
                document.body.appendChild(script);
            }
        });
    </script>

    <!-- SEÇÃO NOVA: Injeção de Scripts Extras -->
    @if(isset($extra_scripts) && is_array($extra_scripts))
        @foreach($extra_scripts as $script)
            @include($script)
        @endforeach
    @endif

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
@endsection
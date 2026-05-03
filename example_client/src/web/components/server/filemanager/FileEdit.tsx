import React, { useState, useEffect } from "react";
import Editor, { useMonaco } from "@monaco-editor/react";
import Button from "@/web/components/commons/components/Button";
import { ArrowLeft } from "lucide-react";
import { useFileManager } from "./FileManagerContext";
import {useServerContext} from "@/web/contexts/ServerContext";

const SUPPORTED_LANGUAGES = [
    { value: "json", label: "JSON" },
    { value: "yaml", label: "YAML / YML" },
    { value: "properties", label: "Properties" },
    { value: "xml", label: "XML" },
    { value: "javascript", label: "JavaScript" },
    { value: "typescript", label: "TypeScript" },
    { value: "shell", label: "Shell Script (.sh)" },
    { value: "plaintext", label: "Plain Text" },
];

export default function FileEditContainer() {
    const {
        editingFilePath,
        serverId,
        getBreadcrumbs,
        navigateToPath,
        closeEdit,
        readFile,
        writeFile
    } = useFileManager();

    const [content, setContent] = useState("");
    const [language, setLanguage] = useState("plaintext");
    const [isSaving, setIsSaving] = useState(false);
    const [isLoading, setIsLoading] = useState(true);

    const monaco = useMonaco();
    const safeFilePath = editingFilePath || "";
    const breadcrumbs = getBreadcrumbs(safeFilePath);

    // Identifica a linguagem baseada na extensão
    useEffect(() => {
        if (!safeFilePath) return;

        if (safeFilePath.endsWith(".json")) setLanguage("json");
        else if (safeFilePath.endsWith(".yml") || safeFilePath.endsWith(".yaml")) setLanguage("yaml");
        else if (safeFilePath.endsWith(".properties")) setLanguage("properties");
        else if (safeFilePath.endsWith(".sh")) setLanguage("shell");
        else if (safeFilePath.endsWith(".xml")) setLanguage("xml");
        else if (safeFilePath.endsWith(".js")) setLanguage("javascript");
        else if (safeFilePath.endsWith(".ts")) setLanguage("typescript");
        else setLanguage("plaintext");
    }, [safeFilePath]);

    // Tema Dark do Monaco
    useEffect(() => {
        if (monaco) {
            monaco.editor.defineTheme('pterodactyl-dark', {
                base: 'vs-dark',
                inherit: true,
                rules: [],
                colors: {
                    'editor.background': '#0f1419',
                    'editor.lineHighlightBackground': '#151b21',
                }
            });
            monaco.editor.setTheme('pterodactyl-dark');
        }
    }, [monaco]);

    const server = useServerContext(); // Pegue o server aqui também se precisar da trava

    useEffect(() => {
        // Se não tem caminho ou o IP da node ainda não carregou, cancela
        if (!safeFilePath || !server?.nodeUrl) return;

        const fetchFileContent = async () => {
            setIsLoading(true);
            try {
                const response = await readFile(safeFilePath);
                setContent(response.content || "");
            } catch (error) {
                console.error("Erro ao ler o arquivo:", error);
                setContent("// Erro ao carregar o conteúdo.");
            } finally {
                setIsLoading(false);
            }
        };

        fetchFileContent();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [safeFilePath, server?.nodeUrl]);

    // Salva o conteúdo real usando a API
    const handleSave = async () => {
        if (!safeFilePath) return;

        setIsSaving(true);
        try {
            await writeFile(safeFilePath, content);
            // Aqui você pode disparar um toast de sucesso se quiser
        } catch (error) {
            console.error("Erro ao salvar o arquivo:", error);
            // Aqui você pode disparar um toast de erro
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <main className="flex-1 flex flex-col p-6 md:p-8 overflow-hidden relative text-[var(--color-text-value)] h-screen">

            {/* CABEÇALHO / BREADCRUMBS */}
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-3 text-sm font-mono text-[var(--color-text-sub)]">
                    <button
                        onClick={closeEdit}
                        className="p-2 -ml-2 rounded-lg hover:bg-[var(--color-secondary)] hover:text-white transition cursor-pointer"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </button>
                    <div className="flex items-center gap-1">
                        {breadcrumbs.map((crumb, index) => (
                            <React.Fragment key={crumb.path}>
                                <span
                                    className={`cursor-pointer transition ${crumb.isBase ? 'text-[var(--color-text-sub)] hover:text-white' : 'hover:text-white'}`}
                                    onClick={() => {
                                        navigateToPath(crumb.path);
                                    }}
                                >
                                    {crumb.name}
                                </span>
                                {index < breadcrumbs.length - 1 && <span>/</span>}
                            </React.Fragment>
                        ))}
                    </div>
                </div>
            </div>

            {/* CONTAINER DO EDITOR (Ocupa todo o espaço restante) */}
            <div className="flex-1 rounded-t-xl overflow-hidden shadow-[var(--card-shadow)] border border-[var(--color-terciary)] bg-[var(--color-console)] relative">
                <Editor
                    height="100%"
                    language={language}
                    theme="vs-dark"
                    value={isLoading ? "// Carregando arquivo..." : content}
                    onChange={(value) => setContent(value || "")}
                    options={{
                        minimap: { enabled: false },
                        fontSize: 14,
                        fontFamily: 'var(--font-mono)',
                        wordWrap: "on",
                        scrollBeyondLastLine: false,
                        smoothScrolling: true,
                        cursorBlinking: "smooth",
                        padding: { top: 16, bottom: 16 },
                        readOnly: isLoading, // Bloqueia edição enquanto carrega
                    }}
                />
            </div>

            {/* RODAPÉ / AÇÕES */}
            <div className="flex items-center justify-end gap-3 pt-4 pb-2">
                <div className="relative">
                    <select
                        value={language}
                        onChange={(e) => setLanguage(e.target.value)}
                        className="appearance-none bg-[var(--color-secondary)] text-[var(--color-text-label)] font-medium text-sm rounded-xl px-4 py-3 pr-10 outline-none focus:ring-2 focus:ring-[var(--color-primary)] cursor-pointer transition border border-transparent hover:brightness-110 shadow-sm"
                        disabled={isLoading}
                    >
                        {SUPPORTED_LANGUAGES.map((lang) => (
                            <option key={lang.value} value={lang.value}>
                                {lang.label}
                            </option>
                        ))}
                    </select>
                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-[var(--color-text-sub)]">
                        <svg className="w-4 h-4 fill-current" viewBox="0 0 20 20">
                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                        </svg>
                    </div>
                </div>

                <Button
                    variant="info"
                    onClick={handleSave}
                    disabled={isSaving || isLoading}
                    className="min-w-[160px]"
                >
                    {isSaving ? "SALVANDO..." : "SALVAR CONTEÚDO"}
                </Button>
            </div>

        </main>
    );
}
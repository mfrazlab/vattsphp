import React, { useState, useRef, useEffect, useCallback } from "react";
import Button from "@/web/components/commons/components/Button";

import {
    Folder, FileText, MoreHorizontal, Pencil,
    ArrowRightLeft, Lock, Archive, Trash2, Download, FileEdit, FileArchive, X, Check, CloudUpload
} from "lucide-react";

import FileEditContainer from "@/web/components/server/filemanager/FileEdit";
import CreateDirModal from "./modals/CreateDirModal";
import RenameModal from "./modals/RenameModal";
import MoveModal from "./modals/MoveModal";
import CreateFileModal from "./modals/CreateFileModal";
import { useServerContext } from "@/web/contexts/ServerContext";
import { FileManagerProvider, useFileManager } from "./FileManagerContext";
import FileDropdown from "./Dropdown";
import { useLoading } from "@/web/components/wrappers/Wrapper";
import LoadingPage from "@/web/components/commons/LoadingPage";

// === TIPAGENS ===
type FileItem = {
    name: string;
    type: "folder" | "file";
    size: string;
    lastModified: string;
    rawPath: string; // Útil para passar para modais
};

// === FUNÇÕES AUXILIARES ===
const isArchive = (name: string) => /\.(zip|tar\.gz|tgz|rar)$/i.test(name || "");
const isEditable = (name: string) => /\.(txt|json|yml|yaml|properties|js|ts|sh|xml|ini|csv)$/i.test(name || "");

const formatBytes = (bytes: number | null) => {
    if (bytes === null) return "--";
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const formatDate = (timestamp: number) => {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(new Date(timestamp));
};

interface FileManagerProps {
    action?: string;
}

function FileManagerInner({ action = "" }: FileManagerProps) {
    const server = useServerContext();
    const { setLoadingBar } = useLoading();

    // Importando TODA a API necessária do Contexto
    const {
        currentPath,
        isEditOpen,
        editingFilePath,
        navigateToPath,
        navigateToEdit,
        getBreadcrumbs,
        listFiles,
        uploadFile,
        deleteItems,
        archiveItems,

        // Hooks de Estado do Upload
        uploadState,
        totalUploadProgress,
        clearUploads
    } = useFileManager();

    const [files, setFiles] = useState<FileItem[]>([]);
    const [selectedFiles, setSelectedFiles] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    // Controle de Modais
    const [isCreateDirOpen, setIsCreateDirOpen] = useState(false);
    const [isCreateFileOpen, setIsCreateFileOpen] = useState(false);
    const [renameTarget, setRenameTarget] = useState<string | null>(null);
    const [moveTargets, setMoveTargets] = useState<string[] | null>(null);
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);

    // Input file invisível para Upload
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Arrays para o Modal de Upload
    const uploadTasks = Object.values(uploadState);

    // Fecha o modal automaticamente se limparmos tudo
    useEffect(() => {
        if (uploadTasks.length === 0) {
            setIsUploadModalOpen(false);
        }
    }, [uploadTasks.length]);

    // ==========================================
    // BUSCA DE ARQUIVOS (LIST)
    // ==========================================
    const fetchFiles = useCallback(async () => {
        setIsLoading(true);
        setLoadingBar(true);
        try {
            const data = await listFiles(currentPath);

            const formattedFiles = (data.items || []).map((item: any) => ({
                name: item.name,
                type: item.type,
                size: formatBytes(item.size),
                lastModified: formatDate(item.lastModified),
                rawPath: `${currentPath}/${item.name}`
            }));

            // Organiza pastas primeiro, depois arquivos alfabeticamente
            formattedFiles.sort((a: FileItem, b: FileItem) => {
                if (a.type === 'folder' && b.type === 'file') return -1;
                if (a.type === 'file' && b.type === 'folder') return 1;
                return a.name.localeCompare(b.name);
            });

            setFiles(formattedFiles);
            setSelectedFiles([]);
        } catch (error) {
            console.error("Erro ao carregar arquivos:", error);
            setFiles([]);
        } finally {
            setIsLoading(false);
            setLoadingBar(false);
        }
    }, [currentPath, listFiles, setLoadingBar]);

    useEffect(() => {
        if (server?.nodeUrl) {
            fetchFiles();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentPath, server?.nodeUrl]);

    // ==========================================
    // SELEÇÃO E CONTROLE
    // ==========================================
    const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.checked) setSelectedFiles(files.map((f) => f.name));
        else setSelectedFiles([]);
    };

    const handleSelect = (name: string) => {
        if (selectedFiles.includes(name)) {
            setSelectedFiles(selectedFiles.filter((n) => n !== name));
        } else {
            setSelectedFiles([...selectedFiles, name]);
        }
    };

    // ==========================================
    // AÇÕES DE API
    // ==========================================

    // Upload de múltiplos arquivos
    const handleFileSelected = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files;
        if (!selected || selected.length === 0) return;

        setLoadingBar(true);
        try {
            // Executa múltiplos uploads em paralelo
            await Promise.all(
                Array.from(selected).map(file => uploadFile(currentPath, file))
            );
            fetchFiles(); // Recarrega a lista após o sucesso
        } catch (error) {
            console.error("Erro durante o upload:", error);
        } finally {
            setLoadingBar(false);
            if (fileInputRef.current) fileInputRef.current.value = "";
        }
    };

    // Deleção em Massa
    const handleMassDelete = async () => {
        setLoadingBar(true);
        try {
            const fullPaths = selectedFiles.map(name => `${currentPath}/${name}`);
            await deleteItems(fullPaths);
            fetchFiles();
        } catch (error) {
            console.error("Erro ao deletar em massa:", error);
        } finally {
            setLoadingBar(false);
        }
    };

    // Compactação em Massa
    const handleMassArchive = async () => {

        setLoadingBar(true);
        try {
            const fullPaths = selectedFiles.map(name => `${currentPath}/${name}`);
            await archiveItems(fullPaths);
            fetchFiles();
        } catch (error) {
            console.error("Erro ao compactar arquivos:", error);
        } finally {
            setLoadingBar(false);
        }
    };

    const safeAction = action || "";
    const actions = safeAction.split("/");

    if (isLoading && files.length === 0) {
        return <LoadingPage />;
    }

    if (actions[1] === 'edit' || isEditOpen) {
        return <FileEditContainer />;
    }

    const breadcrumbs = getBreadcrumbs(currentPath);

    return (
        <main className="flex-1 flex flex-col p-6 md:p-8 overflow-x-hidden relative text-[var(--color-text-value)]">

            <input
                type="file"
                ref={fileInputRef}
                className="hidden"
                multiple
                onChange={handleFileSelected}
            />

            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div className="flex items-center gap-3 text-sm font-mono text-[var(--color-text-label)]">
                    <input
                        type="checkbox"
                        className="w-4 h-4 rounded border-none bg-[var(--color-terciary)] checked:bg-[var(--color-primary)] cursor-pointer"
                        onChange={handleSelectAll}
                        checked={selectedFiles.length === files.length && files.length > 0}
                    />
                    <div className="flex items-center gap-1">
                        {breadcrumbs.map((crumb, index) => (
                            <React.Fragment key={crumb.path}>
                                <span
                                    className={`cursor-pointer transition ${crumb.isBase ? 'text-[var(--color-text-sub)] hover:text-white' : 'hover:text-white'}`}
                                    onClick={() => navigateToPath(crumb.path)}
                                >
                                    {crumb.name}
                                </span>
                                {index < breadcrumbs.length - 1 && <span>/</span>}
                            </React.Fragment>
                        ))}
                    </div>
                </div>

                <div className="flex gap-2 items-center">
                    {uploadTasks.length > 0 && (
                        <button
                            onClick={() => setIsUploadModalOpen(true)}
                            className="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-white/5 transition"
                            title="Ver Uploads"
                        >
                            <svg className="absolute inset-0 w-full h-full text-white/10" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" strokeWidth="3" />
                            </svg>
                            <svg className="absolute inset-0 w-full h-full text-white animate-spin" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" strokeWidth="3" strokeDasharray="80" strokeDashoffset="60" strokeLinecap="round" />
                            </svg>
                            <CloudUpload className="w-4 h-4 text-[var(--color-text-label)] relative z-10" />
                        </button>
                    )}
                    <Button variant="secondary" onClick={() => setIsCreateDirOpen(true)}>
                        Criar Diretório
                    </Button>
                    <Button variant="info" onClick={() => fileInputRef.current?.click()}>
                        Upload
                    </Button>
                    <Button variant="info" onClick={() => setIsCreateFileOpen(true)}>
                        Novo Arquivo
                    </Button>
                </div>
            </div>

            <div className="rounded-xl overflow-hidden shadow-[var(--card-shadow)] flex flex-col gap-[2px] bg-[var(--color-terciary)] p-[2px]">
                {files.length === 0 && !isLoading && (
                    <div className="p-8 text-center text-[var(--color-text-sub)]">
                        Este diretório está vazio.
                    </div>
                )}

                {files.map((file) => (
                    <div
                        key={file.name}
                        className={`group flex items-center justify-between p-4 bg-[var(--color-secondary)] hover:brightness-110 transition duration-150 ${selectedFiles.includes(file.name) ? 'brightness-125' : ''}`}
                    >
                        <div className="flex items-center gap-4 flex-1">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-none bg-[var(--color-terciary)] checked:bg-[var(--color-primary)] cursor-pointer"
                                checked={selectedFiles.includes(file.name)}
                                onChange={() => handleSelect(file.name)}
                            />
                            {file.type === "folder" ? (
                                <Folder className="w-5 h-5 text-[var(--color-text-sub)] fill-current" />
                            ) : (
                                <FileText className="w-5 h-5 text-[var(--color-text-sub)]" />
                            )}
                            <span
                                className={`font-medium cursor-pointer transition truncate ${isEditable(file.name) ? 'text-[var(--color-text-label)] hover:text-[var(--color-info)]' : 'text-[var(--color-text-label)]'}`}
                                onClick={() => {
                                    if (file.type === "folder") {
                                        navigateToPath(file.rawPath);
                                    } else if (isEditable(file.name)) {
                                        navigateToEdit(file.rawPath);
                                    }
                                }}
                            >
                                {file.name}
                            </span>
                        </div>

                        <div className="hidden md:flex items-center gap-10 text-sm text-[var(--color-text-sub)] w-1/3 justify-end">
                            <span className="w-24 text-right">{file.size}</span>
                            <span className="w-40 text-right">{file.lastModified}</span>
                        </div>

                        <FileDropdown
                            file={file}
                            selectedFiles={selectedFiles}
                            onRename={(name) => setRenameTarget(name)}
                            onMove={(name) => setMoveTargets([name])}
                            onSuccess={fetchFiles}
                        />
                    </div>
                ))}
            </div>

            {selectedFiles.length > 0 && (
                <div className="fixed bottom-8 left-1/2 -translate-x-1/2 bg-[var(--color-terciary)] rounded-2xl shadow-[var(--card-shadow)] px-4 py-3 flex items-center gap-3 z-40 border border-white/5 animate-in slide-in-from-bottom-5">
                    <Button variant="secondary" className="px-6" onClick={() => setMoveTargets(selectedFiles)}>
                        Mover
                    </Button>
                    <Button variant="info" className="px-6" onClick={handleMassArchive}>
                        Compactar
                    </Button>
                    <Button variant="danger" className="px-6" onClick={handleMassDelete}>
                        Excluir
                    </Button>
                </div>
            )}

            {/* MODAL DE UPLOADS */}
            {isUploadModalOpen && uploadTasks.length > 0 && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm animate-in fade-in">
                    <div className="bg-[var(--color-secondary)] rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10">
                        <div className="p-5 flex justify-between items-center border-b border-white/5">
                            <h3 className="text-[var(--color-text-value)] font-medium text-lg">File Uploads</h3>
                            <button onClick={() => setIsUploadModalOpen(false)} className="bg-[var(--color-terciary)] hover:bg-white/10 transition p-1.5 rounded-md text-[var(--color-text-sub)] hover:text-white">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-5">
                            <p className="text-sm text-[var(--color-text-sub)] mb-4">
                                The following files are being uploaded to your server.
                            </p>
                            <div className="space-y-2 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                                {uploadTasks.map((task, idx) => (
                                    <div key={idx} className="flex items-center justify-between bg-[var(--color-terciary)] p-3 rounded-lg border border-white/5">
                                        <div className="flex items-center gap-4 truncate">
                                            {task.status === 'uploading' ? (
                                                <svg className="animate-spin h-5 w-5 text-[var(--color-text-sub)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            ) : task.status === 'error' ? (
                                                <X className="w-5 h-5 text-red-400" />
                                            ) : (
                                                <Check className="w-5 h-5 text-green-400" />
                                            )}
                                            <span className="text-sm text-[var(--color-text-label)] font-mono truncate">{task.fileName}</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-[var(--color-text-sub)]">{task.progress}%</span>
                                            <button className="text-[var(--color-text-sub)] hover:text-white transition">
                                                <X className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="p-4 border-t border-white/5 bg-[var(--color-terciary)] flex justify-end items-center gap-4">
                            <span className="flex-1 text-xs text-[var(--color-text-sub)] px-2">
                                Progresso total: {totalUploadProgress}%
                            </span>
                            <button
                                onClick={() => {
                                    clearUploads();
                                    setIsUploadModalOpen(false);
                                }}
                                className="text-sm text-[var(--color-text-value)] hover:underline font-medium"
                            >
                                Cancel Uploads
                            </button>
                            <Button variant="secondary" onClick={() => setIsUploadModalOpen(false)}>
                                Close
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <CreateDirModal
                isOpen={isCreateDirOpen}
                onClose={() => setIsCreateDirOpen(false)}
                onSuccess={fetchFiles}
            />
            <RenameModal
                target={renameTarget}
                onClose={() => setRenameTarget(null)}
                onSuccess={fetchFiles}
            />
            <MoveModal
                targets={moveTargets}
                onClose={() => setMoveTargets(null)}
                onSuccess={fetchFiles}
            />
            <CreateFileModal
                isOpen={isCreateFileOpen}
                onClose={() => setIsCreateFileOpen(false)}
                onSuccess={fetchFiles}
            />

        </main>
    );
}

export default function FileManagerContainer(props: FileManagerProps) {
    return (
        <FileManagerProvider>
            <FileManagerInner {...props} />
        </FileManagerProvider>
    );
}
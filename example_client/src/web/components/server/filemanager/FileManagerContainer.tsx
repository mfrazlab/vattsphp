import React, { useState, useRef, useEffect, useCallback } from "react";
import Button from "@/web/components/commons/components/Button";

import {
    Folder, FileText, MoreHorizontal, Pencil,
    ArrowRightLeft, Lock, Archive, Trash2, Download, FileEdit, FileArchive, X, Check, CloudUpload,
    ChevronRight, ChevronDown
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
    rawPath: string;
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

// ==========================================
// COMPONENTE DE TREE VIEW (ARVORE)
// ==========================================
const FileTreeNode = ({
                          item,
                          level = 0,
                          onFileClick,
                          listFiles,
                          onRename,
                          onMove,
                          onSuccess,
                          onContextMenuEvent
                      }: {
    item: FileItem;
    level?: number;
    onFileClick: (item: FileItem) => void;
    listFiles: (path: string) => Promise<any>;
    onRename: (target: string) => void;
    onMove: (target: string) => void;
    onSuccess: () => void;
    onContextMenuEvent: (e: React.MouseEvent, item: FileItem) => void;
}) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const [children, setChildren] = useState<FileItem[]>([]);
    const [loading, setLoading] = useState(false);

    const isFolder = item.type === "folder";

    const handleToggle = async (e: React.MouseEvent) => {
        e.stopPropagation();

        // Se for arquivo, só abre no editor
        if (!isFolder) {
            onFileClick(item);
            return;
        }

        // Se for pasta e ainda não carregou os filhos, busca na API
        if (!isExpanded && children.length === 0) {
            setLoading(true);
            try {
                const data = await listFiles(item.rawPath);
                const formattedFiles = (data.items || []).map((child: any) => ({
                    name: child.name,
                    type: child.type,
                    size: formatBytes(child.size),
                    lastModified: formatDate(child.lastModified),
                    rawPath: `${item.rawPath}/${child.name}`
                }));

                formattedFiles.sort((a: FileItem, b: FileItem) => {
                    if (a.type === 'folder' && b.type === 'file') return -1;
                    if (a.type === 'file' && b.type === 'folder') return 1;
                    return a.name.localeCompare(b.name);
                });

                setChildren(formattedFiles);
            } catch (error) {
                console.error("Erro ao carregar subpasta:", error);
            } finally {
                setLoading(false);
            }
        }
        setIsExpanded(!isExpanded);
    };

    return (
        <div className="flex flex-col w-full text-sm">
            <div
                className="flex items-center justify-between py-1.5 px-2 hover:bg-white/10 cursor-pointer text-[var(--color-text-label)] hover:text-white transition-colors gap-1.5"
                style={{ paddingLeft: `${(level * 12) + 8}px` }}
                onClick={handleToggle}
                onContextMenu={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onContextMenuEvent(e, item);
                }}
                title={item.name}
            >
                <div className="flex items-center gap-1.5 min-w-0">
                    <div className="w-4 h-4 flex items-center justify-center shrink-0">
                        {isFolder ? (
                            isExpanded ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />
                        ) : null}
                    </div>
                    <div className="shrink-0">
                        {isFolder ? (
                            <Folder className="w-4 h-4 text-[var(--color-text-sub)] fill-current" />
                        ) : (
                            <FileText className="w-4 h-4 text-[var(--color-text-sub)]" />
                        )}
                    </div>
                    <span className={`truncate ${!isFolder && isEditable(item.name) ? 'hover:text-[var(--color-info)]' : ''}`}>
                        {item.name}
                    </span>
                </div>

                {!isFolder && (
                    <div className="shrink-0">
                        <FileDropdown
                            file={item}
                            selectedFiles={[]}
                            fullPathOverride={item.rawPath}
                            onRename={onRename}
                            onMove={onMove}
                            onSuccess={onSuccess}
                        />
                    </div>
                )}
            </div>

            {/* Renderiza os filhos recursivamente se a pasta estiver expandida */}
            {isExpanded && isFolder && (
                <div className="flex flex-col w-full">
                    {loading ? (
                        <div className="text-xs text-[var(--color-text-sub)] py-1.5" style={{ paddingLeft: `${((level + 1) * 12) + 28}px` }}>
                            Carregando...
                        </div>
                    ) : (
                        children.map(child => (
                            <FileTreeNode
                                key={child.rawPath}
                                item={child}
                                level={level + 1}
                                onFileClick={onFileClick}
                                listFiles={listFiles}
                                onRename={onRename}
                                onMove={onMove}
                                onSuccess={onSuccess}
                                onContextMenuEvent={onContextMenuEvent}
                            />
                        ))
                    )}
                </div>
            )}
        </div>
    );
};


interface FileManagerProps {
    action?: string;
}

function FileManagerInner({ action = "" }: FileManagerProps) {
    const server = useServerContext();
    const { setLoadingBar } = useLoading();

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
        uploadState,
        totalUploadProgress,
        clearUploads
    } = useFileManager();

    const [files, setFiles] = useState<FileItem[]>([]);
    const [selectedFiles, setSelectedFiles] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    const [isCreateDirOpen, setIsCreateDirOpen] = useState(false);
    const [isCreateFileOpen, setIsCreateFileOpen] = useState(false);
    const [renameTarget, setRenameTarget] = useState<string | null>(null);
    const [moveTargets, setMoveTargets] = useState<string[] | null>(null);
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);

    // Context Menu & Upload States
    const [contextMenu, setContextMenu] = useState<{ x: number, y: number, item: FileItem | null } | null>(null);
    const [uploadTarget, setUploadTarget] = useState<string | null>(null);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const uploadTasks = Object.values(uploadState);

    // Fecha o context menu ao clicar fora
    useEffect(() => {
        const handleClickOutside = () => {
            if (contextMenu) setContextMenu(null);
        };
        document.addEventListener("click", handleClickOutside);
        return () => document.removeEventListener("click", handleClickOutside);
    }, [contextMenu]);

    useEffect(() => {
        if (uploadTasks.length === 0) {
            setIsUploadModalOpen(false);
        }
    }, [uploadTasks.length]);

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

    const handleFileSelected = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files;
        if (!selected || selected.length === 0) return;

        setLoadingBar(true);
        // Usa o caminho alvo específico se foi definido via context menu, caso contrário usa o atual
        const targetPath = uploadTarget || currentPath;

        try {
            await Promise.all(
                Array.from(selected).map(file => uploadFile(targetPath, file))
            );
            fetchFiles();
        } catch (error) {
            console.error("Erro durante o upload:", error);
        } finally {
            setLoadingBar(false);
            setUploadTarget(null); // Limpa o alvo para os próximos uploads
            if (fileInputRef.current) fileInputRef.current.value = "";
        }
    };

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
    const isEditing = actions[1] === 'edit' || isEditOpen;

    if (isLoading && files.length === 0) {
        return <LoadingPage />;
    }

    const breadcrumbs = getBreadcrumbs(currentPath);

    return (
        <div className="flex-1 flex w-full h-full text-[var(--color-text-value)] relative z-0">
            <input
                type="file"
                ref={fileInputRef}
                className="hidden"
                multiple
                onChange={handleFileSelected}
            />

            {/* CONTEXT MENU GLOBLAL DA TREE VIEW */}
            {contextMenu && (
                <div
                    className="fixed z-[99999] bg-[var(--color-terciary)] border border-white/10 shadow-2xl rounded-lg py-1.5 min-w-[180px] animate-in fade-in zoom-in-95 duration-100"
                    style={{ top: contextMenu.y, left: contextMenu.x }}
                    onContextMenu={(e) => e.preventDefault()}
                >
                    <button
                        className="w-full text-left px-4 py-2 text-sm text-[var(--color-text-label)] hover:bg-[var(--color-primary)] hover:text-white flex items-center gap-3 transition-colors"
                        onClick={(e) => {
                            e.stopPropagation();
                            const item = contextMenu.item;
                            let target = currentPath;

                            if (item) {
                                if (item.type === "folder") {
                                    target = item.rawPath;
                                } else {
                                    const lastSlashIndex = item.rawPath.lastIndexOf("/");
                                    if (lastSlashIndex !== -1) {
                                        target = item.rawPath.substring(0, lastSlashIndex);
                                    }
                                }
                            }

                            setUploadTarget(target);
                            fileInputRef.current?.click();
                            setContextMenu(null);
                        }}
                    >
                        <CloudUpload className="w-4 h-4" /> Upload Aqui
                    </button>

                    {contextMenu.item && (
                        <>
                            <button
                                className="w-full text-left px-4 py-2 text-sm text-[var(--color-text-label)] hover:bg-[var(--color-primary)] hover:text-white flex items-center gap-3 transition-colors mt-1 border-t border-white/5 pt-2"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setRenameTarget(contextMenu.item!.rawPath);
                                    setContextMenu(null);
                                }}
                            >
                                <Pencil className="w-4 h-4" /> Renomear
                            </button>
                            <button
                                className="w-full text-left px-4 py-2 text-sm text-[var(--color-text-label)] hover:bg-[var(--color-primary)] hover:text-white flex items-center gap-3 transition-colors"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setMoveTargets([contextMenu.item!.rawPath]);
                                    setContextMenu(null);
                                }}
                            >
                                <ArrowRightLeft className="w-4 h-4" /> Mover
                            </button>
                        </>
                    )}
                </div>
            )}

            {isEditing ? (
                // ==========================================
                // SIDEBAR (MODO IDE COM TREE VIEW)
                // ==========================================
                <main className="hidden lg:flex flex-col w-64 lg:w-72 border-r border-white/5 bg-[var(--color-terciary)]/30 relative z-10 shrink-0">
                    {/* Header Explorer */}
                    <div className="p-4 border-b border-white/5 flex items-center justify-between shrink-0 bg-[var(--color-secondary)]/50">
                        <span className="text-xs font-bold text-[var(--color-text-sub)] uppercase tracking-wider truncate">
                            {breadcrumbs[breadcrumbs.length - 1]?.name || 'Root Workspace'}
                        </span>
                        <div className="flex items-center gap-2">
                            <button onClick={() => setIsCreateDirOpen(true)} className="p-1 hover:bg-white/10 rounded-md text-[var(--color-text-sub)] hover:text-white transition" title="Novo Diretório">
                                <Folder className="w-4 h-4" />
                            </button>
                            <button onClick={() => setIsCreateFileOpen(true)} className="p-1 hover:bg-white/10 rounded-md text-[var(--color-text-sub)] hover:text-white transition" title="Novo Arquivo">
                                <FileText className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Tree View List */}
                    <div
                        className="flex-1 overflow-y-auto py-2 custom-scrollbar"
                        onContextMenu={(e) => {
                            // Se clicar no espaço vazio (não num arquivo específico), permite fazer upload na root/pasta atual
                            if (e.target === e.currentTarget) {
                                e.preventDefault();
                                setContextMenu({ x: e.clientX, y: e.clientY, item: null });
                            }
                        }}
                    >
                        {isLoading && files.length === 0 ? (
                            <div className="text-xs text-center text-[var(--color-text-sub)] mt-4">Carregando...</div>
                        ) : files.length === 0 ? (
                            <div className="text-xs text-center text-[var(--color-text-sub)] mt-4">Diretório vazio</div>
                        ) : (
                            files.map(file => (
                                <FileTreeNode
                                    key={file.rawPath}
                                    item={file}
                                    listFiles={listFiles}
                                    onFileClick={(item) => {
                                        if (isEditable(item.name)) navigateToEdit(item.rawPath);
                                    }}
                                    onRename={(target) => setRenameTarget(target)}
                                    onMove={(target) => setMoveTargets([target])}
                                    onSuccess={fetchFiles}
                                    onContextMenuEvent={(e, item) => {
                                        setContextMenu({ x: e.clientX, y: e.clientY, item });
                                    }}
                                />
                            ))
                        )}
                    </div>
                </main>

            ) : (

                // ==========================================
                // GERENCIADOR PADRÃO (LISTA LARGA)
                // ==========================================
                <main className="flex-1 flex flex-col p-6 md:p-8 overflow-y-auto relative z-10">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 shrink-0">
                        <div className="flex items-center gap-3 text-sm font-mono text-[var(--color-text-label)]">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-none bg-[var(--color-terciary)] checked:bg-[var(--color-primary)] cursor-pointer"
                                onChange={handleSelectAll}
                                checked={selectedFiles.length === files.length && files.length > 0}
                            />
                            <div className="flex items-center gap-1 flex-wrap">
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

                        {/* BOTÕES DE AÇÃO */}
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
                            <Button variant="secondary" onClick={() => setIsCreateDirOpen(true)}>Criar Diretório</Button>
                            <Button variant="info" onClick={() => { setUploadTarget(null); fileInputRef.current?.click(); }}>Upload</Button>
                            <Button variant="info" onClick={() => setIsCreateFileOpen(true)}>Novo Arquivo</Button>
                        </div>
                    </div>

                    <div className="rounded-xl shadow-[var(--card-shadow)] flex flex-col gap-[2px] bg-[var(--color-terciary)] p-[2px] shrink-0 overflow-hidden">
                        {files.length === 0 && !isLoading && (
                            <div className="p-8 text-center text-[var(--color-text-sub)]">
                                Este diretório está vazio.
                            </div>
                        )}

                        {files.map((file) => (
                            <div
                                key={file.name}
                                className={`group flex items-center justify-between p-3 bg-[var(--color-secondary)] hover:brightness-110 transition duration-150 ${selectedFiles.includes(file.name) ? 'brightness-125' : ''}`}
                            >
                                <div className="flex items-center gap-3 flex-1 min-w-0">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-none bg-[var(--color-terciary)] checked:bg-[var(--color-primary)] cursor-pointer flex-shrink-0"
                                        checked={selectedFiles.includes(file.name)}
                                        onChange={() => handleSelect(file.name)}
                                    />
                                    {file.type === "folder" ? (
                                        <Folder className="w-5 h-5 text-[var(--color-text-sub)] fill-current flex-shrink-0" />
                                    ) : (
                                        <FileText className="w-5 h-5 text-[var(--color-text-sub)] flex-shrink-0" />
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
                                        title={file.name}
                                    >
                                        {file.name}
                                    </span>
                                </div>

                                <div className="hidden md:flex items-center gap-10 text-sm text-[var(--color-text-sub)] w-1/3 justify-end flex-shrink-0">
                                    <span className="w-24 text-right">{file.size}</span>
                                    <span className="w-40 text-right">{file.lastModified}</span>
                                </div>

                                <div>
                                    <FileDropdown
                                        file={file}
                                        selectedFiles={selectedFiles}
                                        onRename={(name) => setRenameTarget(name)}
                                        onMove={(name) => setMoveTargets([name])}
                                        onSuccess={fetchFiles}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </main>
            )}

            {/* ÁREA DO EDITOR - Z-INDEX GIGANTE PARA SOBREPOR TUDO (MONACO TOOLTIPS LIVRES) */}
            {isEditing && (
                <section className="flex-1 flex flex-col h-full bg-[var(--color-secondary)] animate-in fade-in slide-in-from-right-4 duration-300 relative z-[9999] border-l border-white/5">
                    <FileEditContainer />
                </section>
            )}

            {/* AÇÕES EM MASSA */}
            {selectedFiles.length > 0 && !isEditing && (
                <div className="fixed bottom-8 left-1/2 -translate-x-1/2 bg-[var(--color-terciary)] rounded-2xl shadow-[var(--card-shadow)] px-4 py-3 flex items-center gap-3 z-[80] border border-white/5 animate-in slide-in-from-bottom-5">
                    <Button variant="secondary" className="px-6" onClick={() => setMoveTargets(selectedFiles)}>Mover</Button>
                    <Button variant="info" className="px-6" onClick={handleMassArchive}>Compactar</Button>
                    <Button variant="danger" className="px-6" onClick={handleMassDelete}>Excluir</Button>
                </div>
            )}

            {/* MODAIS (Upload, Rename, etc...) */}
            {isUploadModalOpen && uploadTasks.length > 0 && (
                <div className="fixed inset-0 z-[11000] flex items-center justify-center bg-black/40 backdrop-blur-sm animate-in fade-in">
                    <div className="bg-[var(--color-secondary)] rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10">
                        <div className="p-5 flex justify-between items-center border-b border-white/5">
                            <h3 className="text-[var(--color-text-value)] font-medium text-lg">File Uploads</h3>
                            <button onClick={() => setIsUploadModalOpen(false)} className="bg-[var(--color-terciary)] hover:bg-white/10 transition p-1.5 rounded-md text-[var(--color-text-sub)] hover:text-white">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-5">
                            <div className="space-y-2 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                                {uploadTasks.map((task, idx) => (
                                    <div key={idx} className="flex items-center justify-between bg-[var(--color-terciary)] p-3 rounded-lg border border-white/5">
                                        <div className="flex items-center gap-4 truncate">
                                            {task.status === 'uploading' ? (
                                                <svg className="animate-spin h-5 w-5 text-[var(--color-text-sub)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            ) : task.status === 'error' ? (
                                                <X className="w-5 h-5 text-red-400" />
                                            ) : (
                                                <Check className="w-5 h-5 text-green-400" />
                                            )}
                                            <span className="text-sm text-[var(--color-text-label)] font-mono truncate">{task.fileName}</span>
                                        </div>
                                        <span className="text-xs text-[var(--color-text-sub)]">{task.progress}%</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="p-4 border-t border-white/5 bg-[var(--color-terciary)] flex justify-end items-center gap-4">
                            <span className="flex-1 text-xs text-[var(--color-text-sub)] px-2">Total: {totalUploadProgress}%</span>
                            <button onClick={() => { clearUploads(); setIsUploadModalOpen(false); }} className="text-sm text-[var(--color-text-value)] hover:underline font-medium">Cancel Uploads</button>
                            <Button variant="secondary" onClick={() => setIsUploadModalOpen(false)}>Close</Button>
                        </div>
                    </div>
                </div>
            )}

            <CreateDirModal isOpen={isCreateDirOpen} onClose={() => setIsCreateDirOpen(false)} onSuccess={fetchFiles} />
            <RenameModal target={renameTarget} onClose={() => setRenameTarget(null)} onSuccess={fetchFiles} />
            <MoveModal targets={moveTargets} onClose={() => setMoveTargets(null)} onSuccess={fetchFiles} />
            <CreateFileModal isOpen={isCreateFileOpen} onClose={() => setIsCreateFileOpen(false)} onSuccess={fetchFiles} />

        </div>
    );
}

export default function FileManagerContainer(props: FileManagerProps) {
    return (
        <FileManagerProvider>
            <FileManagerInner {...props} />
        </FileManagerProvider>
    );
}
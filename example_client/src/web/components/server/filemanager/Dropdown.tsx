import React, { useState, useEffect } from "react";
import {
    Pencil, ArrowRightLeft, Lock, Archive, Trash2, Download, FileEdit, FileArchive
} from "lucide-react";
import { useFileManager } from "./FileManagerContext";

interface FileItem {
    name: string;
    type: "folder" | "file";
    size: string;
    lastModified: string;
}

interface DropdownProps {
    file: FileItem;
    selectedFiles: string[];
    onRename: (name: string) => void;
    onMove: (name: string) => void;
    onSuccess?: () => void;
}

const isArchive = (name: string) => /\.(zip|tar\.gz|tgz|rar)$/i.test(name);
const isEditable = (name: string) => /\.(txt|json|yml|yaml|properties|js|ts|sh|xml|ini|csv)$/i.test(name);

// Simple internal icon
function MoreHorizontalIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
        </svg>
    );
}

export default function FileDropdown({
                                         file,
                                         selectedFiles,
                                         onRename,
                                         onMove,
                                         onSuccess
                                     }: DropdownProps) {
    const [isOpen, setIsOpen] = useState(false);
    const {
        navigateToEdit,
        currentPath,
        deleteItems,
        archiveItems,
        unarchiveItem,
        downloadFile
    } = useFileManager();

    useEffect(() => {
        const handleClickOutside = () => setIsOpen(false);
        document.addEventListener("click", handleClickOutside);
        return () => document.removeEventListener("click", handleClickOutside);
    }, []);

    const toggleMenu = (e: React.MouseEvent) => {
        e.stopPropagation();
        setIsOpen(!isOpen);
    };

    const runAction = async (actionFn: () => Promise<void>) => {
        try {
            await actionFn();
            if (onSuccess) onSuccess();
        } catch (error) {
            console.error("Ação falhou:", error);
        } finally {
            setIsOpen(false);
        }
    };

    const fullPath = `${currentPath}/${file.name}`;

    const handleEdit = () => {
        navigateToEdit(fullPath);
        setIsOpen(false);
    };

    const handleDelete = () => {
        if (!confirm(`Tem certeza que deseja excluir '${file.name}'?`)) return;
        runAction(() => deleteItems([fullPath]));
    };

    const handleArchive = () => {
        runAction(() => archiveItems([fullPath]));
    };

    const handleUnarchive = () => {
        runAction(() => unarchiveItem(fullPath));
    };

    const handleDownload = () => {
        runAction(() => downloadFile(fullPath));
    };

    return (
        <div className="ml-6 relative">
            <button
                onClick={toggleMenu}
                className="p-2 rounded-lg hover:bg-[var(--color-terciary)] transition text-[var(--color-text-sub)] hover:text-white"
            >
                <MoreHorizontalIcon />
            </button>

            {isOpen && (
                <div className="absolute right-0 top-10 mt-1 w-48 bg-[var(--color-terciary)] rounded-xl shadow-[var(--card-shadow)] z-50 text-[var(--color-text-label)] py-2 font-medium text-sm border border-white/5">
                    {isEditable(file.name) && (
                        <button
                            onClick={handleEdit}
                            className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                        >
                            <FileEdit className="w-4 h-4" /> Editar
                        </button>
                    )}

                    <button
                        onClick={() => { onRename(file.name); setIsOpen(false); }}
                        className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                    >
                        <Pencil className="w-4 h-4" /> Renomear
                    </button>

                    <button
                        onClick={() => { onMove(file.name); setIsOpen(false); }}
                        className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                    >
                        <ArrowRightLeft className="w-4 h-4" /> Mover
                    </button>

                    {isArchive(file.name) ? (
                        <button
                            onClick={handleUnarchive}
                            className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                        >
                            <FileArchive className="w-4 h-4" /> Extrair
                        </button>
                    ) : (
                        <button
                            onClick={handleArchive}
                            className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                        >
                            <Archive className="w-4 h-4" /> Compactar
                        </button>
                    )}

                    <button
                        onClick={() => { alert("Configuração de permissões em breve"); setIsOpen(false); }}
                        className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                    >
                        <Lock className="w-4 h-4" /> Permissões
                    </button>

                    {file.type !== "folder" && (
                        <button
                            onClick={handleDownload}
                            className="w-full text-left px-4 py-2 hover:bg-white/5 flex items-center gap-3"
                        >
                            <Download className="w-4 h-4" /> Baixar
                        </button>
                    )}

                    <div className="h-px bg-white/10 my-1"></div>

                    <button
                        onClick={handleDelete}
                        className="w-full text-left px-4 py-2 hover:bg-[var(--color-danger)]/10 text-[var(--color-danger)] flex items-center gap-3"
                    >
                        <Trash2 className="w-4 h-4" /> Excluir
                    </button>
                </div>
            )}
        </div>
    );
}
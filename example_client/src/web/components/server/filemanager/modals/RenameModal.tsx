import React, { useState, useEffect } from "react";
import Button from "@/web/components/commons/components/Button";
import { useFileManager } from "../FileManagerContext";

interface RenameModalProps {
    target: string | null;
    onClose: () => void;
    onSuccess?: () => void;
}

export default function RenameModal({ target, onClose, onSuccess }: RenameModalProps) {
    const { renameItem, currentPath } = useFileManager();
    const [newName, setNewName] = useState("");
    const [loading, setLoading] = useState(false);

    const targetName = target ? target.split("/").pop() || target : "";
    const isFullPathTarget = !!target && target.includes("/");

    // Sincroniza o valor inicial do input com o nome do arquivo atual quando o modal abre
    useEffect(() => {
        if (target) {
            setNewName(targetName);
        }
    }, [target, targetName]);

    if (!target) return null;

    const handleRename = async () => {
        if (!newName.trim() || newName === targetName) {
            onClose(); // Se não mudou nada, só fecha
            return;
        }

        setLoading(true);
        try {
            const fromPath = isFullPathTarget ? target : `${currentPath}/${target}`;
            await renameItem(fromPath, newName);
            if (onSuccess) onSuccess();
            onClose();
        } catch (error) {
            console.error("Erro ao renomear:", error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[11000] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-[var(--color-secondary)] p-6 rounded-2xl shadow-[var(--card-shadow)] w-full max-w-md border border-white/5" onClick={e => e.stopPropagation()}>
                <h3 className="text-lg font-semibold text-white mb-4">Renomear Item</h3>
                <p className="text-sm text-[var(--color-text-sub)] mb-4">
                    Renomeando: <strong className="text-white">{targetName}</strong>
                </p>
                <div className="mb-6">
                    <label className="block text-sm font-medium text-[var(--color-text-label)] mb-2">Novo nome</label>
                    <input
                        type="text"
                        autoFocus
                        value={newName}
                        onChange={(e) => setNewName(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && handleRename()}
                        className="w-full bg-[var(--color-terciary)] text-white px-4 py-3 rounded-xl outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition"
                        placeholder="Novo nome do arquivo ou pasta"
                        disabled={loading}
                    />
                </div>
                <div className="flex gap-3 justify-end">
                    <Button variant="ghost" onClick={onClose} disabled={loading}>Cancelar</Button>
                    <Button variant="primary" onClick={handleRename} disabled={loading}>
                        {loading ? "Salvando..." : "Renomear"}
                    </Button>
                </div>
            </div>
        </div>
    );
}
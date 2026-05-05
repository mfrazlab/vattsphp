import React, { useState } from "react";
import Button from "@/web/components/commons/components/Button";
import { useFileManager } from "../FileManagerContext";

interface CreateDirModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess?: () => void;
}

export default function CreateDirModal({ isOpen, onClose, onSuccess }: CreateDirModalProps) {
    const { createDirectory, currentPath } = useFileManager();
    const [dirName, setDirName] = useState("");
    const [loading, setLoading] = useState(false);

    if (!isOpen) return null;

    const handleCreate = async () => {
        if (!dirName.trim()) return;

        setLoading(true);
        try {
            await createDirectory(`${currentPath}/${dirName}`);
            setDirName("");
            if (onSuccess) onSuccess();
            onClose();
        } catch (error) {
            console.error("Erro ao criar diretório:", error);
            // Aqui você pode colocar um toast de erro futuramente
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[11000] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-[var(--color-secondary)] p-6 rounded-2xl shadow-[var(--card-shadow)] w-full max-w-md border border-white/5" onClick={e => e.stopPropagation()}>
                <h3 className="text-lg font-semibold text-white mb-4">Criar Novo Diretório</h3>
                <div className="mb-6">
                    <label className="block text-sm font-medium text-[var(--color-text-label)] mb-2">Nome do diretório</label>
                    <input
                        type="text"
                        autoFocus
                        value={dirName}
                        onChange={(e) => setDirName(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
                        className="w-full bg-[var(--color-terciary)] text-white px-4 py-3 rounded-xl outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition"
                        placeholder="ex: nova_pasta"
                        disabled={loading}
                    />
                </div>
                <div className="flex gap-3 justify-end">
                    <Button variant="ghost" onClick={onClose} disabled={loading}>Cancelar</Button>
                    <Button variant="primary" onClick={handleCreate} disabled={loading}>
                        {loading ? "Criando..." : "Criar Diretório"}
                    </Button>
                </div>
            </div>
        </div>
    );
}
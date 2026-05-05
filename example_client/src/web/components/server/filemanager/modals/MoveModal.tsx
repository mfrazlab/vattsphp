import React, { useState } from "react";
import Button from "@/web/components/commons/components/Button";
import { useFileManager, ROOT_PATH } from "../FileManagerContext";

interface MoveModalProps {
    targets: string[] | null;
    onClose: () => void;
    onSuccess?: () => void;
}

export default function MoveModal({ targets, onClose, onSuccess }: MoveModalProps) {
    const { moveItem, currentPath } = useFileManager();
    const [destination, setDestination] = useState("/");
    const [loading, setLoading] = useState(false);

    if (!targets || targets.length === 0) return null;

    const handleMove = async () => {
        if (!destination.trim()) return;

        setLoading(true);
        try {
            // Formata o destino para incluir o ROOT_PATH caso o usuário digite apenas "/" ou "/plugins"
            const formattedDest = destination.startsWith("/")
                ? `${ROOT_PATH}${destination === "/" ? "" : destination}`
                : `${ROOT_PATH}/${destination}`;

            // Executa a movimentação para cada arquivo selecionado em paralelo
            await Promise.all(targets.map(target => {
                const fromPath = target.includes("/") ? target : `${currentPath}/${target}`;
                return moveItem(fromPath, formattedDest);
            }));

            if (onSuccess) onSuccess();
            onClose();
        } catch (error) {
            console.error("Erro ao mover:", error);
        } finally {
            setLoading(false);
        }
    };

    const targetLabel = targets.length === 1
        ? (targets[0].split("/").pop() || targets[0])
        : `${targets.length} itens selecionados`;

    return (
        <div className="fixed inset-0 z-[11000] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-[var(--color-secondary)] p-6 rounded-2xl shadow-[var(--card-shadow)] w-full max-w-md border border-white/5" onClick={e => e.stopPropagation()}>
                <h3 className="text-lg font-semibold text-white mb-4">Mover Item</h3>
                <p className="text-sm text-[var(--color-text-sub)] mb-4">
                    Você está movendo: <strong className="text-white">{targetLabel}</strong>
                </p>
                <div className="mb-6">
                    <label className="block text-sm font-medium text-[var(--color-text-label)] mb-2">Caminho de destino</label>
                    <input
                        type="text"
                        autoFocus
                        value={destination}
                        onChange={(e) => setDestination(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && handleMove()}
                        className="w-full bg-[var(--color-terciary)] text-white px-4 py-3 rounded-xl outline-none focus:ring-2 focus:ring-[var(--color-info)] transition"
                        placeholder="ex: /plugins"
                        disabled={loading}
                    />
                </div>
                <div className="flex gap-3 justify-end">
                    <Button variant="ghost" onClick={onClose} disabled={loading}>Cancelar</Button>
                    <Button variant="info" onClick={handleMove} disabled={loading}>
                        {loading ? "Movendo..." : "Mover"}
                    </Button>
                </div>
            </div>
        </div>
    );
}
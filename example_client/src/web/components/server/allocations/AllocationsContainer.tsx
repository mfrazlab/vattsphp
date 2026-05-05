import React, { useEffect, useMemo, useState } from "react";
import { useServerContext } from "@/web/contexts/ServerContext";
import LoadingPage from "@/web/components/commons/LoadingPage";
import Card from "@/web/components/commons/components/Card";
import Select from "@/web/components/commons/components/Select";
import Button from "@/web/components/commons/components/Button";
import { useToast } from "@/web/contexts/ToastContext";

type AllocationItem = {
    id: number;
    nodeId?: string | null;
    ip: string;
    externalIp?: string | null;
    port: number;
};

type AdditionalAllocation = AllocationItem & {
    type: "FIXED" | "BYUSER";
};

export default function AllocationsContainer() {
    const { sendApiRequest, server, isLoadingServer } = useServerContext();
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [additionalAllocations, setAdditionalAllocations] = useState<AdditionalAllocation[]>([]);
    const [availableAllocations, setAvailableAllocations] = useState<AllocationItem[]>([]);
    const [selectedAllocationId, setSelectedAllocationId] = useState<string>("");
    const toast = useToast();

    const loadAllocations = async () => {
        if (!server) return;
        setIsLoading(true);
        try {
            const request = await sendApiRequest("/allocations");
            const data = await request.json();

            if (!request.ok) {
                toast.addToast(data.error || "Erro ao carregar alocações.", "error");
                return;
            }

            setAdditionalAllocations(Array.isArray(data.additionalAllocations) ? data.additionalAllocations : []);
            setAvailableAllocations(Array.isArray(data.availableAllocations) ? data.availableAllocations : []);
        } catch (error) {
            console.error("Erro ao carregar alocações adicionais:", error);
            toast.addToast("Erro ao conectar com o servidor.", "error");
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        if (!server) return;
        loadAllocations();
    }, [server]);

    const availableOptions = useMemo(() => {
        return availableAllocations.map((alloc) => ({
            label: `${alloc.ip}:${alloc.port}${alloc.externalIp ? ` (${alloc.externalIp})` : ""}`,
            value: String(alloc.id),
        }));
    }, [availableAllocations]);

    const handleAddAllocation = async () => {
        if (!selectedAllocationId) {
            toast.addToast("Selecione uma porta para adicionar.", "warning");
            return;
        }

        setIsSubmitting(true);
        try {
            const request = await sendApiRequest("/allocations/add", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ allocationId: Number(selectedAllocationId) })
            });
            const data = await request.json();

            if (request.ok) {
                setSelectedAllocationId("");
                setAdditionalAllocations(Array.isArray(data.additionalAllocations) ? data.additionalAllocations : []);
                setAvailableAllocations(Array.isArray(data.availableAllocations) ? data.availableAllocations : []);
                toast.addToast("Porta adicional adicionada.", "success");
            } else {
                toast.addToast(data.error || "Erro ao adicionar allocation.", "error");
            }
        } catch (error) {
            console.error("Erro ao adicionar allocation:", error);
            toast.addToast("Erro de conexão ao adicionar allocation.", "error");
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleRemoveAllocation = async (allocationId: number) => {
        setIsSubmitting(true);
        try {
            const request = await sendApiRequest("/allocations/remove", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ allocationId })
            });
            const data = await request.json();

            if (request.ok) {
                setAdditionalAllocations(Array.isArray(data.additionalAllocations) ? data.additionalAllocations : []);
                setAvailableAllocations(Array.isArray(data.availableAllocations) ? data.availableAllocations : []);
                toast.addToast("Porta removida.", "success");
            } else {
                toast.addToast(data.error || "Erro ao remover allocation.", "error");
            }
        } catch (error) {
            console.error("Erro ao remover allocation:", error);
            toast.addToast("Erro de conexão ao remover allocation.", "error");
        } finally {
            setIsSubmitting(false);
        }
    };

    if (isLoadingServer || isLoading) {
        return (
            <div className="min-h-screen flex justify-center items-center">
                <LoadingPage />
            </div>
        );
    }

    if (!server) return null;

    return (
        <main className="flex-1 flex flex-col p-6 md:p-8 overflow-x-hidden gap-8">
            <div className="grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] gap-6 items-start">
                <Card title="ADICIONAR ALOCACAO">
                    <div className="flex flex-col gap-4">
                        <Select
                            options={availableOptions}
                            value={selectedAllocationId}
                            onChange={(value) => setSelectedAllocationId(String(value))}
                            placeholder={availableOptions.length ? "Selecione uma porta..." : "Sem portas disponiveis"}
                            desc="Portas livres do node atual para adicionar como alocacoes extras."
                        />
                        <Button
                            type="button"
                            onClick={handleAddAllocation}
                            disabled={isSubmitting || !selectedAllocationId}
                            variant="primary"
                        >
                            Adicionar porta
                        </Button>
                    </div>
                </Card>

                <Card title="REGRAS">
                    <div className="text-sm text-(--color-text-sub) space-y-2">
                        <p>Alocacoes FIXED sao definidas pelo admin e nao podem ser removidas aqui.</p>
                        <p>Alocacoes BYUSER podem ser removidas pelo proprio usuario.</p>
                    </div>
                </Card>
            </div>

            <div>
                <h2 className="text-xl font-semibold text-white mb-4">Alocacoes adicionais</h2>

                {additionalAllocations.length === 0 ? (
                    <div className="bg-(--color-secondary) rounded-xl p-6 text-(--color-text-sub)">
                        Nenhuma alocacao adicional configurada.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {additionalAllocations.map((alloc) => (
                            <Card key={`${alloc.type}-${alloc.id}`} title={`${alloc.ip}:${alloc.port}`}>
                                <div className="flex flex-col gap-3 text-sm text-(--color-text-sub)">
                                    <div className="flex items-center justify-between">
                                        <span>Tipo</span>
                                        <span className="text-(--color-text-label)">{alloc.type}</span>
                                    </div>
                                    {alloc.externalIp && (
                                        <div className="flex items-center justify-between">
                                            <span>IP externo</span>
                                            <span className="text-(--color-text-label)">{alloc.externalIp}</span>
                                        </div>
                                    )}
                                    {alloc.type === "BYUSER" ? (
                                        <Button
                                            type="button"
                                            variant="danger"
                                            onClick={() => handleRemoveAllocation(alloc.id)}
                                            disabled={isSubmitting}
                                        >
                                            Remover
                                        </Button>
                                    ) : (
                                        <Button type="button" variant="ghost" disabled>
                                            FIXED (somente admin)
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </main>
    );
}


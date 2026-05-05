import React, { useEffect, useRef, useState } from "react";
import { useServerContext } from "@/web/contexts/ServerContext";
import LoadingPage from "@/web/components/commons/LoadingPage";
import Card from "../../commons/components/Card";
import Input from "@/web/components/commons/components/Input";
import Select from "@/web/components/commons/components/Select";
import Checkbox from "@/web/components/commons/components/Checkbox";
import { useToast } from "@/web/contexts/ToastContext";

export default function StartupContainer() {
    const { sendApiRequest, server, isLoadingServer, connectUsageWs, disconnectConsoleWs } = useServerContext();
    const [startupData, setStartupData] = useState<any>(null);
    const [isLoadingStartup, setIsLoadingStartup] = useState(true);
    const [envVars, setEnvVars] = useState<Record<string, string>>({});

    const [selectedDockerImage, setSelectedDockerImage] = useState<string>("");
    const toast = useToast();
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    useEffect(() => {
        if (!server) return;


        async function fetchStartup() {
            try {
                // Presumo que o GET seja apenas a rota sem body
                const request = await sendApiRequest("/startup");
                const data = await request.json();

                if (request.ok) {
                    setStartupData(data);
                    // @ts-ignore
                    setEnvVars(JSON.parse(server.envVars || "{}") || {});

                    const parsedDockerImages = JSON.parse(data.core.dockerImages || "[]");
                    // @ts-ignore
                    setSelectedDockerImage(server.dockerImage || parsedDockerImages[0]?.image || "");
                } else {
                    toast.addToast(data.error || "Erro ao carregar dados de inicialização.", "error");
                }
            } catch (error) {
                console.error("Erro ao buscar startup:", error);
                toast.addToast("Erro ao conectar com o servidor.", "error");
            } finally {
                setIsLoadingStartup(false);
            }
        }

        fetchStartup();
    }, [server]);

// ===== SALVAR IMAGEM DOCKER =====
    const handleDockerImageChange = async (value: string) => {
        // Atualiza a tela instantaneamente
        setSelectedDockerImage(value);

        try {
            // Usando o RequestInit corretamente
            const request = await sendApiRequest("/startup/docker", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ dockerImage: value })
            });
            const text = await request.text();

            const data = JSON.parse(text);

            if (request.ok) {
                toast.addToast(data.message || "Imagem Docker atualizada com sucesso!", "success");
            } else {
                console.log(text)
                toast.addToast(data.error || "Erro ao atualizar imagem Docker.", "error");
            }
        } catch (error) {
            console.error("Erro ao salvar imagem docker:", error);
            toast.addToast("Erro de conexão ao salvar a imagem.", "error");
        }
    };

    // ===== SALVAR VARIÁVEIS COM DEBOUNCE =====
    const handleVarChange = (envKey: string, value: string) => {
        setEnvVars(prev => ({ ...prev, [envKey]: value }));

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(async () => {
            try {
                // Usando o RequestInit corretamente
                const request = await sendApiRequest("/startup/variable", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        key: envKey,
                        value: value
                    })
                });
                const data = await request.json();

                if (request.ok) {
                    toast.addToast(data.message || "Variável salva com sucesso!", "success");
                } else {
                    toast.addToast(data.error || "Erro ao salvar variável.", "error");
                }
            } catch (error) {
                console.error("Erro ao salvar variável:", error);
                toast.addToast("Erro de conexão ao salvar variável.", "error");
            }
        }, 500);
    };

    if (isLoadingServer || isLoadingStartup) return <div className="min-h-screen flex justify-center items-center"><LoadingPage /></div>;
    if (!server || !startupData) return null;

    const core = startupData.core;
    const variables = JSON.parse(core.variables || "[]");
    const dockerImages = JSON.parse(core.dockerImages || "[]");

    const dockerOptions = dockerImages.map((img: any) => ({
        label: img.name,
        value: img.image
    }));

    return (
        <main className="flex-1 flex flex-col p-6 md:p-8 overflow-x-hidden gap-8">
            <div className="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6 items-start">
                <Card title="COMANDO DE INICIALIZAÇÃO">
                    <Input
                        value={core.startupCommand}
                        readOnly={true}
                        desc="O comando base utilizado para iniciar o servidor."
                    />
                </Card>

                <Card title="IMAGEM DOCKER">
                    <Select
                        options={dockerOptions}
                        value={selectedDockerImage}
                        onChange={handleDockerImageChange}
                        desc="Este é um recurso avançado que permite selecionar uma imagem Docker para usar ao executar esta instância do servidor."
                    />
                </Card>
            </div>

            <div>
                <h2 className="text-xl font-semibold text-white mb-4">Variáveis</h2>
                <div className="columns-1 lg:columns-2 gap-6 space-y-6">
                    {variables.map((v: any) => {
                        const rulesArray = v.rules.split('|');
                        const isBoolean = rulesArray.includes('in:true,false') || rulesArray.includes('in:false,true');
                        const inRule = rulesArray.find((r: string) => r.startsWith('in:') && !isBoolean);
                        const isNumeric = rulesArray.includes('numeric');

                        const currentValue = envVars[v.envVariable] || '';

                        return (
                            <Card key={v.envVariable} title={v.name.toUpperCase()}>
                                {isBoolean ? (
                                    <Checkbox
                                        checked={currentValue === 'true'}
                                        onChange={(checked) => handleVarChange(v.envVariable, checked ? 'true' : 'false')}
                                        desc={v.description}
                                        label=""
                                    />
                                ) : inRule ? (
                                    <Select
                                        options={inRule.replace('in:', '').split(',').map((opt: string) => ({
                                            label: opt,
                                            value: opt
                                        }))}
                                        value={currentValue}
                                        onChange={(val) => handleVarChange(v.envVariable, val as string)}
                                        desc={v.description}
                                    />
                                ) : (
                                    <Input
                                        type={isNumeric ? "number" : "text"}
                                        value={currentValue}
                                        onChange={(e: any) => handleVarChange(v.envVariable, e.target.value)}
                                        desc={v.description}
                                    />
                                )}
                            </Card>
                        );
                    })}
                </div>
            </div>
        </main>
    );
}
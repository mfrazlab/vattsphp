import ServerActions from "@/web/components/server/console/Buttons";
import StatCard from "@/web/components/server/console/StatsCard";
import TerminalConsole from "@/web/components/server/console/Terminal";
import ServerCharts from "@/web/components/server/console/Charts";
import React, {useEffect} from "react";
import {useServerContext} from "@/web/contexts/ServerContext";
import {useLoading} from "@/web/components/wrappers/Wrapper";
import LoadingPage from "@/web/components/commons/LoadingPage";
import CopyOnClick from "@/web/components/commons/CopyOnClick";

const formatBytes = (bytes: number = 0) => (bytes / 1024 / 1024).toFixed(2) + ' MiB';
const formatNetwork = (bytes: number = 0) => {
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB/s';
    return (bytes / 1024 / 1024).toFixed(2) + ' MB/s';
};
const formatUptime = (ms: number = 0) => {
    if (!ms) return '0d 0h 0m';
    const seconds = Math.floor((ms / 1000) % 60);
    const minutes = Math.floor((ms / (1000 * 60)) % 60);
    const hours = Math.floor((ms / (1000 * 60 * 60)));
    return `${hours}h ${minutes}m ${seconds}s`;
};

export default function ConsoleContainer() {
    // Adicionado usageWsStatus e consoleWsStatus aqui no hook
    const {
        server, usage, isLoadingServer,
        allocation, usageWsStatus, consoleWsStatus
    } = useServerContext();

    const { setLoadingBar } = useLoading();

    useEffect(() => {
        if (isLoadingServer) {
            setLoadingBar(true);
        } else {
            setLoadingBar(false);
            const interval = setInterval(() => {
                setLoadingBar(true);
                setTimeout(() => setLoadingBar(false), 1500);
            }, 20000);
            return () => clearInterval(interval);
        }
    }, [isLoadingServer, setLoadingBar]);


    if (isLoadingServer) return <div className="min-h-screen flex justify-center items-center"><LoadingPage /></div>;
    if (!server) return null;

    const serverStatus = usage?.state || 'connecting';
    const address = allocation ? `${allocation.externalIp}:${allocation.port}` : '---';

    const statusConfig = {
        running: { color: 'var(--color-success)', label: 'Online', uptime: formatUptime(usage?.uptimeMs)},
        installing: { color: 'var(--color-warning)', label: 'Instalando', uptime: '' },
        initializing: { color: 'var(--color-warning)', label: 'Iniciando', uptime: '' },
        stopping: { color: 'var(--color-warning)', label: 'Desligando', uptime: '' },
        stopped: { color: 'var(--color-danger)', label: 'Offline', uptime: '' },
        connecting: { color: 'var(--color-warning)', label: 'Conectando', uptime: '' },
    };

    const currentStatus = statusConfig[serverStatus as keyof typeof statusConfig] || statusConfig.connecting;

    // Lógica para saber qual mensagem mostrar
    const isReconnecting = usageWsStatus === 'reconnecting' || consoleWsStatus === 'reconnecting';
    const isFailed = usageWsStatus === 'failed' || consoleWsStatus === 'failed';

    return (
        <>
            {/* BARRA DE RECONEXÃO */}
            {isReconnecting && !isFailed && (
                <div className="w-full bg-(--color-danger) flex items-center justify-center gap-3 px-4 py-2 shadow-md">
                    {/* Spinner do Tailwind */}
                    <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span className="text-white text-sm font-semibold tracking-wide">
                        Estamos com dificuldades para conectar ao seu servidor. Por favor, aguarde...
                    </span>
                </div>
            )}

            {/* BARRA DE FALHA TOTAL */}
            {isFailed && (
                <div className="w-full bg-(--color-danger) flex items-center justify-center gap-3 px-4 py-2 shadow-md">
                    {/* Ícone de Erro / Alerta */}
                    <svg className="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span className="text-white text-sm font-semibold tracking-wide">
                        Falha ao conectar-se à instância do websocket após várias tentativas: tente atualizar a página.
                    </span>
                </div>
            )}

            <main className="flex-1 flex flex-col p-6 md:p-8 overflow-x-hidden">

                {/* Header Section */}
                <div className="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
                    <div className="flex flex-col gap-1">
                        {/* Título e Status lado a lado */}
                        <div className="flex flex-wrap items-center gap-6">
                            <h1 className="text-3xl md:text-4xl font-black tracking-tighter text-(--color-text-label) uppercase leading-none">{server.name}</h1>

                            {/* Status */}
                            <div className="flex items-center gap-3">
                                <div className="relative flex h-5 w-5 items-center justify-center">
                                    {serverStatus === 'running' && (
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full opacity-50" style={{ backgroundColor: currentStatus.color }}></span>
                                    )}
                                    <span className="relative inline-flex rounded-full h-3 w-3" style={{ backgroundColor: currentStatus.color, boxShadow: `0 0 15px ${currentStatus.color}` }}></span>
                                </div>
                                <span
                                    className="text-lg md:text-xl font-black uppercase tracking-[0.2em]"
                                    style={{ color: currentStatus.color, textShadow: `0 0 20px ${currentStatus.color}40` }}
                                >
                                {currentStatus.label}
                            </span>

                                {serverStatus === 'running' && (
                                    <div className="flex items-center gap-3 border-l-2 border-white/10 pl-4 ml-1">
                                    <span
                                        className="text-xs font-bold tracking-widest uppercase"
                                        style={{ color: 'var(--color-text-label)' }}
                                    >
                                        {currentStatus.uptime}
                                    </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        <CopyOnClick text={address} notify={true}>
                            <p className="text-(--color-primary) text-[15px] font-bold tracking-widest uppercase font-mono">{address}</p>
                        </CopyOnClick>
                    </div>

                    <ServerActions status={serverStatus} />
                </div>

                {/* Grid de Stats */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <StatCard
                        label="Uso de Processador"
                        value={usage ? `${usage.cpu.toFixed(2)}%` : '0.00%'}
                        subValue={server.cpu === 0 ? "ILIMITADO" : `${server.cpu}%`}
                        icon={<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M12 1v3m0 16v3M20 12h3M1 12h3" /></svg>}
                    />
                    <StatCard
                        label="Memória RAM"
                        value={formatBytes(usage?.memory)}
                        subValue={server.ram === 0 ? "ILIMITADO" : `${server.ram} MB`}
                        icon={<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5"><path d="M4 21v-7m0-4V3m8 18v-9m0-4V3m8 18v-5m0-4V3M1 14h7m2-6h6m2 8h6" /></svg>}
                    />
                    <StatCard
                        label="Armazenamento"
                        value={formatBytes(usage?.disk)}
                        subValue={server.disk === 0 ? "ILIMITADO" : `${server.disk} MB`}
                        icon={<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5"><path d="M22 12H2M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z" /></svg>}
                    />
                </div>

                {/* Console */}
                <div className="w-full h-[600px] mb-8">
                    <TerminalConsole />
                </div>

                {/* Gráficos */}
                <ServerCharts />
            </main>
        </>
    )
}
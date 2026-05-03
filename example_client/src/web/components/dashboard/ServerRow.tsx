import React from 'react';
import { ServerData } from "@/web/types";
import { Link } from "vatts/react";

interface ServerRowProps {
    server: ServerData;
    status?: string;
    stats?: {
        cpu: number;
        ram: number;
        disk: number;
    };
    allocation?: {
        ip: string;
        externalIp: string;
        port: number;
    };
}

// Configuração global de status sincronizada com o seu CSS
const STATUS_CONFIG: Record<string, { color: string; label: string; isAnimated: boolean }> = {
    running: { color: 'var(--color-success)', label: 'Online', isAnimated: true },
    initializing: { color: 'var(--color-warning)', label: 'Iniciando', isAnimated: true },
    starting: { color: 'var(--color-warning)', label: 'Iniciando', isAnimated: true },
    installing: { color: 'var(--color-warning)', label: 'Instalando', isAnimated: true },
    stopping: { color: 'var(--color-warning)', label: 'Desligando', isAnimated: true },
    stopped: { color: 'var(--color-danger)', label: 'Offline', isAnimated: false },
    offline: { color: 'var(--color-danger)', label: 'Offline', isAnimated: false },
    suspended: { color: 'var(--color-danger)', label: 'Suspenso', isAnimated: false },
    conectando: { color: 'var(--color-warning)', label: 'Conectando...', isAnimated: true },
};

const ServerRow: React.FC<ServerRowProps> = ({ server, status = 'offline', stats, allocation }) => {
    const currentStatus = STATUS_CONFIG[status.toLowerCase()] || STATUS_CONFIG.stopped; // Fallback alterado para stopped
    const isConnecting = status.toLowerCase() === 'conectando';

    const address = allocation
        ? `${allocation.externalIp !== 'localhost' ? allocation.externalIp : allocation.ip}:${allocation.port}`
        : 'Sem alocação';

    const currentCpu = stats?.cpu ?? 0;
    const currentRamBytes = stats?.ram ?? 0;
    const currentDiskBytes = stats?.disk ?? 0;

    const formatUsage = (bytes: number) => {
        const mb = bytes / 1024 / 1024;
        return mb >= 1024 ? (mb / 1024).toFixed(2) + ' GB' : mb.toFixed(1) + ' MB';
    };

    const formatLimit = (mb: number) => {
        if (mb === 0) return '∞';
        return mb >= 1024 ? (mb / 1024).toFixed(0) + ' GB' : mb + ' MB';
    };

    return (
        <Link
            href={`/server/${server.serverUuid.split('-')[0]}`}
            className="flex items-center justify-between p-6 rounded-xl transition-all duration-500 cursor-pointer backdrop-blur-md group hover:bg-white/10 shadow-(--card-shadow) hover:shadow-[0_0_30px_color-mix(in_srgb,var(--color-primary)_15%,transparent)] transform hover:-translate-y-1 bg-(--color-secondary)"
        >
            {/* Ícone, Nome, Allocation e Status */}
            <div className="flex items-center gap-6 w-2/5">
                <div className="w-16 h-16 rounded-xl flex items-center justify-center text-(--color-primary) bg-(--color-terciary) group-hover:bg-(--color-primary)/20 group-hover:text-(--color-text-label) transition-all duration-500 shadow-inner relative overflow-hidden">
                    <div className="absolute inset-0 bg-(--color-terciary) opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <svg width="26" height="26" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24" className="relative z-10">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                        <line x1="6" y1="6" x2="6.01" y2="6"></line>
                        <line x1="6" y1="18" x2="6.01" y2="18"></line>
                    </svg>
                </div>

                <div>
                    <div className="flex items-center gap-3">
                        <h3 className="text-[var(--color-text-value)] font-black text-lg tracking-tight group-hover:text-(--color-primary)/50 transition-colors truncate max-w-[200px]">
                            {server.name}
                        </h3>
                        <div className="flex items-center gap-2">
                            <div className="flex items-center justify-center w-6 h-6 rounded-full bg-black/40">
                                <span className="relative flex h-2 w-2">
                                    {currentStatus.isAnimated && (
                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style={{ backgroundColor: currentStatus.color }}></span>
                                    )}
                                    <span className="relative inline-flex rounded-full h-2 w-2" style={{ backgroundColor: currentStatus.color, boxShadow: `0 0 12px ${currentStatus.color}` }}></span>
                                </span>
                            </div>
                            <span className="text-[11px] uppercase font-bold tracking-widest" style={{ color: currentStatus.color }}>
                                {currentStatus.label}
                            </span>
                        </div>
                    </div>
                    {/* Allocation limpa, sem background e sem borda, usando a fonte mono para o IP */}
                    <div className="flex items-center gap-2 text-(--color-text-label) text-[13px] mt-1.5 font-semibold">
                        <span className="font-mono text-(--color-text-label) group-hover:text-(--color-primary) transition-colors tracking-wider">{address}</span>
                    </div>
                </div>
            </div>

            {/* Estatísticas */}
            <div className="flex items-center justify-end gap-6 flex-1 pr-2">

                {/* CPU */}
                <div className="flex flex-col items-end group-hover:-translate-y-0.5 transition-transform duration-300">
                    <div className="flex items-center gap-2 mb-1">
                        <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="text-(--color-primary) group-hover:text-(--color-text-label) transition-colors">
                            <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect>
                            <rect x="9" y="9" width="6" height="6"></rect>
                        </svg>
                        <span className="text-[10px] text-(--color-text-label) uppercase font-bold tracking-widest">CPU</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-lg font-black text-[var(--color-text-value)]">
                            {isConnecting ? <span className="animate-pulse">--</span> : `${currentCpu.toFixed(1)}%`}
                        </span>
                        <span className="text-xs text-(--color-text-label) opacity-60 font-bold">/ {server.cpu === 0 ? '∞' : `${server.cpu}%`}</span>
                    </div>
                </div>

                <div className="w-[1px] h-8 bg-gradient-to-b from-transparent via-white/10 to-transparent"></div>

                {/* RAM */}
                <div className="flex flex-col items-end group-hover:-translate-y-0.5 transition-transform duration-300 delay-75">
                    <div className="flex items-center gap-2 mb-1">
                        <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="text-(--color-primary) group-hover:text-(--color-text-label) transition-colors">
                            <line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line>
                            <line x1="20" y1="21" x2="20" y2="16"></line>
                        </svg>
                        <span className="text-[10px] text-[var(--color-text-label)] uppercase font-bold tracking-widest">RAM</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-lg font-black text-[var(--color-text-value)]">
                            {isConnecting ? <span className="animate-pulse">--</span> : formatUsage(currentRamBytes)}
                        </span>
                        <span className="text-xs text-[var(--color-text-label)] opacity-60 font-bold">/ {formatLimit(server.ram)}</span>
                    </div>
                </div>

                <div className="w-[1px] h-8 bg-gradient-to-b from-transparent via-white/10 to-transparent"></div>

                {/* DISK */}
                <div className="flex flex-col items-end group-hover:-translate-y-0.5 transition-transform duration-300 delay-150">
                    <div className="flex items-center gap-2 mb-1">
                        <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="text-(--color-primary) group-hover:text-(--color-text-label) transition-colors">
                            <path d="M22 12H2"></path>
                            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                        </svg>
                        <span className="text-[10px] text-[var(--color-text-label)] uppercase font-bold tracking-widest">SSD</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-lg font-black text-[var(--color-text-value)]">
                            {isConnecting ? <span className="animate-pulse">--</span> : formatUsage(currentDiskBytes)}
                        </span>
                        <span className="text-xs text-[var(--color-text-label)] opacity-60 font-bold">/ {formatLimit(server.disk)}</span>
                    </div>
                </div>

            </div>
        </Link>
    );
};

export default ServerRow;
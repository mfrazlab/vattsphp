import React, { useState, useEffect, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import ServerRow from './ServerRow';
import { ServerData } from '../../types';
import LoadingPage from "@/web/components/commons/LoadingPage";
import Footer from "@/web/components/commons/Footer";

interface ServerStats {
    cpu: number;
    ram: number;
    disk: number;
}

interface ServerContainerProps {
    isAdmin?: boolean;
}

const DashboardContainer: React.FC<ServerContainerProps> = ({
                                                                isAdmin = false,
                                                            }) => {
    // Inicializando states direto do localStorage para manter o cache do F5
    const [showOthers, setShowOthers] = useState(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('hight_show_others');
            return saved ? JSON.parse(saved) : false;
        }
        return false;
    });

    const [activeFilter, setActiveFilter] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('hight_active_filter') || 'all';
        }
        return 'all';
    });

    const[servers, setServers] = useState<ServerData[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [serverStatuses, setServerStatuses] = useState<Record<number, string>>({});
    const[serverStats, setServerStats] = useState<Record<number, ServerStats>>({});

    // Salvando preferências no LocalStorage sempre que mudarem
    useEffect(() => {
        localStorage.setItem('hight_show_others', JSON.stringify(showOthers));
    }, [showOthers]);

    useEffect(() => {
        localStorage.setItem('hight_active_filter', activeFilter);
    }, [activeFilter]);

    useEffect(() => {
        const fetchServers = async () => {
            setIsLoading(true);
            try {
                const endpoint = (showOthers && isAdmin)
                    ? '/api/v1/users/servers?type=others'
                    : '/api/v1/users/servers';

                const response = await fetch(endpoint);
                const data = await response.json();

                const servers = data.servers;
                setServers(Array.isArray(servers) ? servers :[]);
            } catch (error) {
                console.error("Erro ao buscar servidores:", error);
            } finally {
                setIsLoading(false);
            }
        };

        fetchServers();
    }, [showOthers, isAdmin]);

    useEffect(() => {
        if (servers.length === 0) return;

        const updateServerData = async () => {
            const newStatuses: Record<number, string> = {};
            const newStats: Record<number, ServerStats> = {};

            await Promise.all(servers.map(async (server) => {
                try {
                    const response = await fetch(`/api/v1/users/server/${server.id}/status`);

                    // Tratamento caso a API retorne um status de erro HTTP
                    if (!response.ok) {
                        throw new Error('Erro na requisição');
                    }

                    const data = await response.json();

                    const { status, usage } = data.status;

                    newStatuses[server.id] = status;
                    newStats[server.id] = {
                        cpu: usage.cpu || 0,
                        ram: usage.memory || 0,
                        disk: usage.disk || 0
                    };
                } catch (error) {
                    console.error(`Erro ao buscar status do servidor ${server.id}:`, error);
                    // Caso dê erro, define o status como 'conectando'
                    newStatuses[server.id] = 'conectando';
                    newStats[server.id] = {
                        cpu: 0,
                        ram: 0,
                        disk: 0
                    };
                }
            }));

            setServerStatuses(prev => ({ ...prev, ...newStatuses }));
            setServerStats(prev => ({ ...prev, ...newStats }));
        };

        // Puxa os dados apenas uma vez e não tenta novamente (setInterval removido)
        updateServerData();
    }, [servers]);

    // Extrair grupos únicos dos servidores
    const groups = useMemo(() => {
        const uniqueGroups = new Set(servers.map(s => s.group || 'Geral'));
        return Array.from(uniqueGroups);
    }, [servers]);

    // Filtrar e agrupar servidores
    const groupedServers = useMemo(() => {
        const filtered = servers.filter(s => activeFilter === 'all' || (s.group || 'Geral') === activeFilter);

        return filtered.reduce((acc, server) => {
            const group = server.group || 'Geral';
            if (!acc[group]) acc[group] = [];
            acc[group].push(server);
            return acc;
        }, {} as Record<string, ServerData[]>);
    }, [servers, activeFilter]);

    return (
        <div className="w-full max-w-6xl mx-auto mt-12 px-6 overflow-x-hidden">

            {/* Trocado o float-right por flex justify-end w-full para manter no fluxo da página */}
            <div className="flex justify-end mb-8 w-full">
                {isAdmin && (
                    <div
                        className="flex items-center gap-4 cursor-pointer px-5 py-2.5 rounded-full backdrop-blur-md  transition-all duration-300 group"
                        onClick={() => setShowOthers(!showOthers)}
                    >
                        <span className="text-[12px] font-bold text-(--color-text-label) tracking-widest uppercase transition-colors group-hover:text-(--color-text-sub)">
                            {showOthers ? 'Visão Global' : 'Visão Pessoal'}
                        </span>
                        <div
                            className={`w-12 h-6 rounded-full relative transition-all duration-500 ease-in-out shadow-inner`}
                            style={{ backgroundColor: showOthers ? 'var(--color-primary)' : 'var(--color-text-label)' }}
                        >
                            <div
                                className={`absolute top-1 w-4 h-4 rounded-full bg-white transition-transform duration-500 ease-in-out shadow-lg`}
                                style={{ transform: showOthers ? 'translateX(26px)' : 'translateX(4px)' }}
                            />
                        </div>
                    </div>
                )}
            </div>

            {servers.length > 0 && (
                <div className="flex gap-3 mb-8 overflow-x-auto pb-2 scrollbar-hide">
                    <button
                        onClick={() => setActiveFilter('all')}
                        className={`px-5 py-2.5 rounded-md text-sm font-bold transition-all duration-300 backdrop-blur-md shadow-(--card-shadow) cursor-pointer ${
                            activeFilter === 'all'
                                ? 'bg-(--color-primary)/20 text-white'
                                : 'bg-white/5 text-(--color-text-label)  hover:bg-white/10 hover:text-(--color-text-sub)'
                        }`}
                    >
                        Todos
                    </button>
                    {groups.map(group => (
                        <button
                            key={group}
                            onClick={() => setActiveFilter(group)}
                            className={`px-5 py-2.5 rounded-md text-sm font-bold transition-all duration-300 backdrop-blur-md shadow-(--card-shadow) cursor-pointer ${
                                activeFilter === group
                                    ? 'bg-(--color-primary)/20 text-white'
                                    : 'bg-white/5 text-(--color-text-label)  hover:bg-white/10 hover:text-(--color-text-sub)'
                            }`}
                        >
                            {group}
                        </button>
                    ))}
                </div>
            )}

            <div className="flex flex-col min-h-[400px]">
                <AnimatePresence mode="wait">
                    {isLoading ? (
                        <div key="loading" className="py-20 flex items-center justify-center">
                            <LoadingPage />
                        </div>
                    ) : Object.keys(groupedServers).length > 0 ? (
                        <motion.div
                            key="server-list"
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -10 }}
                            transition={{ duration: 0.3 }}
                            className="flex flex-col gap-10"
                        >
                            {Object.entries(groupedServers).map(([group, groupServers]) => (
                                <div key={group} className="flex flex-col gap-4">
                                    <div className="flex items-center gap-4 pl-2">
                                        <h3 className="text-lg font-black text-white/80 tracking-tight uppercase">{group}</h3>
                                        <div className="h-[1px] flex-1 bg-gradient-to-r from-white/10 to-transparent"></div>
                                    </div>
                                    <div className="grid gap-4">
                                        {groupServers.map(server => (
                                            <ServerRow
                                                key={server.id}
                                                server={server}
                                                status={serverStatuses[server.id]}
                                                stats={serverStats[server.id]}
                                                allocation={server.allocation} // <-- PASSANDO A ALLOCATION AQUI
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </motion.div>
                    ) : (
                        <motion.div
                            key="no-servers"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            className="text-center text-(--color-text-label) mt-10 py-24 rounded-md backdrop-blur-xl shadow-[var(--card-shadow)] bg-(--color-secondary)"
                        >
                            <div className="flex flex-col items-center gap-5">
                                <div className="w-20 h-20 rounded-3xl bg-(--color-terciary) flex items-center justify-center ">
                                    <svg width="40" height="40" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </div>
                                <div>
                                    <p className="text-xl font-bold text-(--color-text-label)">Nenhum servidor encontrado</p>
                                    <p className="text-sm mt-1 opacity-60">Altere os filtros ou crie uma nova instância.</p>
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
            <Footer/>
        </div>
    );
};

export default DashboardContainer;
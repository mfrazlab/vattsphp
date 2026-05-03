import React, { createContext, useContext, useEffect, useState, useRef, useCallback } from 'react';
import { ServerData } from "@/web/types";

// ==========================================
// TIPAGENS
// ==========================================

export interface AllocationData {
    id: number;
    nodeId: string;
    ip: string;
    externalIp: string;
    port: number;
    assignedTo: string;
    created_at: string;
    updated_at: string;
}

export interface UsageData {
    cpu: number;
    memory: number;
    memoryLimit: number;
    memoryPercent: number;
    networkIn: number;
    networkOut: number;
    disk: number;
    startedAt: number;
    uptimeMs: number;
    state: 'running' | 'stopped' | 'initializing' | 'stopping' | 'unknown';
}

export interface LogLine {
    id: string;
    type: string;
    prefix: string;
    category: string;
    message: string;
    timestamp: number;
    line: string;
}

export type ConnectionStatus = 'connecting' | 'connected' | 'disconnected' | 'error' | 'reconnecting' | 'failed';

export interface ServerContextData {
    server: ServerData | null;
    nodeUrl: string | null;
    nodeIp: string | null;
    sftpPort: string | null;
    allocation: AllocationData | null;
    isLoadingServer: boolean;
    serverError: string | null;
    refreshServer: () => Promise<void>;
    usage: UsageData | null;
    usageWsStatus: ConnectionStatus;
    connectUsageWs: () => void;
    disconnectUsageWs: () => void;
    logs: LogLine[];
    consoleWsStatus: ConnectionStatus;
    connectConsoleWs: () => void;
    disconnectConsoleWs: () => void;
    sendCommand: (command: string) => void;
    clearConsole: () => void;
    sendServerAction: (action: 'start' | 'stop' | 'restart' | 'kill' | 'command' | 'install', command?: string) => Promise<boolean>;
    sendApiRequest: (path: string, options?: RequestInit) => Promise<Response>;
}

const ServerContext = createContext<ServerContextData | undefined>(undefined);

interface ServerProviderProps {
    serverId: string;
    userUuid: string;
    apiUrlBase?: string;
    children: React.ReactNode;
}

export const ServerProvider: React.FC<ServerProviderProps> = ({
                                                                  serverId,
                                                                  userUuid,
                                                                  apiUrlBase = '',
                                                                  children
                                                              }) => {
    const [server, setServer] = useState<ServerData | null>(null);
    const [nodeUrl, setNodeUrl] = useState<string | null>(null);
    const [nodeIp, setNodeIp] = useState<string | null>(null);
    const [nodeSftp, setNodeSftp] = useState<string | null>(null);
    const [allocation, setAllocation] = useState<AllocationData | null>(null);
    const [isLoadingServer, setIsLoadingServer] = useState(true);
    const [serverError, setServerError] = useState<string | null>(null);

    const [usage, setUsage] = useState<UsageData | null>(null);
    const [usageWsStatus, setUsageWsStatus] = useState<ConnectionStatus>('disconnected');
    const usageWsRef = useRef<WebSocket | null>(null);
    const usageReconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const usageRetryCount = useRef(0);

    const [logs, setLogs] = useState<LogLine[]>([]);
    const [consoleWsStatus, setConsoleWsStatus] = useState<ConnectionStatus>('disconnected');
    const consoleWsRef = useRef<WebSocket | null>(null);
    const consoleReconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const consoleRetryCount = useRef(0);

    const isUsageIntentionalDisconnect = useRef(true);
    const isConsoleIntentionalDisconnect = useRef(true);
    const MAX_RETRIES = 5;

    const getWsUrl = (path: string) => {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = nodeUrl ? nodeUrl.replace(/^https?:\/\//, '') : window.location.host;
        return `${protocol}//${host}${path}`;
    };

    const fetchServerInfo = useCallback(async () => {
        setIsLoadingServer(true);
        setServerError(null);
        try {
            const response = await fetch(`${apiUrlBase}/api/v1/users/server/${serverId}`);
            if (!response.ok) throw new Error(`Erro na API: ${response.status}`);
            const data = await response.json();
            setServer(data.server);
            setNodeUrl(data.nodeUrl);
            setNodeIp(data.nodeIp);
            setNodeSftp(data.nodeSftp);
            setAllocation(data.allocation);
        } catch (err: any) {
            setServerError(err.message || 'Falha ao buscar dados do servidor');
        } finally {
            setIsLoadingServer(false);
        }
    }, [serverId, apiUrlBase]);

    useEffect(() => {
        fetchServerInfo();
    }, [fetchServerInfo]);

    const connectUsageWs = useCallback(() => {
        if (!server || (usageWsRef.current && usageWsRef.current.readyState !== WebSocket.CLOSED)) return;
        isUsageIntentionalDisconnect.current = false;
        if (usageRetryCount.current === 0) setUsageWsStatus('connecting');

        const ws = new WebSocket(getWsUrl(`/api/v1/servers/usages?serverId=${server.serverUuid}&userUuid=${userUuid}`));
        usageWsRef.current = ws;

        ws.onopen = () => {
            usageRetryCount.current = 0;
            setUsageWsStatus('connected');
            if (usageReconnectTimeoutRef.current) clearTimeout(usageReconnectTimeoutRef.current);
        };
        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'usage') setUsage(data.usage);
            } catch (e) { }
        };
        ws.onclose = () => {
            if (!isUsageIntentionalDisconnect.current) {
                if (usageRetryCount.current < MAX_RETRIES) {
                    usageRetryCount.current += 1;
                    setUsageWsStatus('reconnecting');
                    usageReconnectTimeoutRef.current = setTimeout(connectUsageWs, 5000);
                } else {
                    setUsageWsStatus('failed');
                }
            } else {
                setUsageWsStatus('disconnected');
            }
        };
    }, [server, userUuid, nodeUrl]);

    const disconnectUsageWs = useCallback(() => {
        isUsageIntentionalDisconnect.current = true;
        if (usageReconnectTimeoutRef.current) clearTimeout(usageReconnectTimeoutRef.current);
        if (usageWsRef.current) {
            usageWsRef.current.close();
            usageWsRef.current = null;
        }
        setUsageWsStatus('disconnected');
    }, []);

    const connectConsoleWs = useCallback(() => {
        if (!server || (consoleWsRef.current && consoleWsRef.current.readyState !== WebSocket.CLOSED)) return;
        isConsoleIntentionalDisconnect.current = false;
        if (consoleRetryCount.current === 0) setConsoleWsStatus('connecting');

        const ws = new WebSocket(getWsUrl(`/api/v1/servers/console?serverId=${server.serverUuid}&userUuid=${userUuid}`));
        consoleWsRef.current = ws;

        ws.onopen = () => {
            consoleRetryCount.current = 0;
            setLogs([]); // Limpa o estado para receber o histórico novo do Wings
            setConsoleWsStatus('connected');
            if (consoleReconnectTimeoutRef.current) clearTimeout(consoleReconnectTimeoutRef.current);
        };

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'line') {
                    const lineText = data.line || data.message;
                    setLogs((prev) => {
                        // Anti-duplicação robusta: verifica se a linha já existe no final do buffer
                        // para evitar duplicatas causadas por reconexões rápidas ou múltiplos listeners
                        if (prev.length > 0 && prev[prev.length - 1].line === lineText && prev[prev.length - 1].timestamp === data.timestamp) {
                            return prev;
                        }
                        return [...prev, { ...data, line: lineText, id: data.id || crypto.randomUUID() }].slice(-1000);
                    });
                } else if (data.type === 'clear') {
                    setLogs([]);
                }
            } catch (e) { }
        };

        ws.onclose = () => {
            if (!isConsoleIntentionalDisconnect.current) {
                if (consoleRetryCount.current < MAX_RETRIES) {
                    consoleRetryCount.current += 1;
                    setConsoleWsStatus('reconnecting');
                    consoleReconnectTimeoutRef.current = setTimeout(connectConsoleWs, 5000);
                } else {
                    setConsoleWsStatus('failed');
                }
            } else {
                setConsoleWsStatus('disconnected');
            }
        };
    }, [server, userUuid, nodeUrl]);

    const disconnectConsoleWs = useCallback(() => {
        isConsoleIntentionalDisconnect.current = true;
        if (consoleReconnectTimeoutRef.current) clearTimeout(consoleReconnectTimeoutRef.current);
        if (consoleWsRef.current) {
            consoleWsRef.current.close();
            consoleWsRef.current = null;
        }
        setConsoleWsStatus('disconnected');
    }, []);

    const sendCommand = useCallback((command: string) => {
        if (consoleWsRef.current?.readyState === WebSocket.OPEN) {
            consoleWsRef.current.send(JSON.stringify({ type: 'command', command }));
        }
    }, []);

    const clearConsole = useCallback(() => setLogs([]), []);

    const sendServerAction = useCallback(async (action: string, command?: string) => {
        try {
            const bodyData = command ? { action, command } : { action };
            const response = await fetch(`${apiUrlBase}/api/v1/users/server/${serverId}/action`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bodyData),
            });
            return response.ok;
        } catch (err) {
            return false;
        }
    }, [apiUrlBase, serverId]);

    const sendApiRequest = useCallback(async (path: string, options?: RequestInit) => {
        const cleanPath = path.startsWith('/') ? path.slice(1) : path;
        return fetch(`${apiUrlBase}/api/v1/users/server/${serverId}/${cleanPath}`, options);
    }, [apiUrlBase, serverId]);

    return (
        <ServerContext.Provider
            value={{
                nodeIp,
                sftpPort: nodeSftp,
                server,
                nodeUrl,
                allocation,
                isLoadingServer,
                serverError,
                refreshServer: fetchServerInfo,
                usage,
                usageWsStatus,
                connectUsageWs,
                disconnectUsageWs,
                logs,
                consoleWsStatus,
                connectConsoleWs,
                disconnectConsoleWs,
                sendCommand,
                clearConsole,
                sendServerAction: sendServerAction as any,
                sendApiRequest,
            }}
        >
            {children}
        </ServerContext.Provider>
    );
};

export const useServerContext = () => {
    const context = useContext(ServerContext);
    if (!context) throw new Error('useServerContext deve ser usado dentro de um ServerProvider');
    return context;
};
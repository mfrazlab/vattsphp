import React, { useLayoutEffect, useRef, useState } from 'react';
import Ansi from 'ansi-to-react';
import { useServerContext } from '@/web/contexts/ServerContext';
import LoadingPage from "@/web/components/commons/LoadingPage";
import { AnimatePresence, motion } from "framer-motion";

export default function Terminal() {
    const scrollRef = useRef<HTMLDivElement>(null);
    const { logs, sendCommand, consoleWsStatus } = useServerContext();
    const [commandInput, setCommandInput] = useState('');
    const shouldScrollRef = useRef(true);

    // LayoutEffect para garantir o scroll automático quando os logs mudarem
    useLayoutEffect(() => {
        if (!scrollRef.current || !shouldScrollRef.current) return;
        scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }, [logs]);

    const handleScroll = () => {
        if (!scrollRef.current) return;
        const { scrollTop, scrollHeight, clientHeight } = scrollRef.current;
        // Detecta se o usuário subiu o scroll manualmente
        shouldScrollRef.current = scrollHeight - scrollTop - clientHeight < 60;
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' && commandInput.trim()) {
            sendCommand(commandInput);
            setCommandInput('');
            shouldScrollRef.current = true;
            // Força o scroll imediato após enviar comando
            setTimeout(() => {
                if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
            }, 10);
        }
    };

    const showSpinner = consoleWsStatus === 'connecting' || consoleWsStatus === 'reconnecting' || (logs.length === 0 && consoleWsStatus === 'connected');

    return (
        <div className="flex flex-col h-full w-full rounded-xl overflow-hidden backdrop-blur-md bg-(--color-console) transition-all duration-300">
            {/* Área de Logs */}
            <div
                ref={scrollRef}
                onScroll={handleScroll}
                className="terminal-font flex-1 p-5 text-[13px] overflow-y-auto custom-scrollbar selection:bg-(--color-primary)/30 bg-(--color-console) min-h-0 antialiased"
            >
                <AnimatePresence>
                    {showSpinner && (
                        <motion.div
                            key="loading-terminal"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="absolute inset-0 flex flex-col items-center justify-center bg-[#050508]/95 z-10"
                        >
                            <LoadingPage />
                        </motion.div>
                    )}
                </AnimatePresence>

                <div className="flex flex-col">
                    {logs.map((log) => (
                        <div key={log.id} className="leading-relaxed break-all whitespace-pre-wrap text-gray-300 mb-[1px]">
                            <Ansi>{log.line || log.message}</Ansi>
                        </div>
                    ))}
                </div>
            </div>

            {/* Input de Comandos */}
            <div className="flex items-center gap-3 px-5 py-4 bg-(--color-console-command) group focus-within:border-(--color-primary)/30">
                <span className="text-(--color-primary) group-focus-within:text-(--color-secondary) transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                        <polyline points="13 17 18 12 13 7" /><polyline points="6 17 11 12 6 7" />
                    </svg>
                </span>
                <input
                    type="text"
                    value={commandInput}
                    onChange={(e) => setCommandInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    disabled={consoleWsStatus !== 'connected'}
                    placeholder={consoleWsStatus === 'connected' ? "Digite um comando..." : "Console desconectado."}
                    className="terminal-font flex-1 bg-transparent border-none outline-none text-(--color-text-label) text-[14px] placeholder:text-(--color-text-sub)"
                />
            </div>
        </div>
    );
};
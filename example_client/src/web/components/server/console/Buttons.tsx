import React, { useState, useEffect } from 'react';
import { useServerContext } from '../../../contexts/ServerContext';

interface ServerActionsProps { status?: string; }

const Buttons: React.FC<ServerActionsProps> = ({ status }) => {
    const { sendServerAction } = useServerContext();
    const [pendingAction, setPendingAction] = useState<string | null>(null);

    useEffect(() => { setPendingAction(null); }, [status]);

    const handleAction = (action: string) => {
        setPendingAction(action);
        // @ts-ignore
        sendServerAction(action);
    };

    const isPending = pendingAction !== null;
    const isStartDisabled = isPending || status !== 'stopped';
    const isRestartDisabled = isPending || status === 'stopped';
    const isStopDisabled = isPending || status === 'stopped' || status === 'stopping';
    const isKillDisabled = status === 'stopped';

    return (
        /* Container solto, botões grandes e independentes */
        <div className="flex items-center gap-4">

            {/* Start */}
            <button
                title="Iniciar"
                disabled={isStartDisabled}
                onClick={() => handleAction('start')}
                className={`group relative flex items-center justify-center h-14 w-16 rounded-2xl transition-all duration-300 overflow-hidden ${
                    isStartDisabled
                        ? 'bg-(--color-secondary)/40  opacity-50 cursor-not-allowed shadow-none'
                        : 'bg-(--color-secondary)  shadow-(--card-shadow)  hover:-translate-y-1 active:translate-y-0 active:scale-95 cursor-pointer'
                }`}
                style={{ color: 'var(--color-success)' }}
            >
                {!isStartDisabled && <div className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity" style={{ backgroundColor: 'var(--color-success)' }} />}
                <svg className="relative z-10 drop-shadow-md" width="26" height="26" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
            </button>

            {/* Stop */}
            <button
                title="Desligar"
                disabled={isStopDisabled}
                onClick={() => handleAction('stop')}
                className={`group relative flex items-center justify-center h-14 w-16 rounded-2xl  transition-all duration-300 overflow-hidden ${
                    isStopDisabled
                        ? 'bg-(--color-secondary)/40 -transparent opacity-50 cursor-not-allowed shadow-none'
                        : 'bg-(--color-secondary)  shadow-(--card-shadow)  hover:-translate-y-1 active:translate-y-0 active:scale-95 cursor-pointer'
                }`}
                style={{ color: 'var(--color-danger)' }}
            >
                {!isStopDisabled && <div className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity" style={{ backgroundColor: 'var(--color-danger)' }} />}
                <svg className="relative z-10 drop-shadow-md" width="26" height="26" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M18.36 6.64a9 9 0 1 1-12.73 0M12 2v10" /></svg>
            </button>

            {/* Restart */}
            <button
                title="Reiniciar"
                disabled={isRestartDisabled}
                onClick={() => handleAction('restart')}
                className={`group relative flex items-center justify-center h-14 w-16 rounded-2xl  transition-all duration-300 overflow-hidden ${
                    isRestartDisabled
                        ? 'bg-(--color-secondary)/40  opacity-50 cursor-not-allowed shadow-none'
                        : 'bg-(--color-secondary)  shadow-[var(--card-shadow)]  hover:-translate-y-1 active:translate-y-0 active:scale-95 cursor-pointer'
                }`}
                style={{ color: 'var(--color-info)' }}
            >
                {!isRestartDisabled && <div className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity" style={{ backgroundColor: 'var(--color-info)' }} />}
                <svg className="relative z-10 drop-shadow-md" width="26" height="26" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            </button>

            {/* Separador */}
            <div className="w-[2px] h-10 bg-white/5 mx-1 rounded-full" />

            {/* Kill (Caveira) */}
            <button
                title="Matar Processo"
                disabled={isKillDisabled}
                onClick={() => handleAction('kill')}
                className={`group relative flex items-center justify-center h-14 w-16 rounded-2xl  transition-all duration-300 overflow-hidden ${
                    isKillDisabled
                        ? 'bg-(--color-secondary)/40 -transparent opacity-50 cursor-not-allowed shadow-none'
                        : 'bg-(--color-secondary)  shadow-(--card-shadow)  hover:-translate-y-1 active:translate-y-0 active:scale-95 cursor-pointer'
                }`}
                style={{ color: 'var(--color-warning)' }}
            >
                {!isKillDisabled && <div className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity" style={{ backgroundColor: 'var(--color-warning)' }} />}
                <svg className="relative z-10 drop-shadow-md" width="26" height="26" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24">
                    <circle cx="9" cy="12" r="1" />
                    <circle cx="15" cy="12" r="1" />
                    <path d="M8 20v2h8v-2" />
                    <path d="m12.5 17-.5-1-.5 1h1z" />
                    <path d="M16 20a2 2 0 0 0 1.56-3.25 8 8 0 1 0-11.12 0A2 2 0 0 0 8 20" />
                </svg>
            </button>
        </div>
    );
};

export default Buttons;
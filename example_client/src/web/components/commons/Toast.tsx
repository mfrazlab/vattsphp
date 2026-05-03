import React, { useState, useEffect } from 'react';

interface ToastProps {
    toast: {
        id: string;
        message: string;
        type: 'success' | 'error';
    };
    removeToast: (id: string) => void;
}

const Toast: React.FC<ToastProps> = ({ toast, removeToast }) => {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        // Timer para iniciar a animação de "subida"
        const showTimer = setTimeout(() => setIsVisible(true), 10);

        // Timer para fechar automaticamente após 3 segundos
        const hideTimer = setTimeout(() => {
            setIsVisible(false);
            setTimeout(() => removeToast(toast.id), 300);
        }, 3000);

        return () => {
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
        };
    }, [toast, removeToast]);

    return (
        <div
            className={`
        transform transition-all duration-300 ease-in-out
        ${isVisible ? 'translate-y-0 opacity-100 scale-100' : 'translate-y-8 opacity-0 scale-95'}
        flex items-center gap-3 px-4 py-3 rounded-md shadow-2xl min-w-[300px] pointer-events-auto
      `}
            style={{
                backgroundColor: '#111118',
                borderLeft: `4px solid ${toast.type === 'error' ? '#ef4444' : '#9D56FF'}`,
                color: '#ffffff'
            }}
        >
            {/* Ícone customizado baseado no tipo */}
            {toast.type === 'error' ? (
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)" className="text-red-500">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2"/>
                    <path d="M12 8V12" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                </svg>
            ) : (
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)" className="text-[#9D56FF]">
                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M9 12L11 14L15 10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
            )}
            <span className="text-[13px] font-medium tracking-wide flex-1">{toast.message}</span>

            {/* Botão de fechar */}
            <button
                onClick={() => setIsVisible(false)}
                className="text-gray-400 hover:text-white transition-colors"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    );
};

export default Toast;
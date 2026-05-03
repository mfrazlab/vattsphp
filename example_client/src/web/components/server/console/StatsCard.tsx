import React from 'react';

interface StatCardProps {
    label: string;
    value: string;
    subValue?: string;
    icon: React.ReactNode;
    accentColor?: string;
    labelColor?: string;
    valueColor?: string;
    subValueColor?: string;
}

const StatsCard: React.FC<StatCardProps> = ({
                                                label,
                                                value,
                                                subValue,
                                                icon,
                                                accentColor = 'var(--color-primary)', // Usa a cor do CSS por padrão
                                                labelColor = 'var(--color-text-label)',
                                                valueColor = 'var(--color-text-value)',
                                                subValueColor = 'var(--color-text-sub)'
                                            }) => {
    return (
        /* Removido border, adicionado shadow customizada */
        <div className="group flex items-center gap-4 p-4 rounded-xl backdrop-blur-xl bg-(--color-secondary) transition-all duration-300 shadow-[var(--card-shadow)]">

            {/* Box do Ícone: Removido border e aplicado accentColor dinâmico */}
            <div
                className="w-12 h-12 rounded-lg flex items-center justify-center bg-(--color-terciary) transition-colors flex-shrink-0"
                style={{ color: accentColor }}
            >
                <div className="scale-110 opacity-80 group-hover:opacity-100 transition-opacity">
                    {icon}
                </div>
            </div>

            {/* Informações */}
            <div className="flex flex-col flex-1 min-w-0">
                <span
                    className="text-[10px] font-bold uppercase tracking-widest mb-0.5"
                    style={{ color: labelColor }}
                >
                    {label}
                </span>

                <div className="flex items-baseline gap-1.5">
                    <span
                        className="text-2xl font-bold tracking-tighter leading-none"
                        style={{ color: valueColor }}
                    >
                        {value}
                    </span>

                    {subValue && (
                        <span
                            className="text-xs font-medium tracking-tight"
                            style={{ color: subValueColor }}
                        >
                            <span className="opacity-30 mr-1">/</span>
                            {subValue}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
};

export default StatsCard;
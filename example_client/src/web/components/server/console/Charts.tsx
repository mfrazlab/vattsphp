import React, { useEffect, useState } from 'react';
import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer
} from 'recharts';
import { useServerContext } from '../../../contexts/ServerContext';

const formatBytes = (bytes: number, decimals = 2) => {
    if (!+bytes) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
};

interface ChartDataPoint {
    time: string;
    memory: number;
    networkIn: number;
    networkOut: number;
}

const CustomTooltip = ({ active, payload, label, formatter }: any) => {
    if (active && payload && payload.length) {
        return (
            /* Tooltip atualizado para combinar com o novo padrão */
            <div className="bg-(--color-background) backdrop-blur-xl p-3 rounded-xl shadow-[var(--card-shadow)]">
                <p
                    className="text-[10px] font-bold uppercase tracking-widest mb-1"
                    style={{ color: 'var(--color-text-label)' }}
                >
                    {label}
                </p>
                <p
                    className="text-sm font-bold tracking-tight"
                    style={{ color: 'var(--color-text-value)' }}
                >
                    {formatter ? formatter(payload[0].value) : payload[0].value}
                </p>
            </div>
        );
    }
    return null;
};

const Charts: React.FC = () => {
    const { usage } = useServerContext();
    const [dataHistory, setDataHistory] = useState<ChartDataPoint[]>([]);

    useEffect(() => {
        if (usage && usage.state !== 'stopped') {
            const now = new Date();
            const timeString = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;

            setDataHistory((prev) => {
                const newPoint: ChartDataPoint = {
                    time: timeString,
                    memory: usage.memory || 0,
                    networkIn: usage.networkIn || 0,
                    networkOut: usage.networkOut || 0,
                };
                const next = [...prev, newPoint];
                return next.length > 20 ? next.slice(next.length - 20) : next;
            });
        }
    }, [usage]);

    const chartConfigs = [
        {
            id: 'memory',
            label: 'Memória RAM',
            value: formatBytes(usage?.memory || 0),
            color: 'var(--color-primary)', /* Modificado para usar a variável do CSS */
            dataKey: 'memory',
            formatter: (val: number) => formatBytes(val),
            icon: <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5"><path d="M4 21v-7m0-4V3m8 18v-9m0-4V3m8 18v-5m0-4V3M1 14h7m2-6h6m2 8h6" /></svg>
        },
        {
            id: 'networkIn',
            label: 'Rede (Entrada)',
            value: `${formatBytes(usage?.networkIn || 0)}/s`,
            color: 'var(--color-success)', /* Modificado para usar a variável do CSS */
            dataKey: 'networkIn',
            formatter: (val: number) => `${formatBytes(val)}/s`,
            icon: <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5"><path d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
        },
        {
            id: 'networkOut',
            label: 'Rede (Saída)',
            value: `${formatBytes(usage?.networkOut || 0)}/s`,
            color: 'var(--color-info)', /* Modificado para usar a variável do CSS */
            dataKey: 'networkOut',
            formatter: (val: number) => `${formatBytes(val)}/s`,
            icon: <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.5"><path d="M11 17l-5-5m0 0l5-5m-5 5h12" /></svg>
        },
    ];

    return (
        /* Retirei o mt-8 pq no ServerContainer já tá englobado com mt-10 */
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {chartConfigs.map((chart) => (
                /* Card do gráfico atualizado com shadow e background sem borda */
                <div
                    key={chart.id}
                    className="group flex flex-col p-5 rounded-xl backdrop-blur-xl bg-(--color-secondary) transition-all duration-300 shadow-[var(--card-shadow)]"
                >
                    {/* Header do Gráfico copiando o estilo visual do StatCard */}
                    <div className="flex items-center gap-5 mb-6">
                        {/* Box do Ícone sem borda */}
                        <div
                            className="w-12 h-12 rounded-lg flex items-center justify-center bg-(--color-terciary) transition-colors shrink-0"
                            style={{ color: chart.color }}
                        >
                            <div className="scale-110 opacity-80 group-hover:opacity-100 transition-opacity">
                                {chart.icon}
                            </div>
                        </div>

                        <div className="flex flex-col flex-1 min-w-0">
                            <span
                                className="text-[11px] font-bold uppercase tracking-widest mb-1"
                                style={{ color: 'var(--color-text-label)' }}
                            >
                                {chart.label}
                            </span>
                            <span
                                className="text-xl font-bold tracking-tight leading-none"
                                style={{ color: 'var(--color-text-value)' }}
                            >
                                {chart.value}
                            </span>
                        </div>
                    </div>

                    {/* Gráfico */}
                    <div className="h-40 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={dataHistory} margin={{ top: 0, right: 0, left: -25, bottom: 0 }}>
                                <defs>
                                    <linearGradient id={`color${chart.id}`} x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor={chart.color} stopOpacity={0.3}/>
                                        <stop offset="95%" stopColor={chart.color} stopOpacity={0}/>
                                    </linearGradient>
                                </defs>
                                {/* Linhas de grade mais sutis */}
                                <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.03)" vertical={false} />
                                <XAxis
                                    dataKey="time"
                                    tick={false}
                                    axisLine={false}
                                    tickLine={false}
                                />
                                <YAxis
                                    stroke="rgba(255,255,255,0.2)"
                                    fontSize={10}
                                    tickLine={false}
                                    axisLine={false}
                                    tickCount={3}
                                    tickFormatter={(val: any) => chart.formatter(val)}
                                />
                                <Tooltip content={<CustomTooltip formatter={chart.formatter} />} cursor={{ stroke: 'rgba(255,255,255,0.1)', strokeWidth: 1 }} />
                                <Area
                                    type="monotone"
                                    dataKey={chart.dataKey}
                                    stroke={chart.color}
                                    strokeWidth={2}
                                    fillOpacity={1}
                                    fill={`url(#color${chart.id})`}
                                    isAnimationActive={false}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            ))}
        </div>
    );
};

export default Charts;
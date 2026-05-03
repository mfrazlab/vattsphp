import React, { useId } from "react";
import { motion } from "framer-motion";

type CheckboxProps = {
    label?: string | null;
    desc?: string | null;
    checked: boolean;
    onChange: (checked: boolean) => void;
    className?: string;
};

export default function Checkbox({ label, desc, checked, onChange, className }: CheckboxProps) {
    const id = useId(); // Mantido caso necessário futuramente

    return (
        <div
            className={`flex items-center gap-4 cursor-pointer rounded-full backdrop-blur-md transition-all duration-300 group w-fit ${className || ""}`}
            onClick={() => onChange(!checked)}
        >
            {/* O Switch (Toggle) - AGORA NA ESQUERDA */}
            <motion.div
                className="w-12 h-6 rounded-full relative shadow-inner flex-shrink-0"
                animate={{
                    backgroundColor: checked ? 'var(--color-primary)' : 'var(--color-text-label)'
                }}
                transition={{ duration: 0.3, ease: "easeInOut" }}
            >
                {/* Bolinha Branca (Thumb) */}
                <motion.div
                    className="absolute top-1 w-4 h-4 rounded-full bg-white shadow-lg"
                    animate={{
                        x: checked ? 26 : 4
                    }}
                    transition={{ duration: 0.3, ease: "easeInOut" }}
                />
            </motion.div>

            {/* Labels - AGORA NA DIREITA */}
            {(label || desc) && (
                <div className="flex flex-col items-start select-none">
                    {label && (
                        <span className="text-[15px] font-light text-(--color-text-sub) tracking-widest uppercase transition-colors group-hover:text-(--color-text-sub)">
                            {label}
                        </span>
                    )}
                    {desc && (
                        <span className={`text-(--color-text-sub) text-[15px] font-light ${label ? 'mt-0.5' : ''}`}>
                            {desc}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
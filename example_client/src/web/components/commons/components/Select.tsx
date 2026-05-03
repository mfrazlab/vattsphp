import React, { useState, useId, useRef, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { ChevronDown } from "lucide-react";

type Option = {
    label: string;
    value: string | number;
};

type SelectProps = {
    label?: string | null;
    desc?: string | null;
    options: Option[];
    value?: string | number;
    onChange: (value: any) => void;
    placeholder?: string;
    className?: string;
};

export default function Select({ label, desc, options, value, onChange, placeholder = "Selecione...", className }: SelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const id = useId();
    const containerRef = useRef<HTMLDivElement>(null);

    // Fecha ao clicar fora
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const selectedOption = options.find(opt => opt.value === value);

    return (
        <div className="flex flex-col gap-1.5 w-full relative" ref={containerRef}>
            {label && (
                <label className="font-medium text-(--color-text-label) text-[12px] tracking-widest ml-1">
                    {label}
                </label>
            )}

            {/* Trigger (O "Botão" do Select) */}
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={`
                    w-full bg-(--color-terciary) text-(--color-text-label) 
                    p-4 rounded-xl border-none outline-none flex items-center justify-between
                    focus:ring-2 focus:ring-(--color-primary) transition-all duration-200
                    cursor-pointer hover:bg-(--color-terciary)/80
                    ${className || ""}
                `}
            >
                <span className={!selectedOption ? "text-(--color-text-sub)" : ""}>
                    {selectedOption ? selectedOption.label : placeholder}
                </span>
                <motion.div
                    animate={{ rotate: isOpen ? 180 : 0 }}
                    transition={{ duration: 0.2 }}
                >
                    <ChevronDown size={18} className="text-(--color-text-sub)" />
                </motion.div>
            </button>

            {/* Dropdown Menu */}
            <AnimatePresence>
                {isOpen && (
                    <motion.ul
                        initial={{ opacity: 0, y: 0, scale: 0.95 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 0, scale: 0.95 }}
                        transition={{ duration: 0.15, ease: "easeOut" }}
                        className="absolute top-full left-0 w-full bg-(--color-terciary) rounded-xl overflow-hidden z-50 shadow-2xl p-1.5"
                    >
                        {options.map((option) => (
                            <li key={option.value}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        onChange(option.value);
                                        setIsOpen(false);
                                    }}
                                    className={`
                                    cursor-pointer
                                        w-full text-left p-3 rounded-lg text-[14px] transition-colors
                                        ${value === option.value
                                        ? "bg-(--color-secondary) text-(--color-text-sub)"
                                        : "text-(--color-text-label) hover:bg-(--color-secondary)/80"}
                                    `}
                                >
                                    {option.label}
                                </button>
                            </li>
                        ))}
                    </motion.ul>
                )}
            </AnimatePresence>

            {desc && (
                <span className="text-(--color-text-sub) text-[15px] ml-1 font-light">
                    {desc}
                </span>
            )}
        </div>
    );
}
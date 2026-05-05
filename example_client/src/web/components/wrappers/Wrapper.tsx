import React, { useState, useEffect, ReactNode, createContext, useContext } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import Navbar from '../commons/Navbar';
import LoadingPage from "@/web/components/commons/LoadingPage";
import { useSession } from "@vatts/auth/react";
import Footer from "@/web/components/commons/Footer";

interface LoadingContextType {
    setLoadingBar: (state: boolean) => void;
}

const LoadingContext = createContext<LoadingContextType | undefined>(undefined);

export const useLoading = () => {
    const context = useContext(LoadingContext);
    if (!context) throw new Error("useLoading deve ser usado dentro de um LoadingProvider");
    return context;
};

export default function DashboardWrapper({ children }: { children: ReactNode }) {
    const session = useSession();
    const [isBarActive, setIsBarActive] = useState(false);
    const [isChangingPage, setIsChangingPage] = useState(false);
    const [displayChildren, setDisplayChildren] = useState<ReactNode>(children);

    useEffect(() => {
        setIsChangingPage(true);
        setIsBarActive(true);

        const timer = setTimeout(() => {
            setDisplayChildren(children);
            setIsChangingPage(false);
            setTimeout(() => setIsBarActive(false), 200);
        }, 150);

        return () => clearTimeout(timer);
    }, [children]);

    if (session.data === null) return <>{children}</>;

    return (
        <LoadingContext.Provider value={{ setLoadingBar: setIsBarActive }}>
            {/* 1. Injetando no body para matar o scroll horizontal sem quebrar o sticky */}
            <style dangerouslySetInnerHTML={{ __html: `
                body { overflow-x: hidden !important; }
                .custom-scrollbar::-webkit-scrollbar { width: 6px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
            `}} />

            <div className="relative min-h-screen text-white bg-[var(--color-background)] flex flex-col">
                <Navbar />

                <main className="relative flex flex-1 flex-col">
                    {/* 2. Barra agora é FIXED e o container corta o brilho que vaza */}
                    <div className="fixed top-0 left-0 right-0 z-[9999] pointer-events-none overflow-hidden h-2">
                        <AnimatePresence>
                            {isBarActive && (
                                <motion.div
                                    initial={{ width: 0, opacity: 0 }}
                                    animate={{ width: "100%", opacity: 1 }}
                                    exit={{ opacity: 0 }}
                                    transition={{
                                        width: { duration: 0.8, ease: "easeOut" },
                                        opacity: { duration: 0.2 }
                                    }}
                                    className="h-[2px] bg-[var(--color-primary)] shadow-[0_0_20px_var(--color-primary)]"
                                />
                            )}
                        </AnimatePresence>
                    </div>

                    <AnimatePresence mode="wait">
                        {isChangingPage ? (
                            <motion.div
                                key="loading-screen"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                className="flex-1 flex items-center justify-center"
                            >
                                <LoadingPage />
                            </motion.div>
                        ) : (
                            <motion.div
                                key="page-content"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                className="flex-1 flex flex-col"
                            >
                                {displayChildren}
                            </motion.div>
                        )}
                    </AnimatePresence>
                </main>
            </div>
        </LoadingContext.Provider>
    );
}
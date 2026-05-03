import React, { useState, useEffect } from 'react';
import ServerSidebar from './ServerSidebar';
import ConsoleContainer from "./console/ConsoleContainer";
import { useServerContext } from "@/web/contexts/ServerContext";
import { router } from "vatts/react";
import Footer from "@/web/components/commons/Footer";
import ConfigurationContainer from "@/web/components/server/config/ConfigurationContainer";
import { useLoading } from "@/web/components/wrappers/Wrapper";
import { motion, AnimatePresence } from "framer-motion";
import StartupContainer from "@/web/components/server/startup/StartupContainer";
import FileManagerContainer from "@/web/components/server/filemanager/FileManagerContainer";

type ServerProps = {
    action?: string;
}

export default function ServerContainer({ action }: ServerProps) {
    const context = useServerContext();
    const {
        server,
        isLoadingServer,
        connectUsageWs,
        disconnectUsageWs,
        connectConsoleWs,
        disconnectConsoleWs
    } = context;

    const serverId = server?.serverUuid.split('-')[0] ?? "";
    const pathname = router.pathname;
    const { setLoadingBar } = useLoading();

    const [currentAction, setCurrentAction] = useState(action?.split("/")[0] || 'console');

    // Sincroniza a URL com o estado interno
    useEffect(() => {
        const pathSegments = pathname.split('/').filter(Boolean);
        const actionFromUrl = pathSegments[2] || 'console';

        if (actionFromUrl !== currentAction) {
            setCurrentAction(actionFromUrl);
        }
    }, [pathname]);

    // LÓGICA CENTRAL DE CONEXÃO
    useEffect(() => {
        if (!isLoadingServer && server) {
            connectUsageWs();
            connectConsoleWs();
        }
        return () => {
            disconnectUsageWs();
            disconnectConsoleWs();
        };
    }, [isLoadingServer, server, connectUsageWs, connectConsoleWs, disconnectUsageWs, disconnectConsoleWs]);

    const changeAction = (newAction: string) => {
        if (newAction === currentAction) return;

        setLoadingBar(true);
        setCurrentAction(newAction);

        const url = newAction === 'console'
            ? `/server/${serverId}`
            : `/server/${serverId}/${newAction}`;

        window.history.pushState(null, '', url);

        setTimeout(() => setLoadingBar(false), 300);
    };

    const renderContent = () => {
        switch (currentAction) {
            case 'console':
                return <ConsoleContainer />;
            case 'files':
                return <FileManagerContainer action={action}/>;
            case 'database':
                return <div>Página de Database</div>;
            case 'settings':
                return <ConfigurationContainer />;
            case 'startup':
                return <StartupContainer/>
            default:
                return <ConsoleContainer />;
        }
    };

    return (
    <div className="flex-1 flex flex-row items-start relative">
        <ServerSidebar
            serverId={serverId}
            activeTab={currentAction}
            changeAction={changeAction}
        />

        <div className="flex-1 flex flex-col min-h-screen">
            <main className="flex-1 overflow-hidden">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={currentAction}
                        initial={{ opacity: 0, x: 5 }}
                        animate={{ opacity: 1, x: 0 }}
                        exit={{ opacity: 0, x: -5 }}
                        transition={{ duration: 0.15, ease: "easeOut" }}
                        className="h-full"
                    >
                        {renderContent()}
                    </motion.div>
                </AnimatePresence>
            </main>

            <Footer />
        </div>
    </div>
    );
};
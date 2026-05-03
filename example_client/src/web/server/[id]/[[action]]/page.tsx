import React from 'react';
import ServerContainer from "../../../components/server/ServerContainer";
import {AuthGuard, useSession} from "@vatts/auth/react";
import {ServerProvider} from "@/web/contexts/ServerContext";

type WelcomeProps = {
    params: {
        id: string;
        action: string;
    }
}

export default function ServerPage({params}: WelcomeProps) {
    const id = params.id;
    const session = useSession()
    return (
        <AuthGuard redirectTo={"/auth"}>
            <ServerProvider serverId={id} userUuid={session.data?.user.id}>
                <ServerContainer action={params.action}></ServerContainer>
            </ServerProvider>
        </AuthGuard>
    );
}

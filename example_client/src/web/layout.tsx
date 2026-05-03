import React from 'react';
import {Metadata} from "vatts/react"
import './globals.css';
import {SessionProvider, useSession} from "@vatts/auth/react";
import {ToastProvider} from "@/web/contexts/ToastContext";
import DashboardWrapper from "@/web/components/wrappers/Wrapper";

interface LayoutProps {
    children: React.ReactNode;
}

export const metadata: Metadata = {
    title: "Vatts JS | The Fast and Simple Web Framework for React",
    description: "The fastest and simplest web framework for React! Start building high-performance web applications today with Vatts JS.",
    keywords: ["Vatts JS", "web framework", "React", "JavaScript", "TypeScript", "web development", "fast", "simple", "SSR", "frontend"],
    author: "Vatts JS Team",
};

export default function Layout({ children }: LayoutProps) {

    return (
        <ToastProvider>
            <SessionProvider>
                <DashboardWrapper>
                    {children}
                </DashboardWrapper>
            </SessionProvider>
        </ToastProvider>
    );
}

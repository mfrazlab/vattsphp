import React from 'react';
import { useSession } from "@vatts/auth/react";
import { Link } from "vatts/react";

const Navbar: React.FC = () => {
    const session = useSession();

    return (
        /* Adicionado 'sticky' aqui */
        <nav className="w-full h-16 bg-(--color-navbar) backdrop-blur-xl sticky top-0 z-[100]">

            <div className="w-full h-full flex items-center justify-between px-8">

                {/* Logo - Travada na esquerda */}
                <Link href={"/"} className="flex items-center gap-2 cursor-pointer text-[25px]">
                    <span className="text-(--color-text-value) font-bold tracking-tighter">
                        Hight Cloud
                    </span>
                </Link>

                {/* Ícone de Navegação - Travados na direita */}
                <div className="flex items-center h-full gap-1">

                    {/* Item Ativo (Servidores) */}
                    <Link href={"/"} className="h-full px-5 text-(--color-text-value) flex items-center relative">
                        <svg width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="relative z-10">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 12 12 17 22 12"></polyline>
                            <polyline points="2 17 12 22 22 17"></polyline>
                        </svg>
                        <div className="absolute bottom-0 left-0 w-full h-0.5 bg-(--color-primary) shadow-[0_-2px_10px_rgba(157,86,255,0.4)]" />
                    </Link>

                    {/* Admin Link */}
                    {session.data?.user?.role === 'admin' && (
                        <a href="/admin/servers" className="h-full px-5 text-(--color-text-value) hover:text-(--color-text-sub) transition-colors duration-200 flex items-center">
                            <svg width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </a>
                    )}

                    {/* User / Perfil */}
                    <button className="h-full px-5 text-(--color-text-value) hover:text-(--color-text-sub) transition-colors duration-200 flex items-center">
                        <svg width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </button>

                    {/* Divisor */}
                    <div className="w-px h-6 bg-(--color-text-sub)/10 mx-2" />

                    {/* Logout */}
                    <button
                        onClick={() => session.signOut({callbackUrl: '/auth'})}
                        className="h-full px-5 text-(--color-text-value) hover:text-(--color-danger) transition-colors duration-200 flex items-center"
                    >
                        <svg width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>
    );
};

export default Navbar;
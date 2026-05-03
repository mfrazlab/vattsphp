import React, { useState } from 'react';
import {GuestOnly, useSession} from "@vatts/auth/react";
import {useToast} from "@/web/contexts/ToastContext";
import {router} from "vatts/react";

export default function App() {
    const session = useSession()
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const toast = useToast()

    const handleSubmit = async (e: { preventDefault: () => void; }) => {
        e.preventDefault();
        const sign = await session.signIn('credentials', {
            email: username,
            password: password,
            redirect: false
        })
        if (sign && sign.ok === true) {
            toast.addToast('Login realizado com sucesso!', 'success')
            router.push('/')
        } else {
            toast.addToast('Erro ao realizar login. Verifique suas credenciais.', 'error')
        }
    };

    return (
        <GuestOnly redirectTo="/">
            <div
                className="min-h-screen flex flex-col justify-center items-center font-sans relative"
            >
                {/* Container Principal */}
                <div className="w-full max-w-[420px] px-6">
                    {/* Título */}
                    <h1 className="text-[32px] font-semibold text-center mb-10 tracking-tight">
                        Login
                    </h1>

                    {/* Formulário */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-[11px] font-bold text-gray-400 mb-2 tracking-wider uppercase">
                                Username or Email
                            </label>
                            <input
                                type="text"
                                value={username}
                                onChange={(e) => setUsername(e.target.value)}
                                className="w-full px-4 py-3 rounded-md border border-transparent focus:outline-none transition-all duration-300"
                                style={{
                                    backgroundColor: '#111118',
                                    boxShadow: 'inset 0 1px 2px rgba(0,0,0,0.1)'
                                }}
                                onFocus={(e) => e.target.style.borderColor = '#9D56FF'}
                                onBlur={(e) => e.target.style.borderColor = 'transparent'}
                            />
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-gray-400 mb-2 tracking-wider uppercase">
                                Password
                            </label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="w-full px-4 py-3 rounded-md border border-transparent focus:outline-none transition-all duration-300"
                                style={{
                                    backgroundColor: '#111118',
                                    boxShadow: 'inset 0 1px 2px rgba(0,0,0,0.1)'
                                }}
                                onFocus={(e) => e.target.style.borderColor = '#9D56FF'}
                                onBlur={(e) => e.target.style.borderColor = 'transparent'}
                            />
                        </div>

                        <button
                            type="submit"
                            className="w-full font-bold py-3 px-4 rounded-md transition-all duration-300 hover:brightness-110 mt-4 uppercase"
                            style={{
                                backgroundColor: '#9D56FF',
                                color: '#ffffff',
                                fontSize: '13px',
                                letterSpacing: '0.5px'
                            }}
                        >
                            Login
                        </button>
                    </form>

                    {/* Link Esqueceu a Senha */}
                    <div className="mt-8 mb-8 text-center">
                        <a
                            href="#"
                            className="text-[12px] font-medium text-[#6b6b77] hover:text-[#9D56FF] transition-colors uppercase tracking-wide"
                        >
                            Não definiu ou esqueceu a senha?
                        </a>
                    </div>

                    {/* Botão Secundário (Conta Reis) */}
                    <div className="flex justify-center mt-2">
                        <button
                            type="button"
                            className="flex items-center justify-center gap-3 py-3 px-6 rounded-md transition-all duration-300 hover:brightness-110 w-[300px]"
                            style={{ backgroundColor: '#9D56FF' }}
                        >
                            {/* Ícone de "chama" estilizado como no logo original, mas branco */}
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" className="text-white">
                                <path d="M12.4485 2C12.4485 2 17.5143 5.48528 17.5143 11.1421C17.5143 16.8579 13.0658 20.9142 12.4485 20.9142C12.4485 20.9142 13.6828 17.5143 13.6828 14.1142C13.6828 10.7142 10.7485 8.44853 10.7485 8.44853C10.7485 8.44853 7.68283 11.8485 7.68283 16.3828C7.68283 20.9142 9.68283 23.9142 9.68283 23.9142C9.68283 23.9142 4.61706 20.5142 4.61706 14.8579C4.61706 9.20153 8.61706 4.74853 8.61706 4.74853C8.61706 4.74853 7.61706 7.01422 7.61706 9.28004C7.61706 9.28004 12.4485 2 12.4485 2Z" fill="currentColor"/>
                            </svg>
                            <span className="text-white font-medium text-[15px]">Login com conta Reis</span>
                        </button>
                    </div>
                </div>

                {/* Footer */}
                <div className="absolute bottom-8 text-center w-full">
                    <p className="text-[13px] text-[#5b5b66]">
                        © 2015 - 2026 Pterodactyl Software
                    </p>
                </div>
            </div>
        </GuestOnly>

    );
}
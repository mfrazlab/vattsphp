import React, { useState } from 'react';
import {GuestOnly, useSession} from "@vatts/auth/react";
import {useToast} from "@/web/contexts/ToastContext";
import {router} from "vatts/react";
import Input from "@/web/components/commons/components/Input";
import Button from "@/web/components/commons/components/Button";
import Footer from "@/web/components/commons/Footer";

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
                    <h1 className="text-[32px] text-(--color-primary) font-semibold text-center mb-10 tracking-tight">
                        Login
                    </h1>

                    {/* Formulário */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-[11px] font-bold text-(--color-text-label) mb-2 tracking-wider uppercase">
                                Username or Email
                            </label>
                            <Input
                                type="text"
                                value={username}
                                onChange={(e) => setUsername(e.target.value)}
                            />
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-gray-400 mb-2 tracking-wider uppercase">
                                Password
                            </label>
                            <Input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full font-bold py-3 px-4 uppercase"
                        >
                            Login
                        </Button>
                    </form>

                    {/* Link Esqueceu a Senha */}
                    <div className="mt-8 mb-8 text-center">
                        <a
                            href="#"
                            className="text-[12px] font-medium text-(--color-text-sub) hover:text-(--color-primary) transition-colors uppercase tracking-wide"
                        >
                            Não definiu ou esqueceu a senha?
                        </a>
                    </div>

                </div>

                {/* Footer */}
               <Footer></Footer>
            </div>
        </GuestOnly>

    );
}
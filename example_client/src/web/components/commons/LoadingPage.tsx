import React from 'react';
import { motion } from 'framer-motion';

const LoadingPage: React.FC = () => {
    return (
        // Transformamos a div em motion.div e adicionamos as propriedades de animação
        <motion.div
            className="flex items-center justify-center w-full h-full"
            // Estado inicial (como ele começa antes de entrar)
            initial={{ opacity: 0, scale: 0.9 }}
            // Estado final (como ele fica quando entra)
            animate={{ opacity: 0.7, scale: 1 }}
            // Estado de saída (como ele faz quando vai sumir)
            exit={{ opacity: 0, scale: 1.1 }}
            // Configuração da transição (suavidade)
            transition={{
                duration: 0.15, // Diminuí de 0.3 para 0.15 pra ficar mais rápido
                ease: [0.4, 0, 0.2, 1] // Um ease-in-out suave
            }}
        >
            {/* Elemento Giratório (Mantemos o SVG original) */}
            <svg
                className="animate-spin h-14 w-14 text-(--color-primary)"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                ></circle>
                <path
                    className="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </motion.div>
    );
};

export default LoadingPage;
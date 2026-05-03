import React from "react";

type CardProps = {
    children: React.ReactNode
    title: string;
}

export default function Card({ children, title } : CardProps) {
    return (
        <div className="bg-(--color-secondary) rounded-xl shadow-(--card-shadow)">
            <div className="bg-(--color-sidebar) p-4 w-full rounded-t-xl font-light">
                {title}
            </div>

            <div className="p-4">
                {children}
            </div>
        </div>
    )
}
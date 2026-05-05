import React from "react";

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?:
        | "primary"
        | "secondary"
        | "ghost"
        | "danger"
        | "success"
        | "warning"
        | "info";
    fullWidth?: boolean;
};

export default function Button({
                                   variant = "primary",
                                   fullWidth = false,
                                   className,
                                   ...props
                               }: ButtonProps) {
    const baseClass = `
    cursor-pointer
        inline-flex items-center justify-center gap-2
        px-4 py-3 rounded-md
        font-medium text-sm tracking-wide
        transition-all duration-200
        outline-none border-none
        focus:ring-2 focus:ring-(--color-primary)
        disabled:opacity-50 disabled:cursor-not-allowed
        ${fullWidth ? "w-full" : ""}
    `;

    const variants = {
        primary: `
            bg-(--color-primary) text-white
            hover:brightness-110 active:brightness-95
        `,
        secondary: `
            bg-(--color-secondary) text-(--color-text-label)
            hover:brightness-110 active:brightness-95
        `,
        ghost: `
            bg-transparent text-(--color-text-label)
            hover:bg-white/5 active:bg-white/10
        `,
        danger: `
            bg-(--color-danger) text-white
            hover:brightness-110 active:brightness-95
        `,
        success: `
            bg-(--color-success) text-white
            hover:brightness-110 active:brightness-95
        `,
        warning: `
            bg-(--color-warning) text-black
            hover:brightness-110 active:brightness-95
        `,
        info: `
            bg-(--color-info) text-white
            hover:brightness-110 active:brightness-95
        `,
    };

    return (
        <button
            className={`
                ${baseClass}
                ${variants[variant]}
                ${className || ""}
            `}
            {...props}
        />
    );
}
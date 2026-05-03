import React from 'react';
import copy from 'copy-to-clipboard';
import classNames from 'classnames';
import {useToast} from "@/web/contexts/ToastContext";

interface CopyOnClickProps {
    text: string | number | null | undefined;
    children: React.ReactElement;
    notify: boolean
}

const CopyOnClick = ({ text, children, notify }: CopyOnClickProps) => {
    if (!React.isValidElement(children)) {
        throw new Error('Component passed to <CopyOnClick/> must be a valid React element.');
    }

    const toast = useToast()

    const handleClick = (e: React.MouseEvent<HTMLElement>) => {
        if (text) {
            copy(String(text));
        }
        if(notify) {
            toast.addToast(`Copiado ${text} com sucesso!`, 'success')
        }

        // Mantém a funcionalidade original do clique do componente filho, se existir
        // @ts-ignore
        if (typeof children.props.onClick === 'function') {
            // @ts-ignore
            children.props.onClick(e);
        }
    };

    // @ts-ignore
    return React.cloneElement(React.Children.only(children), {
        // @ts-ignore
        className: classNames(children.props.className || '', 'cursor-pointer'),
        onClick: handleClick,
    });
};

export default CopyOnClick;
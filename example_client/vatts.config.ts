import type { VattsConfigFunction } from 'vatts';

const vattsConfig: VattsConfigFunction = (phase, { defaultConfig }) => {
    return {
        ...defaultConfig
    };
};

export default vattsConfig;
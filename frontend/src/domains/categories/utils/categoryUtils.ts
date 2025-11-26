// src/domains/categories/utils/categoryUtils.ts

export function getRandomPastelHex(): string {
    const pastelColors = [
        '#FFB3BA', '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF',
        '#D7BAFF', '#FFBADC', '#BFFCC6', '#B9FBC0', '#A0CED9',
        '#FDCBFA', '#C4FAF8', '#F6D186', '#D5AAFF', '#FFDAC1',
        '#C1C8E4', '#A8E6CF', '#FFD3B6', '#E2F0CB', '#C7CEEA',
        '#EEEEEE', '#FFFFFF',
    ];
    return pastelColors[Math.floor(Math.random() * pastelColors.length)];
}

export function darkenColor(hex: string, percent: number): string {
    const num = parseInt(hex.replace('#', ''), 16);
    const r = (num >> 16) - percent;
    const g = ((num >> 8) & 0x00FF) - percent;
    const b = (num & 0x0000FF) - percent;
    return `#${Math.max(0, r).toString(16).padStart(2, '0')}${Math.max(0, g).toString(16).padStart(2, '0')}${Math.max(0, b).toString(16).padStart(2, '0')}`;
}
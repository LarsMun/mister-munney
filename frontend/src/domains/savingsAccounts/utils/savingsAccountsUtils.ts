// src/domains/savingsaccounts/utils/savingsAccountUtils.ts

export function getRandomPrimaryHex(): string {
    const vibrantColors = [
        '#FF5733', // rood/oranje
        '#FF8C00', // donker oranje
        '#FFC300', // geel
        '#4CAF50', // groen
        '#2196F3', // blauw
        '#9C27B0', // paars
        '#E91E63', // roze
        '#00BCD4', // cyaan
        '#3F51B5', // indigo
        '#F44336', // rood
        '#009688', // teal
        '#673AB7', // diep paars
    ];
    return vibrantColors[Math.floor(Math.random() * vibrantColors.length)];
}

export function darkenColor(hex: string, percent: number): string {
    const num = parseInt(hex.replace('#', ''), 16);
    const r = (num >> 16) - percent;
    const g = ((num >> 8) & 0x00FF) - percent;
    const b = (num & 0x0000FF) - percent;
    return `#${Math.max(0, r).toString(16).padStart(2, '0')}${Math.max(0, g).toString(16).padStart(2, '0')}${Math.max(0, b).toString(16).padStart(2, '0')}`;
}
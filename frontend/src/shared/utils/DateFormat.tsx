export function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const formatted = date.toLocaleDateString("nl-NL", {
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric",
    });

    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}

export function formatDateFullMonthName(dateStr: string): string {
    const date = new Date(dateStr);
    const formatted = date.toLocaleDateString("nl-NL", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });

    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}

export function formatMonth(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString("nl-NL", {
        month: "long",
        year: "numeric",
    });
}

export function formatMonthShort(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString("nl-NL", {
        month: "short",
        year: "numeric",
    });
}

export function formatMonthFull(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString("nl-NL", {
        month: "long",
    });
}

export function formatDateToLocalString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}
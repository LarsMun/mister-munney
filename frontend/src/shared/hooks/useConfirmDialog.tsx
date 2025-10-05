// src/components/hooks/useConfirmDialog.tsx
import { useState, useCallback } from "react";
import ConfirmDialog from "../components/ConfirmDialog.tsx";

type ConfirmOptions = {
    title: string;
    description?: string;
};

export function useConfirmDialog() {
    const [isOpen, setIsOpen] = useState(false);
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const [resolver, setResolver] = useState<(result: boolean) => void>(() => () => {});

    const confirm = useCallback((opts: ConfirmOptions) => {
        setOptions(opts);
        setIsOpen(true);
        return new Promise<boolean>((resolve) => {
            setResolver(() => resolve);
        });
    }, []);

    const handleConfirm = () => {
        setIsOpen(false);
        resolver(true);
    };

    const handleCancel = () => {
        setIsOpen(false);
        resolver(false);
    };

    const Confirm = isOpen && options ? (
        <ConfirmDialog
            open={isOpen}
            title={options.title}
            description={options.description}
            onCancel={handleCancel}
            onConfirm={handleConfirm}
        />
    ) : null;

    return { confirm, Confirm };
}
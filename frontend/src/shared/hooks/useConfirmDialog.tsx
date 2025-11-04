// src/components/hooks/useConfirmDialog.tsx
import { useState, useCallback } from "react";
import ConfirmDialog from "../components/ConfirmDialog.tsx";

type ConfirmOptions = {
    title: string;
    description?: string;
    checkbox?: {
        label: string;
        defaultChecked?: boolean;
    };
};

type ConfirmResult = {
    confirmed: boolean;
    checkboxValue?: boolean;
};

export function useConfirmDialog() {
    const [isOpen, setIsOpen] = useState(false);
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const [resolver, setResolver] = useState<(result: ConfirmResult) => void>(() => () => {});

    const confirm = useCallback((opts: ConfirmOptions) => {
        setOptions(opts);
        setIsOpen(true);
        return new Promise<ConfirmResult>((resolve) => {
            setResolver(() => resolve);
        });
    }, []);

    const handleConfirm = (checkboxValue?: boolean) => {
        setIsOpen(false);
        resolver({ confirmed: true, checkboxValue });
    };

    const handleCancel = () => {
        setIsOpen(false);
        resolver({ confirmed: false });
    };

    const Confirm = isOpen && options ? (
        <ConfirmDialog
            open={isOpen}
            title={options.title}
            description={options.description}
            checkbox={options.checkbox}
            onCancel={handleCancel}
            onConfirm={handleConfirm}
        />
    ) : null;

    return { confirm, Confirm };
}
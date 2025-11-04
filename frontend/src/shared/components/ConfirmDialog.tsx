// src/components/ConfirmDialog.tsx
import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { useState } from "react";

type Props = {
    open: boolean;
    title: string;
    description?: string;
    onConfirm: (checkboxValue?: boolean) => void;
    onCancel: () => void;
    checkbox?: {
        label: string;
        defaultChecked?: boolean;
    };
};

export default function ConfirmDialog({ open, title, description, onConfirm, onCancel, checkbox }: Props) {
    const [checkboxValue, setCheckboxValue] = useState(checkbox?.defaultChecked ?? false);

    const handleConfirm = () => {
        onConfirm(checkbox ? checkboxValue : undefined);
    };

    return (
        <Dialog.Root open={open} onOpenChange={onCancel}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/30 z-50" />
                <Dialog.Content
                    className="fixed z-50 left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white p-6 rounded shadow-lg space-y-4"
                >
                    <div className="flex justify-between items-start">
                        <Dialog.Title className="text-lg font-semibold text-gray-800">{title}</Dialog.Title>
                        <button onClick={onCancel} className="text-gray-500 hover:text-gray-700">
                            <X size={20} />
                        </button>
                    </div>
                    {description && <div className="text-sm text-gray-600">{description}</div>}
                    {checkbox && (
                        <div className="flex items-center gap-2 pt-2">
                            <input
                                type="checkbox"
                                id="confirm-checkbox"
                                checked={checkboxValue}
                                onChange={(e) => setCheckboxValue(e.target.checked)}
                                className="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500"
                            />
                            <label htmlFor="confirm-checkbox" className="text-sm text-gray-700 cursor-pointer">
                                {checkbox.label}
                            </label>
                        </div>
                    )}
                    <div className="flex justify-end gap-2 pt-4">
                        <button
                            onClick={onCancel}
                            className="px-4 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded"
                        >
                            Annuleren
                        </button>
                        <button
                            onClick={handleConfirm}
                            className="px-4 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white rounded"
                        >
                            Verwijderen
                        </button>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
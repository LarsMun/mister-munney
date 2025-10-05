// src/components/ConfirmDialog.tsx
import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import React from "react";

type Props = {
    open: boolean;
    title: string;
    description?: string;
    onConfirm: () => void;
    onCancel: () => void;
};

export default function ConfirmDialog({ open, title, description, onConfirm, onCancel }: Props) {
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
                    <div className="flex justify-end gap-2 pt-4">
                        <button
                            onClick={onCancel}
                            className="px-4 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded"
                        >
                            Annuleren
                        </button>
                        <button
                            onClick={onConfirm}
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
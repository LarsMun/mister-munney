import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { useEffect, useState } from "react";
import type { Transaction } from "../../transactions/models/Transaction";
import PatternForm from "./PatternForm";

type Props = {
    transaction: Transaction | null;
    transactions: Transaction[];
    onClose: () => void;
};

export default function PatternDrawer({ transaction, onClose }: Props) {
    const [open, setOpen] = useState(false);
    const [closing, setClosing] = useState(false);

    useEffect(() => {
        if (transaction) {
            setOpen(true);
            setClosing(false);
        }
    }, [transaction]);

    const handleClose = () => {
        setClosing(true);
        setTimeout(() => {
            setOpen(false);
            onClose();
        }, 300);
    };

    const handleSuccess = () => {
        handleClose();
    };

    if (!transaction) return null;

    return (
        <Dialog.Root open={open} onOpenChange={handleClose}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/30" />
                <Dialog.Content
                    className={`fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-xl p-6 overflow-y-auto z-50
                    ${closing ? "animate-out slide-out-to-right" : "animate-in slide-in-from-right"} duration-300`}
                >
                    <div className="flex items-center justify-between mb-6 border-b pb-2">
                        <Dialog.Title className="text-xl font-semibold text-gray-800">
                            Nieuw patroon maken
                        </Dialog.Title>
                        <button
                            onClick={handleClose}
                            className="p-1 rounded hover:bg-gray-100 text-gray-500"
                            aria-label="Close"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    <PatternForm
                        prefill={{
                            description: transaction.description,
                            notes: undefined,
                            categoryId: transaction.category?.id,
                            savingsAccountId: transaction.savingsAccount?.id,
                        }}
                        onSuccess={handleSuccess}
                    />
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

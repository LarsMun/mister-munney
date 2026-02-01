import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { useEffect, useState } from "react";
import type { Transaction } from "../models/Transaction";
import { formatMoney } from "../../../shared/utils/MoneyFormat.tsx";
import { formatDate } from "../../../shared/utils/DateFormat.tsx";
import { useNavigate } from "react-router-dom";

type Props = {
    transaction: Transaction | null;
    onClose: () => void;
    onFilterByDescription?: (description: string) => void;
    onFilterByNotes?: (notes: string) => void;
};

export default function TransactionDrawer({ transaction, onClose, onFilterByDescription, onFilterByNotes }: Props) {
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const [closing, setClosing] = useState(false);
    const [visibleTransaction, setVisibleTransaction] = useState<Transaction | null>(null);

    useEffect(() => {
        if (transaction) {
            setVisibleTransaction(transaction);
            setOpen(true);
            setClosing(false);
        }
    }, [transaction]);

    if (!transaction) return null;

    const handleClose = () => {
        setClosing(true);
        setTimeout(() => {
            setOpen(false);
            setVisibleTransaction(null);
            onClose();
        }, 300);
    };

    if (!visibleTransaction) return null;

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
                            Transactiedetails
                        </Dialog.Title>
                        <button
                            onClick={handleClose}
                            className="p-1 rounded hover:bg-gray-100 text-gray-500"
                            aria-label="Close"
                        >
                            <X size={20}/>
                        </button>
                    </div>

                    <dl className="space-y-4 text-sm">
                        <Detail label="Datum" value={formatDate(transaction.date)}/>
                        <ClickableDetail
                            label="Omschrijving"
                            value={transaction.description}
                            onClick={() => {
                                onFilterByDescription?.(transaction.description);
                                handleClose();
                            }}
                        />
                        <Detail
                            label="Bedrag"
                            value={
                                <span
                                    className={`font-mono ${transaction.transactionType === "debit" ? "text-red-500" : "text-green-700"}`}>
                                    {transaction.transactionType === "debit" ? "–" : "+"} {formatMoney(transaction.amount)}{" "}
                                    {transaction.transactionType === "debit" ? "▼" : "▲"}
                                </span>
                            }
                        />
                        <Detail label="Mutatiesoort" value={transaction.mutationType}/>
                        <Detail label="Code" value={transaction.transactionCode}/>
                        <Detail label="Tegenrekening" value={transaction.counterpartyAccount || null}/>
                        <Detail label="Categorie" value={transaction.category?.name || null}/>
                        <Detail label="Tag" value={transaction.tag || null}/>
                        <ClickableDetail
                            label="Notities"
                            value={transaction.notes || null}
                            onClick={() => {
                                if (transaction.notes) {
                                    onFilterByNotes?.(transaction.notes);
                                    handleClose();
                                }
                            }}
                        />
                        <Detail label="Balans na transactie" value={transaction.balanceAfter !== null ? formatMoney(transaction.balanceAfter) : '—'}/>
                    </dl>
                    <button
                        onClick={() => navigate('/patterns', {
                            state: {
                                transaction,
                            }
                        })}
                        className="inline-flex items-center px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded shadow transition mt-6">
                        Maak patroon van deze transactie
                    </button>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function Detail({ label, value }: { label: string; value?: string | React.ReactNode | null }) {
    return (
        <div>
            <dt className="font-medium text-gray-600">{label}</dt>
            <dd className="text-gray-900">{value ?? <span className="text-gray-400 italic">–</span>}</dd>
        </div>
    );
}

function ClickableDetail({ label, value, onClick }: { label: string; value?: string | null; onClick: () => void }) {
    if (!value) {
        return <Detail label={label} value={null} />;
    }

    return (
        <div>
            <dt className="font-medium text-gray-600">{label}</dt>
            <dd
                className="text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 rounded px-1 -mx-1 transition-colors"
                onClick={onClick}
                title="Klik om te filteren"
            >
                {value}
            </dd>
        </div>
    );
}

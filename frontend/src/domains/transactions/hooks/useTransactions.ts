import { useEffect, useState } from "react";
import { useAccount } from "../../../app/context/AccountContext";
import { getAvailableMonths, getTransactions, importTransactions as importTransactionsService } from "../services/TransactionsService";
import { toast } from "react-hot-toast";
import type { Transaction } from "../models/Transaction";
import type { SummaryType } from "../models/SummaryType";
import type { TreeMapDataType } from "../models/TreeMapDataType";

export function useTransactions() {
    const { accountId } = useAccount();

    const [months, setMonths] = useState<string[]>([]);
    const [startDate, setStartDate] = useState<string | null>(null);
    const [endDate, setEndDate] = useState<string | null>(null);
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [summary, setSummary] = useState<SummaryType | null>(null);
    const [treeMapData, setTreeMapData] = useState<TreeMapDataType | null>(null);

    useEffect(() => {
        if (!accountId) return;

        getAvailableMonths(accountId)
            .then(availableMonths => {
                setMonths(availableMonths);
                if (availableMonths.length > 0) {
                    const [year, month] = availableMonths[0].split("-");
                    const start = `${year}-${month}-01`;
                    const end = new Date(Number(year), Number(month), 0).toISOString().slice(0, 10);
                    setStartDate(start);
                    setEndDate(end);
                }
            })
            .catch(() => {
                toast.error("Fout bij ophalen beschikbare maanden.");
            });
    }, [accountId]);

    useEffect(() => {
        if (!accountId || !startDate || !endDate) return;
        loadTransactions(startDate, endDate);
    }, [accountId, startDate, endDate]);

    const loadTransactions = async (start: string, end: string) => {
        if (!accountId) return;

        try {
            const { data, summary, treeMapData } = await getTransactions(accountId, start, end);

            if (data.length === 0) {
                toast.success("Geen transacties in deze periode, laatste beschikbare maand wordt getoond.");
                if (months.length > 0) {
                    const [year, month] = months[0].split("-");
                    setStartDate(`${year}-${month}-01`);
                    setEndDate(new Date(Number(year), Number(month), 0).toISOString().slice(0, 10));
                }
                return;
            }

            setTransactions(data);
            setSummary(summary);
            setTreeMapData(treeMapData);
        } catch (error) {
            console.error(error);
            toast.error("Fout bij ophalen transacties.");
        }
    };

    const refresh = () => {
        if (startDate && endDate) {
            loadTransactions(startDate, endDate);
        }
    };

    const importTransactions = async (file: File) => {
        if (!accountId) return;

        try {
            await importTransactionsService(accountId, file);
            toast.success("Transacties geïmporteerd.");
            refresh();
        } catch (error) {
            console.error(error);
            toast.error("Importeren mislukt.");
        }
    };

    return {
        months,
        startDate,
        endDate,
        setStartDate,
        setEndDate,
        transactions,
        summary,
        treeMapData,
        refresh,
        importTransactions,
    };
}
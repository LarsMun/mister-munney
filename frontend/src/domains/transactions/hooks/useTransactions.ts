import { useEffect, useState } from "react";
import { useAccount } from "../../../app/context/AccountContext";
import { getAvailableMonths, getTransactions, importTransactions as importTransactionsService } from "../services/TransactionsService";
import { toast } from "react-hot-toast";
import type { Transaction } from "../models/Transaction";
import type { SummaryType } from "../models/SummaryType";
import type { TreeMapDataType } from "../models/TreeMapDataType";
import { formatDateToLocalString } from "../../../shared/utils/DateFormat";

export function useTransactions() {
    const { accountId } = useAccount();

    const [months, setMonths] = useState<string[]>([]);
    const [startDate, setStartDate] = useState<string | null>(null);
    const [endDate, setEndDate] = useState<string | null>(null);
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [summary, setSummary] = useState<SummaryType | null>(null);
    const [treeMapData, setTreeMapData] = useState<TreeMapDataType | null>(null);
    const [initialDatesSet, setInitialDatesSet] = useState(false);

    useEffect(() => {
        if (!accountId || initialDatesSet) return;

        getAvailableMonths(accountId)
            .then(availableMonths => {
                setMonths(availableMonths);
                // Only set initial dates once, on first load
                if (availableMonths.length > 0) {
                    const [year, month] = availableMonths[0].split("-");
                    const start = formatDateToLocalString(new Date(Number(year), Number(month) - 1, 1));
                    const end = formatDateToLocalString(new Date(Number(year), Number(month), 0));
                    setStartDate(start);
                    setEndDate(end);
                    setInitialDatesSet(true);
                }
            })
            .catch(() => {
                toast.error("Fout bij ophalen beschikbare maanden.");
            });
    }, [accountId, initialDatesSet]);

    useEffect(() => {
        if (!accountId || !startDate || !endDate) return;
        loadTransactions(startDate, endDate);
    }, [accountId, startDate, endDate]);

    const loadTransactions = async (start: string, end: string) => {
        if (!accountId) return;

        try {
            const { data, summary, treeMapData } = await getTransactions(accountId, start, end);

            // Always set the data, even if empty - don't auto-redirect
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
import type { PatternInput } from "../models/PatternInput";
import SimpleCategoryCombobox from "../../categories/components/SimpleCategoryCombobox";
import SimpleSavingsAccountCombobox from "../../savingsAccounts/components/SimpleSavingsAccountCombobox";

interface Props {
    pattern: PatternInput;
    updateField: (key: keyof PatternInput, value: any) => void;
}

export default function PatternFormElements({ pattern, updateField }: Props) {
    return (
        <>
            {/* Rij 1: Categorie en Spaarrekening */}
            <div className="flex gap-4 items-end">
                <div className="w-56">
                    <label className="block text-xs font-medium text-gray-600">Categorie</label>
                    <SimpleCategoryCombobox
                        categoryId={pattern.categoryId ?? null}
                        onChange={(c) => updateField("categoryId", c?.id ?? null)}
                        refreshCategories={() => {}}
                        transactionType={pattern.transactionType}  // <-- NIEUW
                    />
                </div>
                <div className="w-56">
                    <label className="block text-xs font-medium text-gray-600">Spaarrekening</label>
                    <SimpleSavingsAccountCombobox
                        savingsAccountId={pattern.savingsAccountId ?? null}
                        onChange={(sa) => updateField("savingsAccountId", sa?.id ?? null)}
                    />
                </div>
            </div>

            {/* Rij 2: Omschrijving + matchtype + notities + matchtype */}
            <div className="flex gap-4 mt-2 items-end">
                <div className="flex-1">
                    <label className="block text-xs font-medium text-gray-600">Omschrijving</label>
                    <input
                        type="text"
                        value={pattern.description ?? ""}
                        onChange={(e) => updateField("description", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-28">
                    <label className="block text-xs font-medium text-gray-600">Type</label>
                    <select
                        value={pattern.matchTypeDescription ?? "LIKE"}
                        onChange={(e) => updateField("matchTypeDescription", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                    >
                        <option value="LIKE">LIKE</option>
                        <option value="EXACT">EXACT</option>
                        <option value="REGEX">REGEX</option>
                    </select>
                </div>

                <div className="flex-1">
                    <label className="block text-xs font-medium text-gray-600">Notities</label>
                    <input
                        type="text"
                        value={pattern.notes ?? ""}
                        onChange={(e) => updateField("notes", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-28">
                    <label className="block text-xs font-medium text-gray-600">Type</label>
                    <select
                        value={pattern.matchTypeNotes ?? "LIKE"}
                        onChange={(e) => updateField("matchTypeNotes", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                    >
                        <option value="LIKE">LIKE</option>
                        <option value="EXACT">EXACT</option>
                        <option value="REGEX">REGEX</option>
                    </select>
                </div>
            </div>

            {/* Rij 3: Datums, bedragen, transactie type, checkbox */}
            <div className="flex gap-4 mt-2 items-end">
                <div className="w-40">
                    <label className="block text-xs font-medium text-gray-600">Startdatum</label>
                    <input
                        type="date"
                        value={pattern.startDate ?? ""}
                        onChange={(e) => updateField("startDate", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-40">
                    <label className="block text-xs font-medium text-gray-600">Einddatum</label>
                    <input
                        type="date"
                        value={pattern.endDate ?? ""}
                        onChange={(e) => updateField("endDate", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-24">
                    <label className="block text-xs font-medium text-gray-600">Min bedrag</label>
                    <input
                        type="number"
                        min="0"
                        value={pattern.minAmount ?? ""}
                        onChange={(e) => {
                            const value = e.target.value === "" ? undefined : parseFloat(e.target.value);
                            if (value !== undefined && value < 0) return;
                            updateField("minAmount", value);
                        }}
                        className={`w-full border rounded p-1 h-8 ${
                            pattern.maxAmount !== undefined &&
                            pattern.minAmount !== undefined &&
                            pattern.minAmount > pattern.maxAmount
                                ? "border-red-500"
                                : "border-gray-300"
                        }`}
                    />
                </div>

                <div className="w-24">
                    <label className="block text-xs font-medium text-gray-600">Max bedrag</label>
                    <input
                        type="number"
                        min="0"
                        value={pattern.maxAmount ?? ""}
                        onChange={(e) => {
                            const value = e.target.value === "" ? undefined : parseFloat(e.target.value);
                            if (value !== undefined && value < 0) return;
                            updateField("maxAmount", value);
                        }}
                        className={`w-full border rounded p-1 h-8 ${
                            pattern.maxAmount !== undefined &&
                            pattern.minAmount !== undefined &&
                            pattern.maxAmount < pattern.minAmount
                                ? "border-red-500"
                                : "border-gray-300"
                        }`}
                    />
                </div>

                <div className="w-24">
                    <label className="block text-xs font-medium text-gray-600">Type</label>
                    <select
                        value={pattern.transactionType ?? "debit"}  // <-- Default naar "debit" als er geen waarde is
                        onChange={(e) => updateField("transactionType", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                    >
                        {/* Verwijder de "Beide" optie */}
                        <option value="debit">Af</option>
                        <option value="credit">Bij</option>
                    </select>
                </div>

                <div className="flex items-center h-8 mt-4">
                    <label className="inline-flex items-center text-xs text-gray-700 group">
                        <input
                            type="checkbox"
                            checked={pattern.strict ?? false}
                            onChange={(e) => updateField("strict", e.target.checked)}
                            className="mr-2"
                        />
                        Overschrijf bestaande
                        <span className="ml-1 text-gray-400 cursor-help group-hover:underline"
                              title="Als dit aanstaat, worden ook transacties met een bestaande categorie of spaarrekening overschreven.">
                            ⓘ
                        </span>
                    </label>
                </div>
            </div>
        </>
    );
}
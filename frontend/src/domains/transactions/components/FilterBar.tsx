import React from "react";
import { formatDate } from "../../../shared/utils/DateFormat";
import FilterBadge from "./FilterBadge";
import { XCircle, CalendarX, Tag } from "lucide-react";

interface Props {
    selectedCategoryNames: string[];
    clearCategory: (category: string) => void;
    finalSelection: { start: string; end: string } | null;
    clearDateSelection: () => void;
}

export default function FilterBar({
                                      selectedCategoryNames,
                                      clearCategory,
                                      finalSelection,
                                      clearDateSelection,
                                  }: Props) {
    if (selectedCategoryNames.length === 0 && !finalSelection) return null;

    return (
        <div className="mt-4 mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
            {selectedCategoryNames.map((name) => (
                <FilterBadge
                    key={name}
                    icon={<Tag size={12} />}
                    label={name}
                    onClear={() => clearCategory(name)}
                />
            ))}
            {finalSelection && (
                <FilterBadge
                    icon={<CalendarX size={12} />}
                    label={`Periode: ${formatDate(finalSelection.start)} t/m ${formatDate(finalSelection.end)}`}
                    onClear={clearDateSelection}
                />
            )}
            {(selectedCategoryNames.length > 0 || finalSelection) && (
                <button
                    onClick={() => {
                        clearDateSelection();
                        selectedCategoryNames.forEach(clearCategory);
                    }}
                    className="ml-auto flex items-center gap-1 text-red-600 hover:underline text-xs"
                >
                    <XCircle size={14} /> Verwijder alle filters
                </button>
            )}
        </div>
    );
}
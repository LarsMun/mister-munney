import React from "react";
import {X} from "lucide-react";

interface FilterBadgeProps {
    icon: React.ReactNode,
    label: string,
    onClear: () => void,
}

export default function FilterBadge({icon, label, onClear}: FilterBadgeProps) {
    return (
        <div
            className="flex items-center gap-2 bg-gray-100 px-3 py-1 rounded-full text-xs text-gray-700 border border-gray-300 shadow-sm">
            <div className="flex items-center gap-1">
                {icon}
                <span>{label}</span>
            </div>
            <button onClick={onClear} className="hover:text-red-600">
                <X size={12}/>
            </button>
        </div>
    );
}
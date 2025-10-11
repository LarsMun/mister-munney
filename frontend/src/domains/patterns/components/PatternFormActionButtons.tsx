interface Props {
    onClose: () => void;
    onShowMatches: () => void;
    matchesLoaded: boolean;
    categorySelected: boolean;
}

export default function PatternFormActionButtons({ onClose, onShowMatches, matchesLoaded, categorySelected }: Props) {
    return (
        <div className="flex justify-end gap-2 mt-4">
            <button
                type="button"
                onClick={onClose}
                className="text-xs text-gray-600 underline"
            >
                Annuleren
            </button>

            {!matchesLoaded ? (
                <button
                    type="button"
                    onClick={onShowMatches}
                    disabled={!categorySelected}
                    title={!categorySelected ? "Kies eerst een categorie" : ""}
                    className={`px-3 py-1 text-xs rounded transition ${
                        categorySelected
                            ? "bg-gray-600 text-white hover:bg-gray-700"
                            : "bg-gray-200 text-gray-500 cursor-not-allowed"
                    }`}
                >
                    Toon alle matches
                </button>
            ) : (
                <button
                    type="submit"
                    disabled={!categorySelected}
                    title={!categorySelected ? "Kies eerst een categorie" : ""}
                    className={`px-3 py-1 text-xs rounded transition ${
                        categorySelected
                            ? "bg-blue-600 text-white hover:bg-blue-700"
                            : "bg-gray-200 text-gray-500 cursor-not-allowed"
                    }`}
                >
                    Opslaan
                </button>
            )}
        </div>
    );
}
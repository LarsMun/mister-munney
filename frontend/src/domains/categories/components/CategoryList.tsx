// frontend/src/domains/categories/components/CategoryList.tsx

import CategoryListItem from './CategoryListItem';
import type { Category } from '../models/Category';
import type { CategoryStatistic } from '../models/CategoryStatistics';

interface CategoryWithStats {
    category: Category;
    stats: CategoryStatistic | null;
}

interface CategoryListProps {
    categories: CategoryWithStats[];
    onRefresh: () => void;
    onCategoryClick?: (category: Category) => void;
    onMergeClick?: (category: Category) => void;
}

export default function CategoryList({ categories, onRefresh, onCategoryClick, onMergeClick }: CategoryListProps) {
    if (categories.length === 0) {
        return (
            <div className="bg-white rounded-lg shadow p-8 text-center">
                <div className="text-gray-400 mb-4">
                    <svg
                        className="mx-auto h-12 w-12"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                        />
                    </svg>
                </div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                    Geen categorieën gevonden
                </h3>
                <p className="text-gray-600">
                    Pas je zoekopdracht aan of maak een nieuwe categorie aan
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {categories.map(({ category, stats }) => (
                <CategoryListItem
                    key={category.id}
                    category={category}
                    stats={stats}
                    onRefresh={onRefresh}
                    onCategoryClick={onCategoryClick}
                    onMergeClick={onMergeClick}
                />
            ))}
        </div>
    );
}

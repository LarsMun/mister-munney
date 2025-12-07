import { Loader2 } from 'lucide-react';

interface PageLoaderProps {
    message?: string;
}

export default function PageLoader({ message = 'Laden...' }: PageLoaderProps) {
    return (
        <div className="flex justify-center items-center min-h-[400px]">
            <div className="text-center">
                <Loader2 className="h-10 w-10 animate-spin text-blue-600 mx-auto mb-3" />
                <p className="text-gray-500 text-sm">{message}</p>
            </div>
        </div>
    );
}

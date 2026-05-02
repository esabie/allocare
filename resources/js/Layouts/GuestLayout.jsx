import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function Guest({ children, wide = false }) {
    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-auto" />
                </Link>
            </div>

            <div
                className={
                    'w-full mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg ' +
                    (wide ? 'sm:max-w-2xl' : 'sm:max-w-md')
                }
            >
                {children}
            </div>
        </div>
    );
}

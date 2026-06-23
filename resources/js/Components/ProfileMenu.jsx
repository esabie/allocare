import Dropdown from '@/Components/Dropdown';
import ManagerNotificationBell from '@/Components/ManagerNotificationBell';
import { usePage } from '@inertiajs/react';

export default function ProfileMenu() {
    const authUser = usePage().props?.auth?.user;
    const fullName = authUser?.name || `${authUser?.first_name || ''} ${authUser?.surname || ''}`.trim() || 'User';
    const profilePhotoUrl = authUser?.photoUrl || null;
    const initials = authUser?.name
        ? authUser.name
              .split(' ')
              .map((part) => part[0])
              .join('')
              .slice(0, 2)
              .toUpperCase()
        : 'U';

    return (
        <div className="flex items-center gap-3">
            <ManagerNotificationBell />
            <Dropdown>
            <Dropdown.Trigger>
                <div className="flex items-center gap-2">
                    <span className="max-w-[180px] truncate text-sm font-medium text-slate-700">{fullName}</span>
                    <button
                        type="button"
                        className="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-cyan-600 text-xs font-semibold text-white"
                    >
                        {profilePhotoUrl ? (
                            <img src={profilePhotoUrl} alt={`${fullName} profile`} className="h-full w-full object-cover" />
                        ) : (
                            initials
                        )}
                    </button>
                </div>
            </Dropdown.Trigger>

            <Dropdown.Content width="48" contentClasses="py-1 bg-white">
                <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                <Dropdown.Link href={route('logout')} method="post" as="button">
                    Log Out
                </Dropdown.Link>
            </Dropdown.Content>
        </Dropdown>
        </div>
    );
}

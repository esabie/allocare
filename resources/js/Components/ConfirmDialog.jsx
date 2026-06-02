import { Dialog } from '@headlessui/react';
import Modal from '@/Components/Modal';

export default function ConfirmDialog({
    show = false,
    title,
    message,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    confirmVariant = 'danger',
    processing = false,
    onClose,
    onConfirm,
}) {
    const confirmClass = confirmVariant === 'danger'
        ? 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500'
        : 'bg-slate-900 hover:bg-slate-800 focus:ring-slate-500';

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <Dialog.Title className="text-lg font-semibold text-slate-900">{title}</Dialog.Title>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{message}</p>
                <div className="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className={`rounded-lg px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 ${confirmClass}`}
                    >
                        {processing ? 'Please wait…' : confirmLabel}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

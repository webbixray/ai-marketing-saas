import { Fragment, useEffect, useRef } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X } from 'lucide-react';
import { cn } from '@utils';

export interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
    children: React.ReactNode;
    closeOnOverlayClick?: boolean;
    closeOnEscape?: boolean;
    className?: string;
    showCloseButton?: boolean;
}

const sizeClasses = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    full: 'max-w-[90vw]',
};

export function Modal({
    isOpen,
    onClose,
    title,
    description,
    size = 'md',
    children,
    closeOnOverlayClick = true,
    closeOnEscape = true,
    className,
    showCloseButton = true,
}: ModalProps) {
    const titleId = title ? 'modal-title' : undefined;
    const descriptionId = description ? 'modal-description' : undefined;

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Escape' && closeOnEscape) {
            onClose();
        }
    };

    return (
        <Transition appear show={isOpen} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" aria-hidden="true" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4 text-center">
                        <Transition.Child
                            as={Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 scale-95"
                            enterTo="opacity-100 scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 scale-100"
                            leaveTo="opacity-0 scale-95"
                        >
                            <Dialog.Panel
                                className={cn(
                                    'w-full transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-xl transition-all',
                                    sizeClasses[size],
                                    className
                                )}
                                onKeyDown={handleKeyDown}
                            >
                                {(title || showCloseButton) && (
                                    <div className="flex items-start justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                                        <div>
                                            {title && (
                                                <Dialog.Title
                                                    as="h3"
                                                    id={titleId}
                                                    className="text-lg font-semibold text-slate-900 dark:text-slate-100"
                                                >
                                                    {title}
                                                </Dialog.Title>
                                            )}
                                            {description && (
                                                <Dialog.Description
                                                    id={descriptionId}
                                                    className="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {description}
                                                </Dialog.Description>
                                            )}
                                        </div>
                                        {showCloseButton && (
                                            <button
                                                type="button"
                                                onClick={onClose}
                                                className={cn(
                                                    'p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                                                    'hover:bg-slate-100 dark:hover:bg-slate-800',
                                                    'transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500'
                                                )}
                                                aria-label="Close modal"
                                            >
                                                <X className="w-5 h-5" />
                                            </button>
                                        )}
                                    </div>
                                )}

                                <div className="p-6">
                                    {children}
                                </div>
                            </Dialog.Panel>
                        </Transition.Child>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}

export interface ConfirmModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'primary' | 'warning';
    loading?: boolean;
}

export function ConfirmModal({
    isOpen,
    onClose,
    onConfirm,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'danger',
    loading = false,
}: ConfirmModalProps) {
    const variantStyles = {
        danger: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
        primary: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
        warning: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} title={title} size="sm">
            <p className="text-slate-600 dark:text-slate-300">{message}</p>
            <div className="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    onClick={onClose}
                    disabled={loading}
                    className="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
                    {cancelText}
                </button>
                <button
                    type="button"
                    onClick={onConfirm}
                    disabled={loading}
                    className={cn(
                        'px-4 py-2 text-sm font-medium text-white rounded-lg',
                        'focus:outline-none focus:ring-2 focus:ring-offset-2',
                        variantStyles[variant],
                        loading && 'opacity-50 cursor-not-allowed'
                    )}
                >
                    {loading ? 'Processing...' : confirmText}
                </button>
            </div>
        </Modal>
    );
}

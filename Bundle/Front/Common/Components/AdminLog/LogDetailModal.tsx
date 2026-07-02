import * as React from 'react';
import {useEffect, useRef} from 'react';
import {useBodyScrollLock} from '@common/hooks/useBodyScrollLock';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {Portal} from '@common/Components/Portal';

interface Props {
    title?: string;
    onClose: () => void;
    children: React.ReactNode;
}

/**
 * Generic modal shell for showing the full detail of a single log entry.
 * Reuses the `fg-modal-*` overlay/card classes used elsewhere in the app
 * (see Apps/MyApp/Front/Islands/SlotsCalendar/BookingModal.tsx).
 *
 * Behavior:
 *  - ESC closes
 *  - click on overlay closes (clicks inside the card don't bubble)
 *  - body scroll is locked while the modal is mounted
 *  - body has its own overflow-y-auto so large records remain scrollable
 */
export const LogDetailModal: React.FC<Props> = ({title, onClose, children}) => {
    useBodyScrollLock(true);
    const overlayRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    const handleOverlayClick = (e: React.MouseEvent) => {
        if (e.target === overlayRef.current) onClose();
    };

    const heading = title ?? t.LogDetail_Title();

    return (
        <Portal>
            <div
                ref={overlayRef}
                className="fg-modal-overlay-high"
                onClick={handleOverlayClick}
                data-test-id="log-detail-modal"
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label={heading}
                    className="fg-modal-card-flush fg-modal-card-3xl"
                    onClick={e => e.stopPropagation()}
                >
                    <div className="fg-modal-flush-header">
                        <h3 className="fg-modal-title">{heading}</h3>
                        <button
                            type="button"
                            className="fg-modal-close-x"
                            title={t.LogDetail_Close()}
                            aria-label={t.LogDetail_Close()}
                            onClick={onClose}
                            data-test-id="log-detail-modal-close"
                        >
                            &times;
                        </button>
                    </div>
                    <div className="fg-modal-flush-body">
                        {children}
                    </div>
                </div>
            </div>
        </Portal>
    );
};

import * as React from 'react';
import {useEffect, useRef} from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {EntityHistoryRow, formatHistoryTime} from './EntityHistoryTable';
import {Portal} from '@common/Components/Portal';

interface Props {
    row: EntityHistoryRow;
    onClose: () => void;
}

const formatValue = (v: unknown): string => {
    if (v === null || v === undefined) return '∅';
    if (v === '') return '""';
    if (typeof v === 'object') return JSON.stringify(v, null, 2);
    return String(v);
};

export const EntityHistoryDetailModal: React.FC<Props> = ({row, onClose}) => {
    const overlayRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onKey = (e: KeyboardEvent): void => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    const handleOverlayClick = (e: React.MouseEvent): void => {
        if (e.target === overlayRef.current) onClose();
    };

    const ts = formatHistoryTime(row.created_at);
    const actor = row.actor_name || row.actor_login_resolved || row.actor_login || '—';
    const hasDiff = row.diff && Object.keys(row.diff).length > 0;

    return (
        <Portal><div
            ref={overlayRef}
            className="fg-modal-overlay-high"
            onClick={handleOverlayClick}
            data-test-id="entity-history-detail-modal"
        >
            <div
                role="dialog"
                aria-modal="true"
                className="fg-modal-card-flush fg-modal-card-3xl"
                onClick={e => e.stopPropagation()}
            >
                <div className="fg-modal-flush-header">
                    <h3 className="fg-modal-title">{t.EntityHistory_Title()}</h3>
                    <button
                        type="button"
                        className="fg-modal-close-x"
                        onClick={onClose}
                        data-test-id="entity-history-detail-close"
                    >
                        &times;
                    </button>
                </div>
                <div className="fg-modal-flush-body">
                    <div className="mb-4 text-sm text-muted">
                        <div><strong>{t.EntityHistory_When()}:</strong> {ts}</div>
                        <div><strong>{t.EntityHistory_Actor()}:</strong> {actor}</div>
                    </div>
                    {hasDiff && (
                        <table className="admin-table">
                            <thead>
                                <tr className="border-b border-subtle text-left">
                                    <th>{t.EntityHistory_Field()}</th>
                                    <th>{t.EntityHistory_Old()}</th>
                                    <th>{t.EntityHistory_New()}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(row.diff!).map(([field, {old, new: nv}]) => (
                                    <tr key={field} className="border-b border-subtle align-top">
                                        <td className="font-mono text-xs whitespace-nowrap">{field}</td>
                                        <td className="text-danger break-all whitespace-pre-wrap">{formatValue(old)}</td>
                                        <td className="text-success break-all whitespace-pre-wrap">{formatValue(nv)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div></Portal>
    );
};

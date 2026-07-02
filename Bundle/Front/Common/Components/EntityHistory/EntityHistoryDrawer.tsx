import * as React from 'react';
import {useEffect, useRef, useState, useCallback} from 'react';
import {sendPost} from '@common/Api/sendPost';
import {appUrl} from '@common/Utils/appUrl';
import {useBodyScrollLock} from '@common/hooks/useBodyScrollLock';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {EntityHistoryRow, EntityHistoryTable} from './EntityHistoryTable';
import {Portal} from '@common/Components/Portal';

export type {EntityHistoryRow} from './EntityHistoryTable';

interface ListResponse {
    success: boolean;
    rows: EntityHistoryRow[];
}

interface Props {
    entityType: string;
    entityId: string | number;
    title?: string;
    /** Override endpoint URL. Defaults to /admin/entity-history/~list (with route prefix). */
    listUrl?: string;
    onClose: () => void;
}

const DEFAULT_LIST_URL = '/admin/entity-history/~list';

/**
 * Generic, drop-in viewer for the entity-history audit log of a single record.
 * Shows newest changes first; each row has timestamp, actor, action and a diff.
 *
 * Wire it in by mounting `<EntityHistoryButton entityType=… entityId=… />` —
 * which is the user-facing API. This Drawer is the modal it opens.
 */
export const EntityHistoryDrawer: React.FC<Props> = ({entityType, entityId, title, listUrl, onClose}) => {
    useBodyScrollLock(true);
    const overlayRef = useRef<HTMLDivElement>(null);

    const [rows, setRows] = useState<EntityHistoryRow[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string>('');

    const load = useCallback(async (): Promise<void> => {
        setLoading(true);
        setError('');
        try {
            const res = await sendPost<
                {entity_type: string; entity_id: string; limit: number},
                ListResponse
            >(appUrl(listUrl ?? DEFAULT_LIST_URL), {
                entity_type: entityType,
                entity_id: String(entityId),
                limit: 200,
            });
            setRows(res.rows ?? []);
        } catch (e: any) {
            setError(e?.message || 'Error loading history');
        } finally {
            setLoading(false);
        }
    }, [entityType, entityId, listUrl]);

    useEffect(() => {
        void load();
    }, [load]);

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

    const heading = title ?? `${t.EntityHistory_Title()} — ${entityType} #${entityId}`;

    return (
        <Portal><div
            ref={overlayRef}
            className="fg-modal-overlay-high"
            onClick={handleOverlayClick}
            data-test-id="entity-history-modal"
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
                        onClick={onClose}
                        data-test-id="entity-history-close"
                    >
                        &times;
                    </button>
                </div>
                <div className="fg-modal-flush-body">
                    {loading && <div className="text-muted">{t.EntityHistory_Loading()}</div>}
                    {error && <div className="text-error">{error}</div>}
                    {!loading && !error && rows.length === 0 && (
                        <div className="text-muted" data-test-id="entity-history-empty">{t.EntityHistory_Empty()}</div>
                    )}
                    {!loading && rows.length > 0 && (
                        <EntityHistoryTable rows={rows} />
                    )}
                </div>
            </div>
        </div></Portal>
    );
};

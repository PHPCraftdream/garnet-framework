import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {appUrl} from '@common/Utils/Url/appUrl';
import {useBodyScrollLock} from '@common/hooks/ui/internal/useBodyScrollLock';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {EntityHistoryRow, EntityHistoryTable} from './EntityHistoryTable';
import {Portal} from '@common/Components/Layout/Portal';

export type {EntityHistoryRow} from './EntityHistoryTable';

interface ListResponse { success: boolean; rows: EntityHistoryRow[] }
interface Props {
    entityType: string;
    entityId: string | number;
    title?: string;
    listUrl?: string;
    onClose: () => void;
}

const ScrollLock: React.FC = () => {
    useBodyScrollLock(true);
    return null;
};

/** Prevents clicks inside the drawer from closing it. */
function stopPropagation(event: React.MouseEvent): void {
    event.stopPropagation();
}

export class EntityHistoryDrawer extends AntiHookComponent<Props> {
    private readonly store = new Carburetor<{
        rows: EntityHistoryRow[];
        loading: boolean;
        error: string;
    }>({rows: [], loading: false, error: ''});
    private readonly rows = this.connectSelection(this.store, state => state.rows);
    private request = 0;

    /** Loads history when the drawer opens. */
    public componentDidMount(): void {
        super.componentDidMount();
        document.addEventListener('keydown', this.onKey);
        void this.load();
    }

    /** Reloads history when its entity or endpoint changes. */
    public componentDidUpdate(previous: Readonly<Props>): void {
        super.componentDidUpdate(previous);
        const entityChanged = previous.entityType !== this.props.entityType
            || previous.entityId !== this.props.entityId
            || previous.listUrl !== this.props.listUrl;
        if (entityChanged) void this.load();
    }

    /** Invalidates pending requests and removes the key listener. */
    public componentWillUnmount(): void {
        this.request++;
        document.removeEventListener('keydown', this.onKey);
        super.componentWillUnmount();
    }

    /** Fetches the current entity's history rows. */
    private async load(): Promise<void> {
        const request = ++this.request;
        const {entityType, entityId, listUrl} = this.props;
        this.store.setData({...this.store.getData(), loading: true, error: ''});
        try {
            const res = await sendPost<{entity_type: string; entity_id: string; limit: number}, ListResponse>(
                appUrl(listUrl ?? '/admin/entity-history/~list'),
                {entity_type: entityType, entity_id: String(entityId), limit: 200},
            );
            if (request === this.request) this.store.setData({...this.store.getData(), rows: res.rows ?? []});
        } catch (e: any) {
            if (request === this.request) this.store.setData({...this.store.getData(), error: e?.message || 'Error loading history'});
        } finally {
            if (request === this.request) this.store.setData({...this.store.getData(), loading: false});
        }
    }

    /** Closes the drawer when Escape is pressed. */
    private readonly onKey = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') this.props.onClose();
    };
    /** Closes the drawer when its backdrop is clicked. */
    private readonly onOverlay = (event: React.MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) this.props.onClose();
    };

    /** Renders the entity history drawer. */
    public render() {
        const {entityType, entityId, title, onClose} = this.props;
        const {loading, error} = this.useCarburetor(this.store);
        const rows = this.rows();
        const heading = title ?? `${t.EntityHistory_Title()} — ${entityType} #${entityId}`;
        return <Portal><ScrollLock /><div className="entity-history-overlay" onClick={this.onOverlay} data-test-id="entity-history-modal">
            <div
                role="dialog"
                aria-modal="true"
                aria-label={heading}
                className="entity-history-card"
                onClick={stopPropagation}
            >
                <div className="entity-history-header">
                    <h3 className="entity-history-title">{heading}</h3>
                    <button type="button" className="entity-history-close" onClick={onClose} data-test-id="entity-history-close">&times;</button>
                </div>
                <div className="entity-history-body">
                    {loading && <div className="entity-history-muted">{t.EntityHistory_Loading()}</div>}
                    {error && <div className="entity-history-error">{error}</div>}
                    {!loading && !error && rows.length === 0 && <div className="entity-history-muted" data-test-id="entity-history-empty">{t.EntityHistory_Empty()}</div>}
                    {!loading && rows.length > 0 && <EntityHistoryTable rows={rows} />}
                </div>
            </div>
        </div></Portal>;
    }
}

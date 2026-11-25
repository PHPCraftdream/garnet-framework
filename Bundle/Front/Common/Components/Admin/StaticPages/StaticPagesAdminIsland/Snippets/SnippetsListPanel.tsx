import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {formatTs} from '@common/Utils/Time/DateUtils/format/formatTs';
import {SnippetTabInfo} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';
import {SNIPPET_TYPES} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/values/snippetTypes';
import {SNIPPET_TYPE_LABELS} from
    '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/values/snippetTypeLabels';
import {Snippet, Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';

export interface SnippetsListPanelProps {
    snippets: Snippet[];
    loaded: boolean;
    labels: Labels;
    snippetCreateUrl: string;
    snippetUpdateUrl: string;
    snippetDeleteUrl: string;
    sending: boolean;
    withSending: (fn: () => Promise<void>) => void;
    confirm: (message: string, opts?: {variant?: 'danger'; confirmLabel?: string}) => Promise<boolean>;
    onSnippetCreated: (s: Snippet) => void;
    onSnippetDeleted: (id: number) => void;
    onToggleActive: (s: Snippet) => void;
    onOpenSnippet: (s: Snippet) => void;
    snippetTabs: SnippetTabInfo[];
}

type ListState = {showCreateForm: boolean; newSlug: string; newName: string; newType: string; filterType: string};

export class SnippetsListPanel extends AntiHookComponent<SnippetsListPanelProps> {
    private readonly ui = new Carburetor<ListState>({
        showCreateForm: false, newSlug: '', newName: '', newType: 'block', filterType: '',
    });
    private readonly view = this.connectSelection(
        () => this.ui,
        state => ({
            showCreateForm: state.showCreateForm,
            newSlug: state.newSlug,
            newName: state.newName,
            newType: state.newType,
            filterType: state.filterType,
        }),
    );

    /** Toggles the snippet creation form. */
    private readonly toggleCreate = () => {
        const state = this.ui.getData();
        this.ui.setData({...state, showCreateForm: !state.showCreateForm});
    };
    /** Closes the snippet creation form. */
    private readonly cancelCreate = () => this.ui.setData({...this.ui.getData(), showCreateForm: false});
    /** Updates the draft snippet slug. */
    private readonly changeSlug = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.ui.setData({...this.ui.getData(), newSlug: event.target.value});
    /** Updates the draft snippet name. */
    private readonly changeName = (event: React.ChangeEvent<HTMLInputElement>) =>
        this.ui.setData({...this.ui.getData(), newName: event.target.value});
    /** Updates the draft snippet type. */
    private readonly changeType = (event: React.ChangeEvent<HTMLSelectElement>) =>
        this.ui.setData({...this.ui.getData(), newType: event.target.value});
    /** Toggles the type filter identified by the clicked button. */
    private readonly changeFilter = (event: React.MouseEvent<HTMLButtonElement>) => {
        const type = event.currentTarget.dataset.type ?? '';
        const state = this.ui.getData();
        this.ui.setData({...state, filterType: state.filterType === type ? '' : type});
    };

    /** Validates and submits the new snippet draft. */
    private readonly handleCreate = () => {
        const {newSlug, newName, newType} = this.ui.getData();
        const {withSending, snippetCreateUrl, onSnippetCreated, labels} = this.props;
        if (!newSlug.trim()) return;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean; snippet: Snippet}>(snippetCreateUrl, {
                    slug: newSlug.trim(), name: newName.trim(), snippet_type: newType,
                });
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                onSnippetCreated(res.snippet);
                this.ui.setData({
                    ...this.ui.getData(), newSlug: '', newName: '', newType: 'block', showCreateForm: false,
                });
                showToast('OK', 'success');
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    /** Finds a snippet using the clicked button's data attribute. */
    private snippetFor(event: React.MouseEvent<HTMLButtonElement>): Snippet | undefined {
        const id = Number(event.currentTarget.dataset.snippetId);
        return this.props.snippets.find(snippet => snippet.id === id);
    }
    /** Toggles the active status of the selected snippet. */
    private readonly toggleSnippet = (event: React.MouseEvent<HTMLButtonElement>) => {
        const snippet = this.snippetFor(event);
        if (snippet) this.props.onToggleActive(snippet);
    };
    /** Opens the selected snippet for editing. */
    private readonly openSnippet = (event: React.MouseEvent<HTMLButtonElement>) => {
        const snippet = this.snippetFor(event);
        if (snippet) this.props.onOpenSnippet(snippet);
    };
    /** Starts deletion for the selected snippet. */
    private readonly deleteSnippet = (event: React.MouseEvent<HTMLButtonElement>) => {
        const snippet = this.snippetFor(event);
        if (snippet) void this.handleDelete(snippet);
    };
    /** Confirms and submits deletion of a snippet. */
    private readonly handleDelete = async (snippet: Snippet) => {
        const {confirm, labels, withSending, snippetDeleteUrl, onSnippetDeleted} = this.props;
        const ok = await confirm(labels.snippetsDeleteConfirm, {
            variant: 'danger', confirmLabel: labels.actionDelete,
        });
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(snippetDeleteUrl, {id: snippet.id});
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                onSnippetDeleted(snippet.id);
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    /** Renders an option for a snippet type. */
    private readonly renderTypeOption = (type: string) => (
        <option key={type} value={type}>{(SNIPPET_TYPE_LABELS[type] ?? (() => type))(this.props.labels)}</option>
    );
    /** Renders a button that filters snippets by type. */
    private readonly renderFilter = (type: string) => (
        <button key={type} type="button" className="static-admin-filter"
            data-active={this.ui.getData().filterType === type} data-type={type} onClick={this.changeFilter}>
            {(SNIPPET_TYPE_LABELS[type] ?? (() => type))(this.props.labels)}
        </button>
    );
    /** Renders one snippet row and its actions. */
    private readonly renderRow = (snippet: Snippet) => {
        const {labels, sending, snippetTabs} = this.props;
        const isOpen = snippetTabs.some(tab => tab.snippetId === snippet.id);
        return (
            <tr key={snippet.id} className={isOpen ? 'static-admin-row-open' : undefined}>
                <td className="static-admin-slug-cell">{snippet.slug}</td>
                <td className="static-admin-cell">{snippet.name}</td>
                <td className="static-admin-type-cell">
                    {(SNIPPET_TYPE_LABELS[snippet.snippet_type] ?? (() => snippet.snippet_type))(labels)}
                </td>
                <td className="static-admin-cell">
                    <button type="button" className="static-admin-status" data-published={!!snippet.is_active}
                        data-snippet-id={snippet.id} onClick={this.toggleSnippet} disabled={sending}>
                        {snippet.is_active ? labels.snippetsActive : labels.snippetsInactive}
                    </button>
                </td>
                <td className="static-admin-time-cell">{formatTs(snippet.updated_at)}</td>
                <td className="static-admin-cell"><div className="static-admin-row-actions">
                    <button type="button" className="static-admin-link" data-snippet-id={snippet.id}
                        onClick={this.openSnippet}>{labels.editPage}</button>
                    <button type="button" className="static-admin-delete" data-snippet-id={snippet.id}
                        onClick={this.deleteSnippet} disabled={sending}>{labels.actionDelete}</button>
                </div></td>
            </tr>
        );
    };

    /** Renders the panel using its connected state. */
    public render() {
        return this.renderList(this.view() as ListState);
    }

    /** Renders the filtered snippets list and creation controls. */
    private renderList(state: ListState) {
        const {snippets, loaded, labels, sending} = this.props;
        const {showCreateForm, newSlug, newName, newType, filterType} = state;
        const filtered = filterType ? snippets.filter(snippet => snippet.snippet_type === filterType) : snippets;
        return (
            <div className="static-admin-pages">
                <div className="static-admin-snippets-header">
                    <h1 className="static-admin-snippets-title">{labels.snippets}</h1>
                    <button type="button" className="static-admin-snippets-create" onClick={this.toggleCreate}
                        disabled={sending}>
                        + {labels.snippetsCreateOpen || labels.snippetsCreate}
                    </button>
                </div>
                {showCreateForm && <section className="static-admin-create-panel">
                    <h2 className="static-admin-create-title">{labels.snippetsCreateTitle}</h2>
                    <div className="static-admin-snippets-fields">
                        <label className="static-admin-label"><span className="static-admin-caption">
                            {labels.snippetsSlug}</span>
                            <input className="static-admin-input" value={newSlug} onChange={this.changeSlug} />
                        </label>
                        <label className="static-admin-label"><span className="static-admin-caption">
                            {labels.snippetsName}</span>
                            <input className="static-admin-input" value={newName} onChange={this.changeName} />
                        </label>
                        <label className="static-admin-label"><span className="static-admin-caption">
                            {labels.snippetsType}</span>
                            <select className="static-admin-input" value={newType} onChange={this.changeType}>
                                {SNIPPET_TYPES.map(this.renderTypeOption)}
                            </select>
                        </label>
                    </div>
                    <div className="static-admin-create-actions">
                        <button type="button" className="static-admin-create-submit" onClick={this.handleCreate}
                            disabled={sending || !newSlug.trim()}>{labels.snippetsCreate}</button>
                        <button type="button" className="static-admin-cancel" onClick={this.cancelCreate}>
                            {labels.actionCancel}</button>
                    </div>
                </section>}
                <div className="static-admin-filters">
                    <button type="button" className="static-admin-filter" data-active={!filterType} data-type=""
                        onClick={this.changeFilter}>{labels.snippetsFilterAll}</button>
                    {SNIPPET_TYPES.map(this.renderFilter)}
                </div>
                {loaded && filtered.length === 0 && <div className="static-admin-empty">{labels.snippetsEmpty}</div>}
                {filtered.length > 0 && <div className="static-admin-table-scroll">
                    <table className="static-admin-table">
                    <thead><tr className="static-admin-heading-row">
                        <th className="static-admin-cell">{labels.snippetsSlug}</th>
                        <th className="static-admin-cell">{labels.snippetsName}</th>
                        <th className="static-admin-cell">{labels.snippetsType}</th>
                        <th className="static-admin-cell"></th><th className="static-admin-cell"></th>
                        <th className="static-admin-cell"></th>
                    </tr></thead>
                    <tbody className="static-admin-table-body">{filtered.map(this.renderRow)}</tbody>
                </table></div>}
                <div className="static-admin-usage">{labels.snippetsUsageHint}</div>
            </div>
        );
    }
}

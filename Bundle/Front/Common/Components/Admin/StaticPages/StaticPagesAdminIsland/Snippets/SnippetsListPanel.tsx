/**
 * Список сниппетов с группировкой по типу.
 */
import * as React from 'react';
import {sendPost} from '@common/Api/Send/sendPost';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {EntityHistoryButton} from '@common/Components/Admin/EntityHistory/EntityHistoryButton';
import {TabNav, TabDef} from '@common/Components/Layout/Navigation/TabNav';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {formatTs} from '@common/Utils/Time/DateUtils';
import {markdownToHtml} from '@common/Utils/Ui/markdownToHtml';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {ImageUploadArea} from '@common/Components/Controls/ImageUploadArea';
import {ImageUploadField} from '@common/Components/Controls/ImageUploadField';
import {FileText} from 'lucide-react';
import {SnippetTabInfo, SNIPPET_TYPES, SNIPPET_TYPE_LABELS} from '../types';
import {Snippet, Labels} from '../types';

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


export const SnippetsListPanel: React.FC<SnippetsListPanelProps> = ({
    snippets, loaded, labels,
    snippetCreateUrl, snippetDeleteUrl,
    sending, withSending, confirm,
    onSnippetCreated, onSnippetDeleted, onToggleActive, onOpenSnippet,
    snippetTabs,
}) => {
    const [showCreateForm, setShowCreateForm] = React.useState(false);
    const [newSlug, setNewSlug] = React.useState('');
    const [newName, setNewName] = React.useState('');
    const [newType, setNewType] = React.useState<string>('block');
    const [filterType, setFilterType] = React.useState<string>('');

    const handleCreate = () => {
        if (!newSlug.trim()) return;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean; snippet: Snippet}>(snippetCreateUrl, {
                    slug: newSlug.trim(),
                    name: newName.trim(),
                    snippet_type: newType,
                });
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onSnippetCreated(res.snippet);
                setNewSlug('');
                setNewName('');
                setNewType('block');
                setShowCreateForm(false);
                showToast('OK', 'success');
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const handleDelete = async (s: Snippet) => {
        const ok = await confirm(
            labels.snippetsDeleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(snippetDeleteUrl, {id: s.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onSnippetDeleted(s.id);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const filtered = filterType ? snippets.filter(s => s.snippet_type === filterType) : snippets;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold text-on-surface">{labels.snippets}</h1>
                <button
                    type="button"
                    className="btn btn-primary disabled:opacity-60"
                    onClick={() => setShowCreateForm(!showCreateForm)}
                    disabled={sending}
                >
                    + {labels.snippetsCreateOpen || labels.snippetsCreate}
                </button>
            </div>

            {showCreateForm && (
                <section className="rounded-lg border border-default bg-surface p-5">
                    <h2 className="text-lg font-semibold text-on-surface mb-3">{labels.snippetsCreateTitle}</h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsSlug}</span>
                            <input
                                className="form-control w-full border-default"
                                value={newSlug}
                                onChange={e => setNewSlug(e.target.value)}
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsName}</span>
                            <input
                                className="form-control w-full border-default"
                                value={newName}
                                onChange={e => setNewName(e.target.value)}
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsType}</span>
                            <select
                                className="form-control w-full border-default"
                                value={newType}
                                onChange={e => setNewType(e.target.value)}
                            >
                                {SNIPPET_TYPES.map(t => (
                                    <option key={t} value={t}>{(SNIPPET_TYPE_LABELS[t] ?? (() => t))(labels)}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <div className="mt-4 flex gap-2">
                        <button
                            type="button"
                            className="btn btn-primary disabled:opacity-60"
                            onClick={handleCreate}
                            disabled={sending || !newSlug.trim()}
                        >
                            {labels.snippetsCreate}
                        </button>
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={() => setShowCreateForm(false)}
                        >
                            {labels.actionCancel}
                        </button>
                    </div>
                </section>
            )}

            <div className="flex items-center gap-2 text-sm">
                <button
                    type="button"
                    className={`px-2 py-1 rounded text-xs font-medium ${!filterType ? 'status-active' : 'status-muted'}`}
                    onClick={() => setFilterType('')}
                >
                    {labels.snippetsFilterAll}
                </button>
                {SNIPPET_TYPES.map(t => (
                    <button
                        key={t}
                        type="button"
                        className={`px-2 py-1 rounded text-xs font-medium ${filterType === t ? 'status-active' : 'status-muted'}`}
                        onClick={() => setFilterType(filterType === t ? '' : t)}
                    >
                        {(SNIPPET_TYPE_LABELS[t] ?? (() => t))(labels)}
                    </button>
                ))}
            </div>

            {loaded && filtered.length === 0 && (
                <div className="text-center text-muted py-8">{labels.snippetsEmpty}</div>
            )}
            {filtered.length > 0 && (
                <div className="overflow-x-auto rounded-lg border border-default">
                    <table className="admin-table">
                        <thead>
                            <tr className="border-b border-subtle text-left">
                                <th className="px-4 py-3">{labels.snippetsSlug}</th>
                                <th className="px-4 py-3">{labels.snippetsName}</th>
                                <th className="px-4 py-3">{labels.snippetsType}</th>
                                <th className="px-4 py-3"></th>
                                <th className="px-4 py-3"></th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-subtle bg-surface">
                            {filtered.map(s => {
                                const isOpenInTab = snippetTabs.some(t => t.snippetId === s.id);
                                return (
                                    <tr key={s.id} className={isOpenInTab ? 'bg-accent-subtle' : ''}>
                                        <td className="px-4 py-3 font-mono text-xs">{s.slug}</td>
                                        <td className="px-4 py-3">{s.name}</td>
                                        <td className="px-4 py-3 text-xs">
                                            {(SNIPPET_TYPE_LABELS[s.snippet_type] ?? (() => s.snippet_type))(labels)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <button
                                                type="button"
                                                className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${s.is_active ? 'status-success' : 'status-muted'}`}
                                                onClick={() => onToggleActive(s)}
                                                disabled={sending}
                                            >
                                                {s.is_active ? labels.snippetsActive : labels.snippetsInactive}
                                            </button>
                                        </td>
                                        <td className="px-4 py-3 text-muted text-xs">{formatTs(s.updated_at)}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    className="text-accent hover:text-on-surface text-xs underline"
                                                    onClick={() => onOpenSnippet(s)}
                                                >
                                                    {labels.editPage}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-danger hover:text-on-surface text-xs underline"
                                                    onClick={() => void handleDelete(s)}
                                                    disabled={sending}
                                                >
                                                    {labels.actionDelete}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="text-xs text-muted">
                {labels.snippetsUsageHint}
            </div>
        </div>
    );
};

/**
 * Вкладка одного сниппета: выбор типа и соответствующий редактор.
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
import {SNIPPET_TYPES, SNIPPET_TYPE_LABELS} from '../types';
import {StaticPage, Snippet, Labels} from '../types';
import {HeaderEditor, HeaderData} from './HeaderEditor';
import {FooterEditor, FooterData} from './FooterEditor';

// ── Snippet Editor Tab ──

export interface SnippetEditorTabProps {
    snippetId: number;
    initialSnippet: Snippet | null;
    labels: Labels;
    snippetUpdateUrl: string;
    snippetDeleteUrl: string;
    uploadImageUrl: string;
    deleteImageUrl: string;
    pages: StaticPage[];
    onSnippetUpdated: (snippet: Snippet) => void;
    onSnippetDeleted: (snippetId: number) => void;
}

export const SnippetEditorTab: React.FC<SnippetEditorTabProps> = ({
    snippetId, initialSnippet, labels,
    snippetUpdateUrl, snippetDeleteUrl,
    uploadImageUrl, deleteImageUrl, pages,
    onSnippetUpdated, onSnippetDeleted,
}) => {
    const [snippet, setSnippet] = React.useState<Snippet | null>(initialSnippet ? {...initialSnippet} : null);
    const [localContent, setLocalContent] = React.useState(initialSnippet?.content ?? '');
    const [showPreview, setShowPreview] = React.useState(false);
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);

    const {sending, withSending} = useSending();
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    React.useEffect(() => {
        if (initialSnippet) {
            setSnippet(prev => {
                if (!prev) return {...initialSnippet};
                return prev;
            });
        }
    }, [initialSnippet]);

    const isStructuredType = snippet?.snippet_type === 'header' || snippet?.snippet_type === 'footer';
    const isMarkdownType = !isStructuredType && snippet?.snippet_type !== 'variable';

    const parseStructuredContent = (): any => {
        try { return JSON.parse(localContent) || {}; } catch { return {}; }
    };

    const handleStructuredChange = (data: any) => {
        setLocalContent(JSON.stringify(data));
    };

    const insertMarkdown = (before: string, after: string) => {
        const ta = textareaRef.current;
        if (!ta) return;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = localContent.substring(start, end);
        const replacement = before + (selected || 'text') + after;
        const newContent = localContent.substring(0, start) + replacement + localContent.substring(end);
        setLocalContent(newContent);
        requestAnimationFrame(() => {
            ta.focus();
            const cursorPos = start + before.length + (selected || 'text').length;
            ta.setSelectionRange(cursorPos, cursorPos);
        });
    };

    const handleSave = () => {
        if (!snippet) return;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean}>(snippetUpdateUrl, {
                    id: snippet.id,
                    name: snippet.name,
                    slug: snippet.slug,
                    snippet_type: snippet.snippet_type,
                    content: localContent,
                    is_active: snippet.is_active,
                    sort_order: snippet.sort_order,
                });
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                const updated = {...snippet, content: localContent, updated_at: Math.floor(Date.now() / 1000)};
                setSnippet(updated);
                onSnippetUpdated(updated);
                showToast('OK', 'success');
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const handleDelete = async () => {
        if (!snippet) return;
        const ok = await confirm(
            labels.snippetsDeleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(snippetDeleteUrl, {id: snippet.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onSnippetDeleted(snippet.id);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    if (!snippet) {
        return <div className="text-center text-muted py-8">...</div>;
    }

    const updateField = (field: keyof Snippet, value: string | number) => {
        setSnippet(prev => prev ? {...prev, [field]: value} : prev);
    };

    // When snippet_type changes to a structured type, initialize content if empty/invalid
    const handleTypeChange = (newType: string) => {
        updateField('snippet_type', newType);
        if (newType === 'header') {
            const parsed = (() => { try { return JSON.parse(localContent); } catch { return null; } })();
            if (!parsed || !Array.isArray(parsed?.items)) {
                setLocalContent(JSON.stringify({logo: {url: '', alt: '', link: '/', height: 40}, items: [], layout: 'left', sticky: false}));
            }
        } else if (newType === 'footer') {
            const parsed = (() => { try { return JSON.parse(localContent); } catch { return null; } })();
            if (!parsed || !Array.isArray(parsed?.columns)) {
                setLocalContent(JSON.stringify({columns: [], copyright: '', layout: 'columns'}));
            }
        }
    };

    const renderContentEditor = () => {
        if (snippet.snippet_type === 'header') {
            const data = parseStructuredContent();
            const headerData: HeaderData = {
                logo: data.logo || {url: '', alt: '', link: '/', height: 40},
                items: Array.isArray(data.items) ? data.items : [],
                layout: data.layout || 'left',
                sticky: !!data.sticky,
            };
            return (
                <HeaderEditor
                    data={headerData}
                    onChange={handleStructuredChange}
                    labels={labels}
                    pages={pages}
                    uploadImageUrl={uploadImageUrl}
                    deleteImageUrl={deleteImageUrl}
                />
            );
        }

        if (snippet.snippet_type === 'footer') {
            const data = parseStructuredContent();
            const footerData: FooterData = {
                columns: Array.isArray(data.columns) ? data.columns : [],
                copyright: data.copyright || '',
                layout: data.layout || 'columns',
            };
            return (
                <FooterEditor
                    data={footerData}
                    onChange={handleStructuredChange}
                    labels={labels}
                    pages={pages}
                />
            );
        }

        // Markdown / variable types
        return (
            <>
                {isMarkdownType && (
                    <div className="flex items-center gap-0.5 mb-1 flex-wrap">
                        <button type="button" className="blk-fmt-btn font-bold"
                            onClick={() => insertMarkdown('**', '**')} title="Bold">B</button>
                        <button type="button" className="blk-fmt-btn italic"
                            onClick={() => insertMarkdown('*', '*')} title="Italic">I</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('## ', '')} title="Heading 2">H2</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('### ', '')} title="Heading 3">H3</button>
                        <span className="mx-0.5 h-5 border-l border-subtle" />
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('[', '](url)')} title="Link">Link</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('- ', '')} title="List">List</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('1. ', '')} title="Ordered list">OL</button>
                        <span className="mx-0.5 h-5 border-l border-subtle" />
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('> ', '')} title="Quote">Quote</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('\n---\n', '')} title="Horizontal rule">HR</button>
                        <span className="mx-0.5 h-5 border-l border-subtle" />
                        <div className="blk-seg-toggle">
                            <button type="button"
                                className={`blk-seg-toggle-btn ${!showPreview ? 'blk-seg-toggle-active' : 'blk-seg-toggle-inactive'}`}
                                onClick={() => setShowPreview(false)}>
                                Edit
                            </button>
                            <button type="button"
                                className={`blk-seg-toggle-btn ${showPreview ? 'blk-seg-toggle-active' : 'blk-seg-toggle-inactive'}`}
                                onClick={() => setShowPreview(true)}>
                                Preview
                            </button>
                        </div>
                    </div>
                )}
                {showPreview && isMarkdownType ? (
                    <div className="md-preview"
                         dangerouslySetInnerHTML={{__html: markdownToHtml(localContent)}} />
                ) : (
                    <textarea
                        ref={textareaRef}
                        className="form-control w-full border-default font-mono text-sm"
                        rows={12}
                        value={localContent}
                        onChange={e => setLocalContent(e.target.value)}
                        disabled={sending}
                    />
                )}
            </>
        );
    };

    return (
        <>
            <section className="section-soft rounded-lg border border-default p-5 space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold text-on-surface">
                        {labels.snippetsEditTitle}: {snippet.slug}
                    </h2>
                    <button
                        type="button"
                        className="btn btn-danger btn-sm"
                        onClick={() => void handleDelete()}
                        disabled={sending}
                    >
                        {labels.actionDelete}
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsName}</span>
                        <input
                            className="form-control w-full border-default"
                            value={snippet.name}
                            onChange={e => updateField('name', e.target.value)}
                        />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsSlug}</span>
                        <input
                            className="form-control w-full border-default"
                            value={snippet.slug}
                            onChange={e => updateField('slug', e.target.value)}
                        />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsType}</span>
                        <select
                            className="form-control w-full border-default"
                            value={snippet.snippet_type}
                            onChange={e => handleTypeChange(e.target.value)}
                        >
                            {SNIPPET_TYPES.map(t => (
                                <option key={t} value={t}>{(SNIPPET_TYPE_LABELS[t] ?? (() => t))(labels)}</option>
                            ))}
                        </select>
                    </label>
                </div>

                <div>
                    {renderContentEditor()}
                </div>

                <div className="flex items-center gap-4">
                    <button
                        type="button"
                        className="btn btn-primary btn-lg disabled:opacity-60"
                        onClick={handleSave}
                        disabled={sending}
                    >
                        {labels.savePage}
                    </button>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={!!snippet.is_active}
                            onChange={e => updateField('is_active', e.target.checked ? 1 : 0)}
                        />
                        <span className="text-sm font-medium text-on-surface">{labels.snippetsActive}</span>
                    </label>
                </div>

                {!isStructuredType && (
                    <div className="text-xs text-muted">
                        {labels.snippetsUsageHint}
                    </div>
                )}
            </section>
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>
    );
};

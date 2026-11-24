import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {markdownToHtml} from '@common/Utils/Ui/markdownToHtml';
import {SNIPPET_TYPES, SNIPPET_TYPE_LABELS} from '../types';
import {StaticPage, Snippet, Labels} from '../types';
import {HeaderEditor, HeaderData} from './HeaderEditor';
import {FooterEditor, FooterData} from './FooterEditor';

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

type EditorState = {snippet: Snippet | null; localContent: string; showPreview: boolean};
type EditorProps = SnippetEditorTabProps & ReturnType<typeof useSending> & ReturnType<typeof useConfirm>;

class SnippetEditorView extends AntiHookComponent<EditorProps> {
    private readonly ui = new Carburetor<EditorState>({
        snippet: this.props.initialSnippet ? {...this.props.initialSnippet} : null,
        localContent: this.props.initialSnippet?.content ?? '',
        showPreview: false,
    });
    private readonly view = this.connectSelection(() => this.ui, state => ({snippet: state.snippet, localContent: state.localContent, showPreview: state.showPreview}));
    private readonly textareaRef = React.createRef<HTMLTextAreaElement>();

    public componentDidUpdate(previous: EditorProps) {
        super.componentDidUpdate(previous);
        if (previous.initialSnippet !== this.props.initialSnippet && this.props.initialSnippet && !this.ui.getData().snippet) {
            this.ui.setData({...this.ui.getData(), snippet: {...this.props.initialSnippet}});
        }
    }

    private readonly changeName = (event: React.ChangeEvent<HTMLInputElement>) => this.updateField('name', event.target.value);
    private readonly changeSlug = (event: React.ChangeEvent<HTMLInputElement>) => this.updateField('slug', event.target.value);
    private readonly changeActive = (event: React.ChangeEvent<HTMLInputElement>) => this.updateField('is_active', event.target.checked ? 1 : 0);
    private readonly changeContent = (event: React.ChangeEvent<HTMLTextAreaElement>) => this.ui.setData({...this.ui.getData(), localContent: event.target.value});
    private readonly handleStructuredChange = (data: any) => this.ui.setData({...this.ui.getData(), localContent: JSON.stringify(data)});
    private readonly showEdit = () => this.ui.setData({...this.ui.getData(), showPreview: false});
    private readonly showPreview = () => this.ui.setData({...this.ui.getData(), showPreview: true});

    private updateField(field: keyof Snippet, value: string | number) {
        const state = this.ui.getData();
        if (state.snippet) this.ui.setData({...state, snippet: {...state.snippet, [field]: value}});
    }

    private readonly handleTypeChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        const newType = event.target.value;
        const state = this.ui.getData();
        if (!state.snippet) return;
        let localContent = state.localContent;
        if (newType === 'header' || newType === 'footer') {
            let parsed: any;
            try { parsed = JSON.parse(localContent); } catch { parsed = null; }
            if (newType === 'header' && (!parsed || !Array.isArray(parsed.items))) {
                localContent = JSON.stringify({logo: {url: '', alt: '', link: '/', height: 40}, items: [], layout: 'left', sticky: false});
            } else if (newType === 'footer' && (!parsed || !Array.isArray(parsed.columns))) {
                localContent = JSON.stringify({columns: [], copyright: '', layout: 'columns'});
            }
        }
        this.ui.setData({...state, snippet: {...state.snippet, snippet_type: newType}, localContent});
    };

    private readonly insertMarkdown = (event: React.MouseEvent<HTMLButtonElement>) => {
        const ta = this.textareaRef.current;
        if (!ta) return;
        const before = event.currentTarget.dataset.before ?? '';
        const after = event.currentTarget.dataset.after ?? '';
        const state = this.ui.getData();
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = state.localContent.substring(start, end);
        const replacement = before + (selected || 'text') + after;
        const localContent = state.localContent.substring(0, start) + replacement + state.localContent.substring(end);
        this.ui.setData({...state, localContent});
        requestAnimationFrame(() => {
            ta.focus();
            const cursorPos = start + before.length + (selected || 'text').length;
            ta.setSelectionRange(cursorPos, cursorPos);
        });
    };

    private readonly handleSave = () => {
        const state = this.ui.getData();
        if (!state.snippet) return;
        const snippet = {...state.snippet};
        const localContent = state.localContent;
        const {withSending, snippetUpdateUrl, onSnippetUpdated, labels} = this.props;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean}>(snippetUpdateUrl, {
                    id: snippet.id, name: snippet.name, slug: snippet.slug,
                    snippet_type: snippet.snippet_type, content: localContent,
                    is_active: snippet.is_active, sort_order: snippet.sort_order,
                });
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                const updated = {...snippet, content: localContent, updated_at: Math.floor(Date.now() / 1000)};
                this.ui.setData({...this.ui.getData(), snippet: updated});
                onSnippetUpdated(updated);
                showToast('OK', 'success');
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    private readonly handleDelete = async () => {
        const current = this.ui.getData().snippet;
        if (!current) return;
        const snippet = {...current};
        const {confirm, labels, withSending, snippetDeleteUrl, onSnippetDeleted} = this.props;
        const ok = await confirm(labels.snippetsDeleteConfirm, {variant: 'danger', confirmLabel: labels.actionDelete});
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(snippetDeleteUrl, {id: snippet.id});
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                onSnippetDeleted(snippet.id);
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    private readonly renderTypeOption = (type: string) => (
        <option key={type} value={type}>{(SNIPPET_TYPE_LABELS[type] ?? (() => type))(this.props.labels)}</option>
    );

    public render() {
        return this.renderEditor(this.view() as EditorState);
    }

    private renderContentEditor(state: EditorState, snippet: Snippet) {
        const {labels, pages, uploadImageUrl, deleteImageUrl, sending} = this.props;
        const {localContent, showPreview} = state;
        if (snippet.snippet_type === 'header' || snippet.snippet_type === 'footer') {
            let data: any;
            try { data = JSON.parse(localContent) || {}; } catch { data = {}; }
            if (snippet.snippet_type === 'header') {
                const headerData: HeaderData = {logo: data.logo || {url: '', alt: '', link: '/', height: 40}, items: Array.isArray(data.items) ? data.items : [], layout: data.layout || 'left', sticky: !!data.sticky};
                return <HeaderEditor data={headerData} onChange={this.handleStructuredChange} labels={labels} pages={pages} uploadImageUrl={uploadImageUrl} deleteImageUrl={deleteImageUrl} />;
            }
            const footerData: FooterData = {columns: Array.isArray(data.columns) ? data.columns : [], copyright: data.copyright || '', layout: data.layout || 'columns'};
            return <FooterEditor data={footerData} onChange={this.handleStructuredChange} labels={labels} pages={pages} />;
        }
        const isMarkdownType = snippet.snippet_type !== 'variable';
        return <>
            {isMarkdownType && <div className="snippet-format-bar">
                <button type="button" className="snippet-format-bold" data-before="**" data-after="**" onClick={this.insertMarkdown} title="Bold">B</button>
                <button type="button" className="snippet-format-italic" data-before="*" data-after="*" onClick={this.insertMarkdown} title="Italic">I</button>
                <button type="button" className="snippet-format-button" data-before="## " onClick={this.insertMarkdown} title="Heading 2">H2</button>
                <button type="button" className="snippet-format-button" data-before="### " onClick={this.insertMarkdown} title="Heading 3">H3</button>
                <span className="snippet-format-divider" />
                <button type="button" className="snippet-format-button" data-before="[" data-after="](url)" onClick={this.insertMarkdown} title="Link">Link</button>
                <button type="button" className="snippet-format-button" data-before="- " onClick={this.insertMarkdown} title="List">List</button>
                <button type="button" className="snippet-format-button" data-before="1. " onClick={this.insertMarkdown} title="Ordered list">OL</button>
                <span className="snippet-format-divider" />
                <button type="button" className="snippet-format-button" data-before="> " onClick={this.insertMarkdown} title="Quote">Quote</button>
                <button type="button" className="snippet-format-button" data-before={'\n---\n'} onClick={this.insertMarkdown} title="Horizontal rule">HR</button>
                <span className="snippet-format-divider" />
                <div className="snippet-mode">
                    <button type="button" className="snippet-mode-button" aria-pressed={!showPreview} onClick={this.showEdit}>Edit</button>
                    <button type="button" className="snippet-mode-button" aria-pressed={showPreview} onClick={this.showPreview}>Preview</button>
                </div>
            </div>}
            {showPreview && isMarkdownType ? <div className="md-preview" dangerouslySetInnerHTML={{__html: markdownToHtml(localContent)}} /> :
                <textarea ref={this.textareaRef} className="snippet-content" rows={12} value={localContent} onChange={this.changeContent} disabled={sending} />}
        </>;
    }

    private renderEditor(state: EditorState) {
        const {snippet} = state;
        const {labels, sending, confirmState, handleConfirm, handleCancel} = this.props;
        if (!snippet) return <div className="static-admin-empty">...</div>;
        const isStructuredType = snippet.snippet_type === 'header' || snippet.snippet_type === 'footer';
        return <>
            <section className="snippet-editor">
                <div className="static-admin-toolbar">
                    <h2 className="static-admin-create-title">{labels.snippetsEditTitle}: {snippet.slug}</h2>
                    <button type="button" className="snippet-delete" onClick={this.handleDelete} disabled={sending}>{labels.actionDelete}</button>
                </div>
                <div className="static-admin-snippets-fields">
                    <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsName}</span>
                        <input className="static-admin-input" value={snippet.name} onChange={this.changeName} />
                    </label>
                    <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsSlug}</span>
                        <input className="static-admin-input" value={snippet.slug} onChange={this.changeSlug} />
                    </label>
                    <label className="static-admin-label"><span className="static-admin-caption">{labels.snippetsType}</span>
                        <select className="static-admin-input" value={snippet.snippet_type} onChange={this.handleTypeChange}>{SNIPPET_TYPES.map(this.renderTypeOption)}</select>
                    </label>
                </div>
                <div>{this.renderContentEditor(state, snippet)}</div>
                <div className="snippet-save-row">
                    <button type="button" className="snippet-save" onClick={this.handleSave} disabled={sending}>{labels.savePage}</button>
                    <label className="snippet-active-label">
                        <input type="checkbox" checked={!!snippet.is_active} onChange={this.changeActive} />
                        <span className="snippet-active-caption">{labels.snippetsActive}</span>
                    </label>
                </div>
                {!isStructuredType && <div className="static-admin-usage">{labels.snippetsUsageHint}</div>}
            </section>
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>;
    }
}

export const SnippetEditorTab: React.FC<SnippetEditorTabProps> = props => {
    const sending = useSending();
    const confirmation = useConfirm();
    return <SnippetEditorView {...props} {...sending} {...confirmation} />;
};

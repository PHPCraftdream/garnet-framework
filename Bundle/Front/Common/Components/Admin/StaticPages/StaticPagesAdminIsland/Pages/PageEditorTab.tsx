import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {StaticPage, Snippet, PageBlock, Labels} from '../types';
import {PageEditorView} from './PageEditorView';

export interface PageEditorTabProps {
    pageId: number;
    initialPage: StaticPage | null;
    labels: Labels;
    updateUrl: string;
    deleteUrl: string;
    blocksUrl: string;
    saveBlocksUrl: string;
    uploadImageUrl: string;
    deleteImageUrl: string;
    headerFooterSnippetsUrl: string;
    publicBaseUrl: string;
    templateVariables: string[];
    pages: StaticPage[];
    onPageUpdated: (page: StaticPage) => void;
    onPageDeleted: (pageId: number) => void;
}

function orderBlocks(blocks: PageBlock[]): PageBlock[] {
    const ordered: PageBlock[] = [];
    for (const [sort_order, block] of blocks.entries()) {
        ordered.push(block.sort_order === sort_order ? block : {...block, sort_order});
    }
    return ordered;
}

type EditorState = {
    editingPage: StaticPage | null;
    localBlocks: PageBlock[];
    nextTempId: number;
    blocksDirty: boolean;
    headerSnippetOptions: Snippet[];
    footerSnippetOptions: Snippet[];
    addingAtPosition: number | null;
};
type EditorProps = PageEditorTabProps & ReturnType<typeof useSending> & ReturnType<typeof useConfirm>;

class PageEditorTabView extends AntiHookComponent<EditorProps> {
    private readonly ui = new Carburetor<EditorState>({
        editingPage: this.props.initialPage ? {...this.props.initialPage} : null,
        localBlocks: [], nextTempId: -1, blocksDirty: false,
        headerSnippetOptions: [], footerSnippetOptions: [], addingAtPosition: null,
    });
    private readonly view = this.connectSelection(() => this.ui, state => ({editingPage: state.editingPage, localBlocks: state.localBlocks, nextTempId: state.nextTempId, blocksDirty: state.blocksDirty, headerSnippetOptions: state.headerSnippetOptions, footerSnippetOptions: state.footerSnippetOptions, addingAtPosition: state.addingAtPosition}));

    public componentDidMount() {
        super.componentDidMount();
        void this.loadBlocks();
        void this.loadHeaderFooterSnippets();
    }

    public componentDidUpdate(previous: EditorProps) {
        super.componentDidUpdate(previous);
        if (previous.initialPage !== this.props.initialPage && this.props.initialPage && !this.ui.getData().editingPage) {
            this.ui.setData({...this.ui.getData(), editingPage: {...this.props.initialPage}});
        }
        if (previous.pageId !== this.props.pageId) {
            void this.loadBlocks();
            void this.loadHeaderFooterSnippets();
        }
    }

    private readonly loadHeaderFooterSnippets = async () => {
        try {
            const res = await sendPost<{}, {headers: Snippet[]; footers: Snippet[]}>(this.props.headerFooterSnippetsUrl, {});
            if ((res as any)?.error) return;
            this.ui.setData({...this.ui.getData(), headerSnippetOptions: res.headers ?? [], footerSnippetOptions: res.footers ?? []});
        } catch {
            // silent
        }
    };

    private readonly loadBlocks = async () => {
        try {
            const res = await sendPost<{page_id: number}, {blocks: PageBlock[]}>(this.props.blocksUrl, {page_id: this.props.pageId});
            if ((res as any)?.error) return;
            this.ui.setData({...this.ui.getData(), localBlocks: res.blocks ?? [], blocksDirty: false});
        } catch {
            // silent
        }
    };

    private readonly setAddingAtPosition = (addingAtPosition: number | null) => {
        this.ui.setData({...this.ui.getData(), addingAtPosition});
    };

    private readonly setEditingPage = (editingPage: StaticPage) => {
        this.ui.setData({...this.ui.getData(), editingPage});
    };

    private readonly addBlockAtPosition = (position: number, blockType: string) => {
        const state = this.ui.getData();
        if (!state.editingPage) return;
        const newBlock: PageBlock = {
            id: state.nextTempId, page_id: state.editingPage.id, block_type: blockType,
            content: blockType === 'gallery' ? '{"images":[],"lightbox":true,"rows":2}' : '',
            sort_order: position, is_hidden: 0, visibility: 'all', created_at: 0,
        };
        const blocks = [...state.localBlocks];
        blocks.splice(position, 0, newBlock);
        this.ui.setData({...state, nextTempId: state.nextTempId - 1,
            localBlocks: orderBlocks(blocks),
            blocksDirty: true, addingAtPosition: null});
    };

    private readonly handleUpdateBlock = (block: PageBlock, fields: Partial<PageBlock>) => {
        const state = this.ui.getData();
        const localBlocks = [...state.localBlocks];
        const index = localBlocks.findIndex(current => current.id === block.id);
        if (index < 0) return;
        localBlocks[index] = {...localBlocks[index], ...fields};
        this.ui.setData({...state, localBlocks, blocksDirty: true});
    };

    private readonly handleDeleteBlock = async (block: PageBlock) => {
        const {confirm, labels} = this.props;
        const ok = await confirm(labels.deleteBlockConfirm, {variant: 'danger', confirmLabel: labels.actionDelete});
        if (!ok) return;
        const state = this.ui.getData();
        const localBlocks = orderBlocks(state.localBlocks.filter(b => b.id !== block.id));
        this.ui.setData({...state, localBlocks, blocksDirty: true});
    };

    private readonly handleMoveBlock = (index: number, direction: -1 | 1) => {
        const state = this.ui.getData();
        const blocks = [...state.localBlocks];
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= blocks.length) return;
        [blocks[index], blocks[targetIndex]] = [blocks[targetIndex], blocks[index]];
        this.ui.setData({...state, localBlocks: orderBlocks(blocks), blocksDirty: true});
    };

    private readonly handleToggleBlockVisibility = (block: PageBlock) => {
        this.handleUpdateBlock(block, {is_hidden: block.is_hidden ? 0 : 1});
    };

    private readonly handleSave = () => {
        const {editingPage, localBlocks} = this.ui.getData();
        if (!editingPage) return;
        const {withSending, updateUrl, saveBlocksUrl, onPageUpdated, labels} = this.props;
        void withSending(async () => {
            try {
                const pageRes = await sendPost<any, {success: boolean}>(updateUrl, {
                    id: editingPage.id, title: editingPage.title, slug: editingPage.slug,
                    meta_description: editingPage.meta_description, seo_title: editingPage.seo_title ?? '',
                    og_image: editingPage.og_image ?? '', is_published: editingPage.is_published,
                    sort_order: editingPage.sort_order, max_width: editingPage.max_width,
                    visibility: editingPage.visibility, header_snippet_id: editingPage.header_snippet_id ?? '',
                    footer_snippet_id: editingPage.footer_snippet_id ?? '',
                });
                if ((pageRes as any)?.error) { showToast((pageRes as any).error, 'danger'); return; }
                const blocksRes = await sendPost<any, {success: boolean; blocks: PageBlock[]}>(saveBlocksUrl, {
                    page_id: editingPage.id, blocks: JSON.stringify(localBlocks),
                });
                if ((blocksRes as any)?.error) { showToast((blocksRes as any).error, 'danger'); return; }
                const state = this.ui.getData();
                this.ui.setData({...state, localBlocks: blocksRes.blocks || state.localBlocks, blocksDirty: false});
                onPageUpdated(editingPage);
                showToast('OK', 'success');
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    private readonly handleDeletePage = async () => {
        const editingPage = this.ui.getData().editingPage;
        if (!editingPage) return;
        const {confirm, labels, withSending, deleteUrl, onPageDeleted} = this.props;
        const ok = await confirm(labels.deleteConfirm, {variant: 'danger', confirmLabel: labels.actionDelete});
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(deleteUrl, {id: editingPage.id});
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                onPageDeleted(editingPage.id);
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    private readonly deletePage = () => { void this.handleDeletePage(); };

    public render() {
        const {editingPage, localBlocks, blocksDirty, headerSnippetOptions, footerSnippetOptions, addingAtPosition} = this.view() as EditorState;
        const {templateVariables, labels, sending, uploadImageUrl, deleteImageUrl, publicBaseUrl, pages,
            confirmState, handleConfirm, handleCancel} = this.props;
        if (!editingPage) return <div className="static-admin-empty">...</div>;
        return <>
            <PageEditorView page={editingPage} blocks={localBlocks} templateVariables={templateVariables}
                labels={labels} sending={sending} blocksDirty={blocksDirty} uploadImageUrl={uploadImageUrl}
                deleteImageUrl={deleteImageUrl} publicBaseUrl={publicBaseUrl}
                headerSnippetOptions={headerSnippetOptions} footerSnippetOptions={footerSnippetOptions} pages={pages}
                addingAtPosition={addingAtPosition} onSetAddingAtPosition={this.setAddingAtPosition}
                onPageChange={this.setEditingPage} onSave={this.handleSave} onDelete={this.deletePage}
                onAddBlock={this.addBlockAtPosition} onUpdateBlock={this.handleUpdateBlock}
                onDeleteBlock={this.handleDeleteBlock} onMoveBlock={this.handleMoveBlock}
                onToggleBlockVisibility={this.handleToggleBlockVisibility} />
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>;
    }
}

export const PageEditorTab: React.FC<PageEditorTabProps> = props => {
    const sending = useSending();
    const confirmation = useConfirm();
    return <PageEditorTabView {...props} {...sending} {...confirmation} />;
};

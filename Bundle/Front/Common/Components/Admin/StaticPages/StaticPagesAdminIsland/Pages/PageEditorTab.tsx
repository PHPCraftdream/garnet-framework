/**
 * Вкладка одной страницы: заголовок, свойства, сохранение.
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
import {ImageUploadArea, ImageUploadField} from '@common/Components/Controls/ImageUploadField';
import {FileText} from 'lucide-react';
import {StaticPage, Snippet, PageBlock, Labels} from '../types';
import {PageEditor} from './PageEditor';

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

export const PageEditorTab: React.FC<PageEditorTabProps> = ({
    pageId, initialPage, labels,
    updateUrl, deleteUrl, blocksUrl, saveBlocksUrl,
    uploadImageUrl, deleteImageUrl,
    headerFooterSnippetsUrl,
    publicBaseUrl, templateVariables, pages,
    onPageUpdated, onPageDeleted,
}) => {
    const [editingPage, setEditingPage] = React.useState<StaticPage | null>(initialPage ? {...initialPage} : null);
    const [localBlocks, setLocalBlocks] = React.useState<PageBlock[]>([]);
    const [nextTempId, setNextTempId] = React.useState(-1);
    const [blocksDirty, setBlocksDirty] = React.useState(false);
    const [headerSnippetOptions, setHeaderSnippetOptions] = React.useState<Snippet[]>([]);
    const [footerSnippetOptions, setFooterSnippetOptions] = React.useState<Snippet[]>([]);

    const {sending, withSending} = useSending();
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    // Sync initialPage when parent updates it (e.g. publish toggle from list)
    React.useEffect(() => {
        if (initialPage) {
            setEditingPage(prev => {
                if (!prev) return {...initialPage};
                // Only sync fields that are not user-editable locally (updated_at, is_published from list toggle)
                // Avoid overwriting in-progress edits
                return prev;
            });
        }
    }, [initialPage]);

    React.useEffect(() => {
        void loadBlocks();
        void loadHeaderFooterSnippets();
    }, [pageId]);

    const loadHeaderFooterSnippets = async () => {
        try {
            const res = await sendPost<{}, {headers: Snippet[]; footers: Snippet[]}>(headerFooterSnippetsUrl, {});
            if ((res as any)?.error) return;
            setHeaderSnippetOptions(res.headers ?? []);
            setFooterSnippetOptions(res.footers ?? []);
        } catch {
            // silent
        }
    };

    const loadBlocks = async () => {
        try {
            const res = await sendPost<{page_id: number}, {blocks: PageBlock[]}>(blocksUrl, {page_id: pageId});
            if ((res as any)?.error) return;
            setLocalBlocks(res.blocks ?? []);
            setBlocksDirty(false);
        } catch {
            // silent
        }
    };

    // ── Type picker state for "+" buttons ──
    const [addingAtPosition, setAddingAtPosition] = React.useState<number | null>(null);

    const addBlockAtPosition = (position: number, blockType: string) => {
        if (!editingPage) return;
        const newBlock: PageBlock = {
            id: nextTempId,
            page_id: editingPage.id,
            block_type: blockType,
            content: blockType === 'gallery' ? '{"images":[],"lightbox":true,"rows":2}' : '',
            sort_order: position,
            is_hidden: 0,
            visibility: 'all',
            created_at: 0,
        };
        setNextTempId(prev => prev - 1);

        const updated = [...localBlocks];
        updated.splice(position, 0, newBlock);
        // Reindex sort_order
        updated.forEach((b, i) => b.sort_order = i);
        setLocalBlocks(updated);
        setBlocksDirty(true);
        setAddingAtPosition(null);
    };

    // ── Local block operations ──

    const handleUpdateBlock = (block: PageBlock, fields: Partial<PageBlock>) => {
        setLocalBlocks(prev => prev.map(b => b.id === block.id ? {...b, ...fields} : b));
        setBlocksDirty(true);
    };

    const handleDeleteBlock = async (block: PageBlock) => {
        const ok = await confirm(
            labels.deleteBlockConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        setLocalBlocks(prev => {
            const filtered = prev.filter(b => b.id !== block.id);
            filtered.forEach((b, i) => b.sort_order = i);
            return filtered;
        });
        setBlocksDirty(true);
    };

    const handleMoveBlock = (index: number, direction: -1 | 1) => {
        const newBlocks = [...localBlocks];
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= newBlocks.length) return;
        [newBlocks[index], newBlocks[targetIndex]] = [newBlocks[targetIndex], newBlocks[index]];
        newBlocks.forEach((b, i) => b.sort_order = i);
        setLocalBlocks(newBlocks);
        setBlocksDirty(true);
    };

    const handleToggleBlockVisibility = (block: PageBlock) => {
        handleUpdateBlock(block, {is_hidden: block.is_hidden ? 0 : 1});
    };

    // ── Save: page metadata + all blocks ──

    const handleSave = () => {
        if (!editingPage) return;
        void withSending(async () => {
            try {
                // Save page fields
                const pageRes = await sendPost<any, {success: boolean}>(updateUrl, {
                    id: editingPage.id,
                    title: editingPage.title,
                    slug: editingPage.slug,
                    meta_description: editingPage.meta_description,
                    seo_title: editingPage.seo_title ?? '',
                    og_image: editingPage.og_image ?? '',
                    is_published: editingPage.is_published,
                    sort_order: editingPage.sort_order,
                    max_width: editingPage.max_width,
                    visibility: editingPage.visibility,
                    header_snippet_id: editingPage.header_snippet_id ?? '',
                    footer_snippet_id: editingPage.footer_snippet_id ?? '',
                });
                if ((pageRes as any)?.error) {
                    showToast((pageRes as any).error, 'danger');
                    return;
                }

                // Save blocks
                const blocksRes = await sendPost<any, {success: boolean; blocks: PageBlock[]}>(saveBlocksUrl, {
                    page_id: editingPage.id,
                    blocks: JSON.stringify(localBlocks),
                });
                if ((blocksRes as any)?.error) {
                    showToast((blocksRes as any).error, 'danger');
                    return;
                }
                if (blocksRes.blocks) {
                    setLocalBlocks(blocksRes.blocks);
                }
                setBlocksDirty(false);

                onPageUpdated(editingPage);
                showToast('OK', 'success');
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const handleDeletePage = async () => {
        if (!editingPage) return;
        const ok = await confirm(
            labels.deleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(deleteUrl, {id: editingPage.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onPageDeleted(editingPage.id);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    if (!editingPage) {
        return <div className="text-center text-muted py-8">...</div>;
    }

    return (
        <>
            <PageEditor
                page={editingPage}
                blocks={localBlocks}
                templateVariables={templateVariables}
                labels={labels}
                sending={sending}
                blocksDirty={blocksDirty}
                uploadImageUrl={uploadImageUrl}
                deleteImageUrl={deleteImageUrl}
                publicBaseUrl={publicBaseUrl}
                headerSnippetOptions={headerSnippetOptions}
                footerSnippetOptions={footerSnippetOptions}
                pages={pages}
                addingAtPosition={addingAtPosition}
                onSetAddingAtPosition={setAddingAtPosition}
                onPageChange={setEditingPage}
                onSave={handleSave}
                onDelete={() => void handleDeletePage()}
                onAddBlock={addBlockAtPosition}
                onUpdateBlock={handleUpdateBlock}
                onDeleteBlock={handleDeleteBlock}
                onMoveBlock={handleMoveBlock}
                onToggleBlockVisibility={handleToggleBlockVisibility}
            />
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>
    );
};

/**
 * Редактор страницы: список блоков и операции над ними.
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
import {BlockEditor} from './BlockEditor';

export interface PageEditorProps {
    page: StaticPage;
    blocks: PageBlock[];
    templateVariables: string[];
    labels: Labels;
    sending: boolean;
    blocksDirty: boolean;
    uploadImageUrl: string;
    deleteImageUrl: string;
    publicBaseUrl: string;
    headerSnippetOptions: Snippet[];
    footerSnippetOptions: Snippet[];
    pages: StaticPage[];
    addingAtPosition: number | null;
    onSetAddingAtPosition: (pos: number | null) => void;
    onPageChange: (page: StaticPage) => void;
    onSave: () => void;
    onDelete: () => void;
    onAddBlock: (position: number, blockType: string) => void;
    onUpdateBlock: (block: PageBlock, fields: Partial<PageBlock>) => void;
    onDeleteBlock: (block: PageBlock) => void;
    onMoveBlock: (index: number, direction: -1 | 1) => void;
    onToggleBlockVisibility: (block: PageBlock) => void;
}


export const PageEditor: React.FC<PageEditorProps> = ({
    page, blocks, templateVariables, labels, sending, blocksDirty,
    uploadImageUrl, deleteImageUrl, publicBaseUrl,
    headerSnippetOptions, footerSnippetOptions, pages,
    addingAtPosition, onSetAddingAtPosition,
    onPageChange, onSave, onDelete,
    onAddBlock, onUpdateBlock, onDeleteBlock, onMoveBlock, onToggleBlockVisibility,
}) => {
    const update = (field: keyof StaticPage, value: string | number) => {
        onPageChange({...page, [field]: value});
    };

    const renderAddSeparator = (position: number) => {
        if (addingAtPosition === position) {
            return (
                <div className="flex justify-center gap-2 my-2">
                    <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => onAddBlock(position, 'text')}
                    >
                        {labels.blockTypeText}
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => onAddBlock(position, 'gallery')}
                    >
                        {labels.blockTypeGallery}
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => onSetAddingAtPosition(null)}
                    >
                        {labels.actionCancel}
                    </button>
                </div>
            );
        }
        return (
            <div className="blk-add-separator my-1">
                <button
                    type="button"
                    className="blk-add-btn"
                    onClick={() => onSetAddingAtPosition(position)}
                    title={labels.addBlock}
                >
                    +
                </button>
            </div>
        );
    };

    return (
        <section className="section-soft rounded-lg border border-default p-5 space-y-5">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h2 className="text-lg font-semibold text-on-surface">
                        {labels.editPage}: {page.slug}
                    </h2>
                    <a
                        href={publicBaseUrl + page.slug}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-accent text-sm hover:underline"
                    >
                        {labels.openPage} &#8599;
                    </a>
                </div>
                <button
                    type="button"
                    className="btn btn-danger btn-sm"
                    onClick={onDelete}
                    disabled={sending}
                >
                    {labels.actionDelete}
                </button>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.pageTitle}</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.title}
                        onChange={e => update('title', e.target.value)}
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.slug}</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.slug}
                        onChange={e => update('slug', e.target.value)}
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.metaDescription}</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.meta_description}
                        onChange={e => update('meta_description', e.target.value)}
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">SEO title</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.seo_title ?? ''}
                        placeholder={page.title}
                        onChange={e => update('seo_title', e.target.value)}
                    />
                </label>
                <div className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">OG image (social preview)</span>
                    <span className="mb-2 block text-xs text-secondary">preview image for Telegram/WhatsApp; if empty, the default image is used</span>
                    <ImageUploadField
                        value={page.og_image ?? ''}
                        onChange={url => update('og_image', url)}
                        uploadUrl={uploadImageUrl}
                        deleteUrl={deleteImageUrl}
                        uploadLabel={labels.uploadImage}
                        removeLabel={labels.removeImage}
                        removeConfirm={labels.removeImage}
                        errorLabel={labels.error}
                        previewAlt={page.title}
                        disabled={sending}
                    />
                </div>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.maxWidth}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.max_width || '3xl'}
                        onChange={e => update('max_width', e.target.value)}
                    >
                        <option value="xl">xl — 576px</option>
                        <option value="2xl">2xl — 672px</option>
                        <option value="3xl">3xl — 768px</option>
                        <option value="4xl">4xl — 896px</option>
                        <option value="5xl">5xl — 1024px</option>
                        <option value="6xl">6xl — 1152px</option>
                        <option value="7xl">7xl — 1280px</option>
                        <option value="full">full — 100%</option>
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.visibility}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.visibility || 'all'}
                        onChange={e => update('visibility', e.target.value)}
                    >
                        <option value="all">{labels.visibilityAll}</option>
                        <option value="guest">{labels.visibilityGuest}</option>
                        <option value="auth">{labels.visibilityAuth}</option>
                        <option value="moderator">{labels.visibilityModerator}</option>
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.headerSnippet}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.header_snippet_id ?? ''}
                        onChange={e => {
                            const val = e.target.value;
                            onPageChange({...page, header_snippet_id: val ? Number(val) : null});
                        }}
                    >
                        <option value="">{labels.noSnippet}</option>
                        {headerSnippetOptions.map(s => (
                            <option key={s.id} value={s.id}>{s.name || s.slug}</option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.footerSnippet}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.footer_snippet_id ?? ''}
                        onChange={e => {
                            const val = e.target.value;
                            onPageChange({...page, footer_snippet_id: val ? Number(val) : null});
                        }}
                    >
                        <option value="">{labels.noSnippet}</option>
                        {footerSnippetOptions.map(s => (
                            <option key={s.id} value={s.id}>{s.name || s.slug}</option>
                        ))}
                    </select>
                </label>
            </div>

            <div>
                <h3 className="text-base font-semibold text-on-surface mb-3">{labels.blocks}</h3>

                {renderAddSeparator(0)}

                {blocks.map((block, idx) => (
                    <React.Fragment key={block.id}>
                        <BlockEditor
                            block={block}
                            index={idx}
                            total={blocks.length}
                            labels={labels}
                            disabled={sending}
                            uploadImageUrl={uploadImageUrl}
                            deleteImageUrl={deleteImageUrl}
                            pages={pages}
                            onUpdate={onUpdateBlock}
                            onDelete={onDeleteBlock}
                            onMove={onMoveBlock}
                            onToggleVisibility={onToggleBlockVisibility}
                        />
                        {renderAddSeparator(idx + 1)}
                    </React.Fragment>
                ))}

                {blocks.length === 0 && (
                    <div className="text-center text-muted py-4 text-sm">{labels.empty}</div>
                )}
            </div>

            {templateVariables.length > 0 && (
                <div className="text-xs text-muted">
                    {labels.variables}
                </div>
            )}

            <div className="flex items-center gap-4 pt-4 border-t border-subtle">
                <button
                    type="button"
                    className="btn btn-primary btn-lg disabled:opacity-60"
                    onClick={onSave}
                    disabled={sending}
                >
                    {labels.savePage}
                </button>

                {blocksDirty && (
                    <span className="text-sm text-warning">&#9679;</span>
                )}

                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={!!page.is_published}
                        onChange={e => update('is_published', e.target.checked ? 1 : 0)}
                    />
                    <span className="text-sm font-medium text-on-surface">{labels.published}</span>
                </label>
            </div>
        </section>
    );
};

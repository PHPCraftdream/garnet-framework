/**
 * Редактор одного блока: текст, изображение, галерея, ссылки.
 */
import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {sendPostFormData} from '@common/Api/sendPostFormData';
import {showToast} from '@common/Components/GlobalToast';
import {ConfirmModal} from '@common/Components/ConfirmModal';
import {EntityHistoryButton} from '@common/Components/EntityHistory/EntityHistoryButton';
import {TabNav, TabDef} from '@common/Components/Navigation/TabNav';
import {useSending} from '@common/hooks/useSending';
import {useConfirm} from '@common/hooks/useConfirm';
import {formatTs} from '@common/Utils/DateUtils';
import {markdownToHtml} from '@common/Utils/markdownToHtml';
import {PageHeader} from '@common/Components/PageHeader';
import {ImageUploadArea, ImageUploadField} from '@common/Components/ImageUploadField';
import {FileText} from 'lucide-react';
import {BLOCK_TYPE_LABELS} from '../types';
import {StaticPage, PageBlock, Labels} from '../types';

export interface BlockEditorProps {
    block: PageBlock;
    index: number;
    total: number;
    labels: Labels;
    disabled: boolean;
    uploadImageUrl: string;
    deleteImageUrl: string;
    pages?: StaticPage[];
    onUpdate: (block: PageBlock, fields: Partial<PageBlock>) => void;
    onDelete: (block: PageBlock) => void;
    onMove: (index: number, direction: -1 | 1) => void;
    onToggleVisibility: (block: PageBlock) => void;
}

// ── Block Editor ──

export const BlockEditor: React.FC<BlockEditorProps> =({block, index, total, labels, disabled, uploadImageUrl, deleteImageUrl, pages, onUpdate, onDelete, onMove, onToggleVisibility}) => {
    const [localContent, setLocalContent] = React.useState(block.content);
    const [showPreview, setShowPreview] = React.useState(false);
    const [showPagePicker, setShowPagePicker] = React.useState(false);
    const [imgUploading, setImgUploading] = React.useState(false);
    const [pendingDeleteImg, setPendingDeleteImg] = React.useState<{url: string; action: () => Promise<void>} | null>(null);
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);

    React.useEffect(() => {
        if (!showPagePicker) return;
        const close = () => setShowPagePicker(false);
        document.addEventListener('click', close);
        return () => document.removeEventListener('click', close);
    }, [showPagePicker]);

    React.useEffect(() => {
        setLocalContent(block.content);
    }, [block.content]);

    // Propagate local text changes to parent state
    const propagateContent = (content: string) => {
        setLocalContent(content);
        onUpdate(block, {content});
    };

    const insertMarkdown = (before: string, after: string) => {
        const ta = textareaRef.current;
        if (!ta) return;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = localContent.substring(start, end);
        const replacement = before + (selected || 'text') + after;
        const newContent = localContent.substring(0, start) + replacement + localContent.substring(end);
        propagateContent(newContent);
        requestAnimationFrame(() => {
            ta.focus();
            const cursorPos = start + before.length + (selected || 'text').length;
            ta.setSelectionRange(cursorPos, cursorPos);
        });
    };

    // ── Image block helpers ──
    const parseJsonContent = (content: string): Record<string, any> => {
        try { return JSON.parse(content) || {}; } catch { return {}; }
    };

    const handleImageUpload = async (files: File[]) => {
        const file = files[0];
        if (!file) return;
        setImgUploading(true);
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
            if (res?.url) {
                const imgData = parseJsonContent(localContent);
                const data = {...imgData, url: res.url};
                const json = JSON.stringify(data);
                setLocalContent(json);
                onUpdate(block, {content: json});
            }
        } catch { showToast(labels.error, 'danger'); }
        finally { setImgUploading(false); }
    };

    const updateImageField = (field: string, value: string | boolean) => {
        const imgData = parseJsonContent(localContent);
        const data = {...imgData, [field]: value};
        const json = JSON.stringify(data);
        setLocalContent(json);
        onUpdate(block, {content: json});
    };

    const removeImage = (url: string) => {
        setPendingDeleteImg({
            url,
            action: async () => {
                const imgData = parseJsonContent(localContent);
                if (imgData.url) {
                    try { await sendPost(deleteImageUrl, {url: imgData.url}); } catch { /* silent */ }
                }
                const json = JSON.stringify({});
                setLocalContent(json);
                onUpdate(block, {content: json});
            },
        });
    };

    // ── Gallery block helpers ──
    const handleGalleryUpload = async (files: File[]) => {
        setImgUploading(true);
        try {
            const galData = parseJsonContent(localContent);
            let images: {url: string; alt: string}[] = galData.images || [];
            for (const file of files) {
                const fd = new FormData();
                fd.append('file', file);
                const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
                if (res?.url) {
                    images = [...images, {url: res.url, alt: ''}];
                }
            }
            const data = {...galData, images};
            const json = JSON.stringify(data);
            setLocalContent(json);
            onUpdate(block, {content: json});
        } catch { showToast(labels.error, 'danger'); }
        finally { setImgUploading(false); }
    };

    const removeGalleryImage = (idx: number) => {
        const galData = parseJsonContent(localContent);
        const images: {url: string; alt: string}[] = galData.images || [];
        const img = images[idx];
        if (!img) return;
        setPendingDeleteImg({
            url: img.url,
            action: async () => {
                if (img.url) {
                    try { await sendPost(deleteImageUrl, {url: img.url}); } catch { /* silent */ }
                }
                const freshData = parseJsonContent(localContent);
                const freshImages: {url: string; alt: string}[] = freshData.images || [];
                const newImages = freshImages.filter((_: any, i: number) => i !== idx);
                const data = {...freshData, images: newImages};
                const json = JSON.stringify(data);
                setLocalContent(json);
                onUpdate(block, {content: json});
            },
        });
    };

    const updateGalleryField = (field: string, value: any) => {
        const galData = parseJsonContent(localContent);
        const data = {...galData, [field]: value};
        const json = JSON.stringify(data);
        setLocalContent(json);
        onUpdate(block, {content: json});
    };

    const updateGalleryImageAlt = (idx: number, alt: string) => {
        const galData = parseJsonContent(localContent);
        const images: {url: string; alt: string}[] = galData.images || [];
        const newImages = images.map((img: {url: string; alt: string}, i: number) => i === idx ? {...img, alt} : img);
        updateGalleryField('images', newImages);
    };

    // ── Block type label ──
    const blockTypeLabel = (BLOCK_TYPE_LABELS[block.block_type] ?? (() => block.block_type))(labels);

    // ── Render block body ──
    const renderBlockBody = () => {
        if (block.block_type === 'heading') {
            return (
                <input
                    className="form-control w-full border-default"
                    value={localContent}
                    onChange={e => propagateContent(e.target.value)}
                    disabled={disabled}
                />
            );
        }

        if (block.block_type === 'image') {
            const imgData = parseJsonContent(localContent);
            return imgData.url ? (
                <div className="space-y-2">
                    <div className="blk-img-preview">
                        <img src={imgData.url} alt={imgData.alt || ''} className="blk-img-preview-img" />
                        <button type="button" className="blk-img-preview-remove" onClick={() => removeImage(imgData.url)} title={labels.removeImage}>&#215;</button>
                    </div>
                    <input
                        className="form-control w-full border-default text-sm"
                        placeholder={labels.imageAlt}
                        value={imgData.alt || ''}
                        onChange={e => updateImageField('alt', e.target.value)}
                    />
                    <label className="flex items-center gap-2 text-sm text-secondary cursor-pointer">
                        <input type="checkbox" checked={!!imgData.lightbox} onChange={e => updateImageField('lightbox', e.target.checked)} />
                        {labels.imageLightbox}
                    </label>
                </div>
            ) : (
                <ImageUploadArea onUpload={files => void handleImageUpload(files)} uploading={imgUploading} label={labels.uploadImage} />
            );
        }

        if (block.block_type === 'gallery') {
            const galData = parseJsonContent(localContent);
            const images: {url: string; alt: string}[] = galData.images || [];
            return (
                <div className="space-y-3">
                    {images.length > 0 && (
                        <div className="blk-gal-grid">
                            {images.map((img: {url: string; alt: string}, idx: number) => (
                                <div key={idx} className="blk-gal-thumb">
                                    <img src={img.url} alt={img.alt || ''} className="blk-gal-thumb-img" />
                                    <button
                                        type="button"
                                        className="blk-gal-thumb-remove"
                                        onClick={() => void removeGalleryImage(idx)}
                                        title={labels.removeImage}
                                    >
                                        &#215;
                                    </button>
                                    <input
                                        className="form-control w-full border-default text-xs p-1"
                                        placeholder={labels.imageAlt}
                                        value={img.alt || ''}
                                        onChange={e => updateGalleryImageAlt(idx, e.target.value)}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    <ImageUploadArea onUpload={files => void handleGalleryUpload(files)} uploading={imgUploading} label={labels.uploadImage} multiple />
                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-sm text-secondary">
                            {labels.galleryRows}
                            <input
                                type="number"
                                min={1}
                                max={10}
                                className="form-control form-control-sm w-16 border-default"
                                value={galData.rows ?? 2}
                                onChange={e => updateGalleryField('rows', Math.max(1, Math.min(10, parseInt(e.target.value, 10) || 2)))}
                            />
                        </label>
                        <label className="flex items-center gap-2 text-sm text-secondary cursor-pointer">
                            <input type="checkbox" checked={galData.lightbox !== false} onChange={e => updateGalleryField('lightbox', e.target.checked)} />
                            {labels.imageLightbox}
                        </label>
                    </div>
                </div>
            );
        }

        // Default: text block
        return (
            <>
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
                    {pages && pages.length > 0 && (
                        <div className="relative inline-block">
                            <button type="button" className="blk-fmt-btn text-xs"
                                onClick={(e) => { e.stopPropagation(); setShowPagePicker(!showPagePicker); }} title="Page link">
                                Page
                            </button>
                            {showPagePicker && (
                                <div className="absolute left-0 top-full mt-1 z-10 bg-surface border border-default rounded-lg shadow-lg py-1 min-w-48 max-h-48 overflow-y-auto">
                                    {pages.filter(p => p.is_published).map(p => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            className="block w-full text-left px-3 py-1.5 text-sm text-on-surface hover:bg-surface-hover transition-colors"
                                            onClick={() => {
                                                insertMarkdown('{link:' + p.slug + '}', '');
                                                setShowPagePicker(false);
                                            }}
                                        >
                                            {p.title_rendered || p.title || p.slug}
                                        </button>
                                    ))}
                                    {pages.filter(p => p.is_published).length === 0 && (
                                        <div className="px-3 py-1.5 text-sm text-muted">{labels.empty}</div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
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
                {showPreview ? (
                    <div className="md-preview"
                         dangerouslySetInnerHTML={{__html: markdownToHtml(localContent)}} />
                ) : (
                    <textarea
                        ref={textareaRef}
                        className="form-control w-full border-default font-mono text-sm"
                        rows={8}
                        value={localContent}
                        onChange={e => propagateContent(e.target.value)}
                        disabled={disabled}
                    />
                )}
            </>
        );
    };

    return (
        <div className={`blk-card ${block.is_hidden ? 'blk-card-dimmed' : ''}`}>
            <div className="blk-header">
                <span className="text-xs font-medium text-secondary px-2 py-1">
                    {blockTypeLabel}
                </span>

                <button
                    type="button"
                    className="blk-icon-btn"
                    onClick={() => onMove(index, -1)}
                    disabled={disabled || index === 0}
                    title={labels.moveUp}
                >
                    &#8593;
                </button>
                <button
                    type="button"
                    className="blk-icon-btn"
                    onClick={() => onMove(index, 1)}
                    disabled={disabled || index === total - 1}
                    title={labels.moveDown}
                >
                    &#8595;
                </button>

                <div className="ml-auto flex items-center gap-1">
                    <select
                        className="form-select form-select-sm text-xs"
                        style={{width: 'auto', minWidth: '90px'}}
                        value={block.visibility || 'all'}
                        onChange={e => onUpdate(block, {visibility: e.target.value} as any)}
                        disabled={disabled}
                        title={labels.blockVisibility}
                    >
                        <option value="all">{labels.visibilityAll}</option>
                        <option value="guest">{labels.visibilityGuest}</option>
                        <option value="auth">{labels.visibilityAuth}</option>
                        <option value="moderator">{labels.visibilityModerator}</option>
                    </select>
                    <button
                        type="button"
                        className={`blk-vis-chip ${block.is_hidden ? 'blk-vis-chip-off' : 'blk-vis-chip-on'}`}
                        onClick={() => onToggleVisibility(block)}
                        disabled={disabled}
                    >
                        {block.is_hidden ? labels.blockHidden : labels.blockVisible}
                    </button>
                    <button
                        type="button"
                        className="blk-icon-btn-danger"
                        onClick={() => void onDelete(block)}
                        disabled={disabled}
                        title={labels.actionDelete}
                    >
                        &#215;
                    </button>
                </div>
            </div>

            {renderBlockBody()}

            {pendingDeleteImg && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" onClick={() => setPendingDeleteImg(null)}>
                    <div className="bg-surface rounded-lg border border-default p-5 max-w-sm w-full shadow-xl" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-center mb-4">
                            <img src={pendingDeleteImg.url} alt="" className="max-h-40 rounded-lg" />
                        </div>
                        <p className="text-sm text-on-surface text-center mb-4">{labels.removeImage}?</p>
                        <div className="flex justify-center gap-2">
                            <button
                                type="button"
                                className="btn btn-danger"
                                onClick={() => { void pendingDeleteImg.action(); setPendingDeleteImg(null); }}
                            >
                                {labels.actionDelete}
                            </button>
                            <button type="button" className="btn btn-secondary" onClick={() => setPendingDeleteImg(null)}>
                                {labels.actionCancel}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

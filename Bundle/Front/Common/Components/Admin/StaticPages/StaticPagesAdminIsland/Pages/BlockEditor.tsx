/**
 * Редактор одного блока: текст, изображение, галерея, ссылки.
 */
import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ImageUploadArea} from '@common/Components/Controls/ImageUploadArea';
import {BlockEditorHeader} from './BlockEditorHeader';
import {BlockEditorText} from './BlockEditorText';
import {BlockImageDeleteDialog} from './BlockImageDeleteDialog';
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

type EditorState = {
    localContent: string;
    imgUploading: boolean;
    showPreview: boolean;
    showPagePicker: boolean;
    pendingDeleteUrl: string | null;
};
type PendingDelete = {url: string; action: () => Promise<void>};

const parseJsonContent = (content: string): Record<string, any> => {
    try { return JSON.parse(content) || {}; } catch { return {}; }
};

export class BlockEditor extends AntiHookComponent<BlockEditorProps> {
    private readonly ui = new Carburetor<EditorState>({
        localContent: this.props.block.content,
        imgUploading: false,
        showPreview: false,
        showPagePicker: false,
        pendingDeleteUrl: null,
    });
    private readonly view = this.connectSelection(() => this.ui, state => ({localContent: state.localContent, imgUploading: state.imgUploading, showPreview: state.showPreview, showPagePicker: state.showPagePicker, pendingDeleteUrl: state.pendingDeleteUrl}));
    private readonly textareaRef = React.createRef<HTMLTextAreaElement>();
    private pendingDeleteAction: (() => Promise<void>) | null = null;

    public componentDidMount() {
        super.componentDidMount();
        document.addEventListener('click', this.closePagePicker);
    }

    public componentWillUnmount() {
        document.removeEventListener('click', this.closePagePicker);
        super.componentWillUnmount();
    }

    public componentDidUpdate(previous: BlockEditorProps) {
        super.componentDidUpdate(previous);
        if (previous.block.content !== this.props.block.content) this.setLocalContent(this.props.block.content);
    }

    private readonly setLocalContent = (localContent: string) => this.ui.setData({...this.ui.getData(), localContent});
    private readonly setImgUploading = (imgUploading: boolean) => this.ui.setData({...this.ui.getData(), imgUploading});
    private readonly setShowPreview = (showPreview: boolean) => this.ui.setData({...this.ui.getData(), showPreview});
    private readonly setShowPagePicker = (showPagePicker: boolean) => {
        const state = this.ui.getData();
        if (state.showPagePicker !== showPagePicker) this.ui.setData({...state, showPagePicker});
    };
    private readonly closePagePicker = () => this.setShowPagePicker(false);
    private readonly setPendingDeleteImg = (pending: PendingDelete | null) => {
        this.pendingDeleteAction = pending?.action ?? null;
        this.ui.setData({...this.ui.getData(), pendingDeleteUrl: pending?.url ?? null});
    };
    private readonly cancelDeleteImage = () => this.setPendingDeleteImg(null);
    private readonly confirmDeleteImage = () => {
        const action = this.pendingDeleteAction;
        if (action) void action();
        this.setPendingDeleteImg(null);
    };

    // Propagate local text changes to parent state
    private readonly propagateContent = (content: string) => {
        this.setLocalContent(content);
        this.props.onUpdate(this.props.block, {content});
    };

    private readonly insertMarkdown = (before: string, after: string) => {
        const localContent = this.ui.getData().localContent;
        const ta = this.textareaRef.current;
        if (!ta) return;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = localContent.substring(start, end);
        const replacement = before + (selected || 'text') + after;
        const newContent = localContent.substring(0, start) + replacement + localContent.substring(end);
        this.propagateContent(newContent);
        requestAnimationFrame(() => {
            ta.focus();
            const cursorPos = start + before.length + (selected || 'text').length;
            ta.setSelectionRange(cursorPos, cursorPos);
        });
    };

    // ── Image block helpers ──
    private readonly handleImageUpload = async (files: File[]) => {
        const {uploadImageUrl, labels, block, onUpdate} = this.props;
        const {localContent} = this.ui.getData();
        const {setImgUploading, setLocalContent} = this;
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

    private readonly updateImageField = (field: string, value: string | boolean) => {
        const {block, onUpdate} = this.props;
        const {localContent} = this.ui.getData();
        const {setLocalContent} = this;
        const imgData = parseJsonContent(localContent);
        const data = {...imgData, [field]: value};
        const json = JSON.stringify(data);
        setLocalContent(json);
        onUpdate(block, {content: json});
    };

    private readonly removeImage = (url: string) => {
        const {deleteImageUrl, block, onUpdate} = this.props;
        const {localContent} = this.ui.getData();
        const {setPendingDeleteImg, setLocalContent} = this;
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
    private readonly handleGalleryUpload = async (files: File[]) => {
        const {uploadImageUrl, labels, block, onUpdate} = this.props;
        const {localContent} = this.ui.getData();
        const {setImgUploading, setLocalContent} = this;
        setImgUploading(true);
        try {
            const galData = parseJsonContent(localContent);
            const images: {url: string; alt: string}[] = [...(galData.images || [])];
            // Последовательная загрузка сохраняет порядок и останавливается при ошибке.
            for (const file of files) {
                const fd = new FormData();
                fd.append('file', file);
                // eslint-disable-next-line no-await-in-loop
                const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
                if (res?.url) {
                    images.push({url: res.url, alt: ''});
                }
            }
            const data = {...galData, images};
            const json = JSON.stringify(data);
            setLocalContent(json);
            onUpdate(block, {content: json});
        } catch { showToast(labels.error, 'danger'); }
        finally { setImgUploading(false); }
    };

    private readonly removeGalleryImage = (idx: number) => {
        const {deleteImageUrl, block, onUpdate} = this.props;
        const {localContent} = this.ui.getData();
        const {setPendingDeleteImg, setLocalContent} = this;
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

    private readonly updateGalleryField = (field: string, value: any) => {
        const {block, onUpdate} = this.props;
        const {localContent} = this.ui.getData();
        const {setLocalContent} = this;
        const galData = parseJsonContent(localContent);
        const data = {...galData, [field]: value};
        const json = JSON.stringify(data);
        setLocalContent(json);
        onUpdate(block, {content: json});
    };

    private readonly updateGalleryImageAlt = (idx: number, alt: string) => {
        const {localContent} = this.ui.getData();
        const {updateGalleryField} = this;
        const galData = parseJsonContent(localContent);
        const images: {url: string; alt: string}[] = galData.images || [];
        if (!images[idx]) return;
        const newImages = [...images];
        newImages[idx] = {...images[idx], alt};
        updateGalleryField('images', newImages);
    };

    private readonly changeHeading = (event: React.ChangeEvent<HTMLInputElement>) => this.propagateContent(event.target.value);
    private readonly removeImageClick = (event: React.MouseEvent<HTMLButtonElement>) => this.removeImage(event.currentTarget.dataset.url ?? '');
    private readonly changeImageAlt = (event: React.ChangeEvent<HTMLInputElement>) => this.updateImageField('alt', event.target.value);
    private readonly changeImageLightbox = (event: React.ChangeEvent<HTMLInputElement>) => this.updateImageField('lightbox', event.target.checked);
    private readonly uploadImage = (files: File[]) => { void this.handleImageUpload(files); };
    private readonly uploadGallery = (files: File[]) => { void this.handleGalleryUpload(files); };
    private readonly removeGalleryClick = (event: React.MouseEvent<HTMLButtonElement>) => this.removeGalleryImage(Number(event.currentTarget.dataset.index));
    private readonly changeGalleryAlt = (event: React.ChangeEvent<HTMLInputElement>) => this.updateGalleryImageAlt(Number(event.currentTarget.dataset.index), event.target.value);
    private readonly changeGalleryRows = (event: React.ChangeEvent<HTMLInputElement>) => this.updateGalleryField('rows', Math.max(1, Math.min(10, parseInt(event.target.value, 10) || 2)));
    private readonly changeGalleryLightbox = (event: React.ChangeEvent<HTMLInputElement>) => this.updateGalleryField('lightbox', event.target.checked);

    // ── Render block body ──
    private readonly renderGalleryThumbnail = (img: {url: string; alt: string}, idx: number) => {
        const {labels} = this.props;
        return (
            <div key={`${img.url}-${idx}`} className="blk-gal-thumb">
            <img src={img.url} alt={img.alt || ''} className="blk-gal-thumb-img" />
            <button
                type="button"
                className="blk-gal-thumb-remove"
                data-index={idx}
                onClick={this.removeGalleryClick}
                title={labels.removeImage}
            >
                &#215;
            </button>
            <input
                className="block-gallery-alt"
                placeholder={labels.imageAlt}
                value={img.alt || ''}
                data-index={idx}
                onChange={this.changeGalleryAlt}
            />
        </div>
        );
    };

    private renderBlockBody(state: EditorState) {
        const {block, labels, disabled, pages} = this.props;
        const {localContent, imgUploading, showPreview, showPagePicker} = state;
        const {textareaRef, propagateContent, insertMarkdown, setShowPreview, setShowPagePicker} = this;
        if (block.block_type === 'heading') {
            return (
                <input
                    className="static-admin-input"
                    value={localContent}
                    onChange={this.changeHeading}
                    disabled={disabled}
                />
            );
        }

        if (block.block_type === 'image') {
            const imgData = parseJsonContent(localContent);
            return imgData.url ? (
                <div className="snippet-header-logo-fields">
                    <div className="blk-img-preview">
                        <img src={imgData.url} alt={imgData.alt || ''} className="blk-img-preview-img" />
                        <button type="button" className="blk-img-preview-remove" data-url={imgData.url} onClick={this.removeImageClick} title={labels.removeImage}>&#215;</button>
                    </div>
                    <input
                        className="static-admin-input"
                        placeholder={labels.imageAlt}
                        value={imgData.alt || ''}
                        onChange={this.changeImageAlt}
                    />
                    <label className="block-image-check">
                        <input type="checkbox" checked={!!imgData.lightbox} onChange={this.changeImageLightbox} />
                        {labels.imageLightbox}
                    </label>
                </div>
            ) : (
                <ImageUploadArea onUpload={this.uploadImage} uploading={imgUploading} label={labels.uploadImage} />
            );
        }

        if (block.block_type === 'gallery') {
            const galData = parseJsonContent(localContent);
            const images: {url: string; alt: string}[] = galData.images || [];
            return (
                <div className="block-gallery">
                    {images.length > 0 && (
                        <div className="blk-gal-grid">
                            {images.map(this.renderGalleryThumbnail)}
                        </div>
                    )}
                    <ImageUploadArea onUpload={this.uploadGallery} uploading={imgUploading} label={labels.uploadImage} multiple />
                    <div className="snippet-save-row">
                        <label className="block-image-label">
                            {labels.galleryRows}
                            <input
                                type="number"
                                min={1}
                                max={10}
                                className="block-gallery-rows"
                                value={galData.rows ?? 2}
                                onChange={this.changeGalleryRows}
                            />
                        </label>
                        <label className="block-image-check">
                            <input type="checkbox" checked={galData.lightbox !== false} onChange={this.changeGalleryLightbox} />
                            {labels.imageLightbox}
                        </label>
                    </div>
                </div>
            );
        }

        return <BlockEditorText labels={labels} pages={pages} localContent={localContent} disabled={disabled}
            textareaRef={textareaRef} propagateContent={propagateContent} insertMarkdown={insertMarkdown}
            showPreview={showPreview} showPagePicker={showPagePicker} setShowPreview={setShowPreview} setShowPagePicker={setShowPagePicker} />;
    }

    public render() {
        const {block, index, total, labels, disabled, onUpdate, onDelete, onMove, onToggleVisibility} = this.props;
        const state = this.view() as EditorState;
        const {pendingDeleteUrl} = state;
        return (
        <div className="blk-card" data-hidden={!!block.is_hidden}>
            <BlockEditorHeader block={block} index={index} total={total} labels={labels} disabled={disabled}
                onUpdate={onUpdate} onDelete={onDelete} onMove={onMove} onToggleVisibility={onToggleVisibility} />

            {this.renderBlockBody(state)}

            {pendingDeleteUrl !== null && <BlockImageDeleteDialog url={pendingDeleteUrl} labels={labels}
                onCancel={this.cancelDeleteImage} onConfirm={this.confirmDeleteImage} />}
        </div>
        );
    }
}

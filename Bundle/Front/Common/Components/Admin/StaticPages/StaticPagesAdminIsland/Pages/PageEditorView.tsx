import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {ImageUploadField} from '@common/Components/Controls/ImageUploadField';
import {StaticPage, Snippet, PageBlock} from '../types';
import {BlockEditor} from './BlockEditor';
import type {PageEditorProps} from './PageEditor';

const renderSnippetOption = (snippet: Snippet) => <option key={snippet.id} value={snippet.id}>{snippet.name || snippet.slug}</option>;

export class PageEditorView extends AntiHookComponent<PageEditorProps> {
    private update(field: keyof StaticPage, value: string | number | null) {
        this.props.onPageChange({...this.props.page, [field]: value});
    }

    private readonly changeTitle = (event: React.ChangeEvent<HTMLInputElement>) => this.update('title', event.target.value);
    private readonly changeSlug = (event: React.ChangeEvent<HTMLInputElement>) => this.update('slug', event.target.value);
    private readonly changeDescription = (event: React.ChangeEvent<HTMLInputElement>) => this.update('meta_description', event.target.value);
    private readonly changeSeoTitle = (event: React.ChangeEvent<HTMLInputElement>) => this.update('seo_title', event.target.value);
    private readonly changeImage = (url: string) => this.update('og_image', url);
    private readonly changeWidth = (event: React.ChangeEvent<HTMLSelectElement>) => this.update('max_width', event.target.value);
    private readonly changeVisibility = (event: React.ChangeEvent<HTMLSelectElement>) => this.update('visibility', event.target.value);
    private readonly changeHeader = (event: React.ChangeEvent<HTMLSelectElement>) => this.update('header_snippet_id', event.target.value ? Number(event.target.value) : null);
    private readonly changeFooter = (event: React.ChangeEvent<HTMLSelectElement>) => this.update('footer_snippet_id', event.target.value ? Number(event.target.value) : null);
    private readonly changePublished = (event: React.ChangeEvent<HTMLInputElement>) => this.update('is_published', event.target.checked ? 1 : 0);
    private readonly openPicker = (event: React.MouseEvent<HTMLButtonElement>) => this.props.onSetAddingAtPosition(Number(event.currentTarget.dataset.position));
    private readonly closePicker = () => this.props.onSetAddingAtPosition(null);
    private readonly addBlock = (event: React.MouseEvent<HTMLButtonElement>) => {
        const {position, type} = event.currentTarget.dataset;
        if (position !== undefined && type !== undefined) this.props.onAddBlock(Number(position), type);
    };
    private readonly renderBlock = (block: PageBlock, index: number) => {
        const {blocks, labels, sending, uploadImageUrl, deleteImageUrl, pages, onUpdateBlock, onDeleteBlock, onMoveBlock, onToggleBlockVisibility} = this.props;
        return <React.Fragment key={block.id}>
            <BlockEditor block={block} index={index} total={blocks.length} labels={labels} disabled={sending}
                uploadImageUrl={uploadImageUrl} deleteImageUrl={deleteImageUrl} pages={pages}
                onUpdate={onUpdateBlock} onDelete={onDeleteBlock} onMove={onMoveBlock} onToggleVisibility={onToggleBlockVisibility} />
            {this.renderAddSeparator(index + 1)}
        </React.Fragment>;
    };

    private renderAddSeparator(position: number) {
        const {addingAtPosition, labels} = this.props;
        if (addingAtPosition === position) return <div className="page-editor-picker">
            <button type="button" className="page-editor-picker-button" data-position={position} data-type="text" onClick={this.addBlock}>{labels.blockTypeText}</button>
            <button type="button" className="page-editor-picker-button" data-position={position} data-type="gallery" onClick={this.addBlock}>{labels.blockTypeGallery}</button>
            <button type="button" className="page-editor-picker-button" onClick={this.closePicker}>{labels.actionCancel}</button>
        </div>;
        return <div className="page-editor-add-separator">
            <span className="page-editor-add-line" aria-hidden="true" />
            <button type="button" className="blk-add-btn" data-position={position} onClick={this.openPicker} title={labels.addBlock}>+</button>
        </div>;
    }

    private renderHeading() {
        const {page, labels, publicBaseUrl, onDelete, sending} = this.props;
        return <div className="static-admin-toolbar">
            <div className="page-editor-heading">
                <h2 className="page-editor-title">{labels.editPage}: {page.slug}</h2>
                <a href={publicBaseUrl + page.slug} target="_blank" rel="noopener noreferrer" className="page-editor-link">{labels.openPage} &#8599;</a>
            </div>
            <button type="button" className="page-editor-delete" onClick={onDelete} disabled={sending}>{labels.actionDelete}</button>
        </div>;
    }

    private renderFields() {
        const {page, labels, uploadImageUrl, deleteImageUrl, sending, headerSnippetOptions, footerSnippetOptions} = this.props;
        return <div className="static-admin-fields">
            <label className="static-admin-label"><span className="static-admin-caption">{labels.pageTitle}</span>
                <input className="static-admin-input" value={page.title} onChange={this.changeTitle} /></label>
            <label className="static-admin-label"><span className="static-admin-caption">{labels.slug}</span>
                <input className="static-admin-input" value={page.slug} onChange={this.changeSlug} /></label>
            <label className="static-admin-label"><span className="static-admin-caption">{labels.metaDescription}</span>
                <input className="static-admin-input" value={page.meta_description} onChange={this.changeDescription} /></label>
            <label className="static-admin-label"><span className="static-admin-caption">SEO title</span>
                <input className="static-admin-input" value={page.seo_title ?? ''} placeholder={page.title} onChange={this.changeSeoTitle} /></label>
            <div className="static-admin-label">
                <span className="static-admin-caption">OG image (social preview)</span>
                <span className="page-editor-image-hint">preview image for Telegram/WhatsApp; if empty, the default image is used</span>
                <ImageUploadField value={page.og_image ?? ''} onChange={this.changeImage} uploadUrl={uploadImageUrl} deleteUrl={deleteImageUrl}
                    uploadLabel={labels.uploadImage} removeLabel={labels.removeImage} removeConfirm={labels.removeImage}
                    errorLabel={labels.error} previewAlt={page.title} disabled={sending} />
            </div>
            <label className="static-admin-label"><span className="static-admin-caption">{labels.maxWidth}</span>
                <select className="static-admin-input" value={page.max_width || '3xl'} onChange={this.changeWidth}>
                    <option value="xl">xl — 576px</option><option value="2xl">2xl — 672px</option><option value="3xl">3xl — 768px</option>
                    <option value="4xl">4xl — 896px</option><option value="5xl">5xl — 1024px</option><option value="6xl">6xl — 1152px</option>
                    <option value="7xl">7xl — 1280px</option><option value="full">full — 100%</option>
                </select></label>
            <label className="static-admin-label"><span className="static-admin-caption">{labels.visibility}</span>
                <select className="static-admin-input" value={page.visibility || 'all'} onChange={this.changeVisibility}>
                    <option value="all">{labels.visibilityAll}</option><option value="guest">{labels.visibilityGuest}</option>
                    <option value="auth">{labels.visibilityAuth}</option><option value="moderator">{labels.visibilityModerator}</option>
                </select></label>
            <label className="static-admin-label"><span className="static-admin-caption">{labels.headerSnippet}</span>
                <select className="static-admin-input" value={page.header_snippet_id ?? ''} onChange={this.changeHeader}>
                    <option value="">{labels.noSnippet}</option>{headerSnippetOptions.map(renderSnippetOption)}
                </select></label>
            <label className="static-admin-label"><span className="static-admin-caption">{labels.footerSnippet}</span>
                <select className="static-admin-input" value={page.footer_snippet_id ?? ''} onChange={this.changeFooter}>
                    <option value="">{labels.noSnippet}</option>{footerSnippetOptions.map(renderSnippetOption)}
                </select></label>
        </div>;
    }

    private renderBlocks() {
        const {blocks, labels} = this.props;
        return <div>
            <h3 className="page-editor-block-title">{labels.blocks}</h3>
            {this.renderAddSeparator(0)}
            {blocks.map(this.renderBlock)}
            {blocks.length === 0 && <div className="page-editor-block-empty">{labels.empty}</div>}
        </div>;
    }

    private renderActions() {
        const {page, labels, sending, onSave, blocksDirty} = this.props;
        return <div className="page-editor-actions">
            <button type="button" className="snippet-save" onClick={onSave} disabled={sending}>{labels.savePage}</button>
            {blocksDirty && <span className="page-editor-dirty">&#9679;</span>}
            <label className="snippet-active-label">
                <input type="checkbox" checked={!!page.is_published} onChange={this.changePublished} />
                <span className="snippet-active-caption">{labels.published}</span>
            </label>
        </div>;
    }

    public render() {
        const {templateVariables, labels} = this.props;
        return <section className="page-editor-panel">
            {this.renderHeading()}
            {this.renderFields()}
            {this.renderBlocks()}
            {templateVariables.length > 0 && <div className="page-editor-variables">{labels.variables}</div>}
            {this.renderActions()}
        </section>;
    }
}

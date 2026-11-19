import * as React from 'react';
import {markdownToHtml} from '@common/Utils/Ui/markdownToHtml';
import {Labels, StaticPage} from '../types';

type Props = {
    labels: Labels;
    pages?: StaticPage[];
    localContent: string;
    disabled: boolean;
    textareaRef: React.RefObject<HTMLTextAreaElement>;
    propagateContent: (content: string) => void;
    insertMarkdown: (before: string, after: string) => void;
    showPreview: boolean;
    showPagePicker: boolean;
    setShowPagePicker: (value: boolean) => void;
    setShowPreview: (value: boolean) => void;
};
const isPublished = (page: StaticPage) => page.is_published;

export class BlockEditorText extends React.PureComponent<Props> {
    private readonly showEdit = () => this.props.setShowPreview(false);
    private readonly showPreview = () => this.props.setShowPreview(true);
    private readonly togglePicker = (event: React.MouseEvent<HTMLButtonElement>) => {
        event.stopPropagation();
        this.props.setShowPagePicker(!this.props.showPagePicker);
    };

    private readonly insertFormat = (event: React.MouseEvent<HTMLButtonElement>) => {
        this.props.insertMarkdown(event.currentTarget.dataset.before ?? '', event.currentTarget.dataset.after ?? '');
    };
    private readonly insertPage = (event: React.MouseEvent<HTMLButtonElement>) => {
        this.props.insertMarkdown('{link:' + event.currentTarget.dataset.slug + '}', '');
        this.props.setShowPagePicker(false);
    };
    private readonly changeContent = (event: React.ChangeEvent<HTMLTextAreaElement>) => this.props.propagateContent(event.target.value);

    private renderPagePicker() {
        const {pages, showPagePicker, labels} = this.props;
        if (!pages?.length) return null;
        const published = pages.filter(isPublished);
        return <div className="blk-page-picker">
            <button type="button" className="snippet-format-button" onClick={this.togglePicker} title="Page link">Page</button>
            {showPagePicker && <div className="blk-page-popup">
                {published.length ? published.map(this.renderPageOption) : <div className="blk-page-empty">{labels.empty}</div>}
            </div>}
        </div>;
    }

    private readonly renderPageOption = (page: StaticPage) => (
        <button key={page.id} type="button" className="blk-page-option" data-slug={page.slug} onClick={this.insertPage}>
            {page.title_rendered || page.title || page.slug}
        </button>
    );

    private renderToolbar() {
        const {showPreview} = this.props;
        return (
        <div className="snippet-format-bar">
            <button type="button" className="snippet-format-bold"
                data-before={'**'} data-after={'**'} onClick={this.insertFormat} title="Bold">B</button>
            <button type="button" className="snippet-format-italic"
                data-before={'*'} data-after={'*'} onClick={this.insertFormat} title="Italic">I</button>
            <button type="button" className="snippet-format-button"
                data-before={'## '} data-after={''} onClick={this.insertFormat} title="Heading 2">H2</button>
            <button type="button" className="snippet-format-button"
                data-before={'### '} data-after={''} onClick={this.insertFormat} title="Heading 3">H3</button>
            <span className="snippet-format-divider" />
            <button type="button" className="snippet-format-button"
                data-before={'['} data-after={'](url)'} onClick={this.insertFormat} title="Link">Link</button>
            {this.renderPagePicker()}
            <button type="button" className="snippet-format-button"
                data-before={'- '} data-after={''} onClick={this.insertFormat} title="List">List</button>
            <button type="button" className="snippet-format-button"
                data-before={'1. '} data-after={''} onClick={this.insertFormat} title="Ordered list">OL</button>
            <span className="snippet-format-divider" />
            <button type="button" className="snippet-format-button"
                data-before={'> '} data-after={''} onClick={this.insertFormat} title="Quote">Quote</button>
            <button type="button" className="snippet-format-button"
                data-before={'\n---\n'} onClick={this.insertFormat} title="Horizontal rule">HR</button>
            <span className="snippet-format-divider" />
            <div className="snippet-mode">
                <button type="button"
                    aria-pressed={!showPreview}
                    className="snippet-mode-button"
                    onClick={this.showEdit}>
                    Edit
                </button>
                <button type="button"
                    aria-pressed={showPreview}
                    className="snippet-mode-button"
                    onClick={this.showPreview}>
                    Preview
                </button>
            </div>
        </div>
        );
    }

    public render() {
        const {localContent, disabled, textareaRef, showPreview} = this.props;
        return (
            <>
                {this.renderToolbar()}
                {showPreview ? (
                    <div className="md-preview"
                         dangerouslySetInnerHTML={{__html: markdownToHtml(localContent)}} />
                ) : (
                    <textarea
                        ref={textareaRef}
                        className="snippet-content"
                        rows={8}
                        value={localContent}
                        onChange={this.changeContent}
                        disabled={disabled}
                    />
                )}
            </>
        );
    }
}

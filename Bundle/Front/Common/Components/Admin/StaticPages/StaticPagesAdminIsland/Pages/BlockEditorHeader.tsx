import * as React from 'react';
import {BLOCK_TYPE_LABELS} from '../types';
import type {BlockEditorProps} from './BlockEditor';

type HeaderProps = Pick<BlockEditorProps, 'block' | 'index' | 'total' | 'labels' | 'disabled' | 'onUpdate' | 'onDelete' | 'onMove' | 'onToggleVisibility'>;

export class BlockEditorHeader extends React.PureComponent<HeaderProps> {
    private readonly moveUp = () => this.props.onMove(this.props.index, -1);
    private readonly moveDown = () => this.props.onMove(this.props.index, 1);
    private readonly toggleVisibility = () => this.props.onToggleVisibility(this.props.block);
    private readonly deleteBlock = () => this.props.onDelete(this.props.block);
    private readonly changeVisibility = (event: React.ChangeEvent<HTMLSelectElement>) => {
        this.props.onUpdate(this.props.block, {visibility: event.target.value} as any);
    };

    public render() {
        const {block, index, total, labels, disabled} = this.props;
        const blockTypeLabel = (BLOCK_TYPE_LABELS[block.block_type] ?? (() => block.block_type))(labels);
        return (
            <div className="blk-header">
                <span className="block-type-label">{blockTypeLabel}</span>
                <button type="button" className="blk-icon-btn" onClick={this.moveUp} disabled={disabled || index === 0} title={labels.moveUp}>&#8593;</button>
                <button type="button" className="blk-icon-btn" onClick={this.moveDown} disabled={disabled || index === total - 1} title={labels.moveDown}>&#8595;</button>
                <div className="block-header-actions">
                    <select className="snippet-menu-visibility"
                        value={block.visibility || 'all'} onChange={this.changeVisibility} disabled={disabled} title={labels.blockVisibility}>
                        <option value="all">{labels.visibilityAll}</option>
                        <option value="guest">{labels.visibilityGuest}</option>
                        <option value="auth">{labels.visibilityAuth}</option>
                        <option value="moderator">{labels.visibilityModerator}</option>
                    </select>
                    <button type="button" className="blk-vis-chip" data-hidden={!!block.is_hidden}
                        onClick={this.toggleVisibility} disabled={disabled}>
                        {block.is_hidden ? labels.blockHidden : labels.blockVisible}
                    </button>
                    <button type="button" className="blk-icon-btn-danger" onClick={this.deleteBlock} disabled={disabled} title={labels.actionDelete}>&#215;</button>
                </div>
            </div>
        );
    }
}

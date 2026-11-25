import * as React from 'react';
import {Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';

type Props = {url: string; labels: Labels; onCancel: () => void; onConfirm: () => void};

export class BlockImageDeleteDialog extends React.PureComponent<Props> {
    /** Stops clicks inside the dialog from closing it. */
    private readonly stopPropagation = (event: React.MouseEvent<HTMLDivElement>) => event.stopPropagation();

    /** Renders the image deletion confirmation dialog. */
    public render() {
        const {url, labels, onCancel, onConfirm} = this.props;
        return (
            <div className="block-delete-overlay" onClick={onCancel}>
                <div className="block-delete-card" onClick={this.stopPropagation}>
                    <div className="block-delete-image-wrap">
                        <img src={url} alt="" className="block-delete-image" />
                    </div>
                    <p className="block-delete-caption">{labels.removeImage}?</p>
                    <div className="block-delete-actions">
                        <button type="button" className="confirm-modal-danger"
                            onClick={onConfirm}>{labels.actionDelete}</button>
                        <button type="button" className="static-admin-cancel"
                            onClick={onCancel}>{labels.actionCancel}</button>
                    </div>
                </div>
            </div>
        );
    }
}

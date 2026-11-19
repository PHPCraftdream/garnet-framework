import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {useBodyScrollLock} from '@common/hooks/ui/useBodyScrollLock';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {Portal} from '@common/Components/Layout/Portal';

interface Props {
    title?: string;
    onClose: () => void;
    children: React.ReactNode;
}

export const LogDetailModal: React.FC<Props> = (props) => {
    useBodyScrollLock(true);
    return <LogDetailModalView {...props} />;
};

function stopPropagation(event: React.MouseEvent): void { event.stopPropagation(); }

function renderHeading(heading: string, onClose: Props['onClose']) {
    return (
        <div className="entity-history-header">
            <h3 className="entity-history-title">{heading}</h3>
            <button
                type="button"
                className="entity-history-close"
                title={t.LogDetail_Close()}
                aria-label={t.LogDetail_Close()}
                onClick={onClose}
                data-test-id="log-detail-modal-close"
            >
                &times;
            </button>
        </div>
    );
}

class LogDetailModalView extends AntiHookComponent<Props> {
    public componentDidMount(): void {
        super.componentDidMount();
        document.addEventListener('keydown', this.onKey);
    }

    public componentWillUnmount(): void {
        document.removeEventListener('keydown', this.onKey);
        super.componentWillUnmount();
    }

    private readonly onKey = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') this.props.onClose();
    };

    private readonly handleOverlayClick = (event: React.MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) this.props.onClose();
    };

    public render() {
        const {title, onClose, children} = this.props;
        const heading = title ?? t.LogDetail_Title();
        return (
            <Portal>
                <div
                    className="fg-modal-overlay-high"
                    onClick={this.handleOverlayClick}
                    data-test-id="log-detail-modal"
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label={heading}
                        className="entity-history-card"
                        onClick={stopPropagation}
                    >
                        {renderHeading(heading, onClose)}
                        <div className="entity-history-body">
                            {children}
                        </div>
                    </div>
                </div>
            </Portal>
        );
    }
}

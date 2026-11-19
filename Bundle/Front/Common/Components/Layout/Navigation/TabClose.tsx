import * as React from 'react';

interface Props {
    tabId: string;
    onClose?: (id: string) => void;
}

export class TabClose extends React.Component<Props> {
    private readonly handleClick = (event: React.MouseEvent<HTMLSpanElement>): void => {
        event.stopPropagation();
        this.props.onClose?.(this.props.tabId);
    };

    public render() {
        return (
            <span className="navigation-tab-close" title="Close tab" data-test-id={`tabnav-close-${this.props.tabId}`} onClick={this.handleClick}>
                ×
            </span>
        );
    }
}

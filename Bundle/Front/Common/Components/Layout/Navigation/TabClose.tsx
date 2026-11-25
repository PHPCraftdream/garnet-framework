import * as React from 'react';

interface Props {
    tabId: string;
    onClose?: (id: string) => void;
}

/** Renders the close control for a tab and invokes its optional close handler. */
export class TabClose extends React.Component<Props> {
    /** Stops tab selection and invokes the optional close callback. */
    private readonly handleClick = (event: React.MouseEvent<HTMLSpanElement>): void => {
        event.stopPropagation();
        this.props.onClose?.(this.props.tabId);
    };

    /** Renders the close control. */
    public render() {
        return (
            <span className="navigation-tab-close" title="Close tab"
                data-test-id={`tabnav-close-${this.props.tabId}`}
                onClick={this.handleClick}>
                ×
            </span>
        );
    }
}

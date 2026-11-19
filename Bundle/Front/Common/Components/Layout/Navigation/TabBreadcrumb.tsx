import * as React from 'react';
import type {TabDef} from './TabNav';

interface Props {
    breadcrumb: TabDef[];
    onSelect: (id: string) => void;
}

interface BreadcrumbButtonProps {
    tab: TabDef;
    onSelect: (id: string) => void;
}

class BreadcrumbButton extends React.Component<BreadcrumbButtonProps> {
    private readonly handleClick = (): void => { this.props.onSelect(this.props.tab.id); };

    public render() {
        const {tab} = this.props;
        return <button data-test-id={`breadcrumb-btn-${tab.id}`} className="breadcrumb-action" onClick={this.handleClick}>{tab.label}</button>;
    }
}

export class TabBreadcrumb extends React.Component<Props> {
    private readonly renderTab = (tab: TabDef, index: number): React.ReactNode => {
        const {breadcrumb, onSelect} = this.props;
        return (
            <React.Fragment key={tab.id}>
                {index > 0 && <span className="breadcrumb-separator">›</span>}
                {index < breadcrumb.length - 1 ? (
                    <BreadcrumbButton tab={tab} onSelect={onSelect} />
                ) : (
                    <span className="breadcrumb-current">{tab.label}</span>
                )}
            </React.Fragment>
        );
    };

    public render() {
        if (this.props.breadcrumb.length <= 1) return <div className="breadcrumb-placeholder" />;
        return <nav className="navigation-breadcrumb">{this.props.breadcrumb.map(this.renderTab)}</nav>;
    }
}

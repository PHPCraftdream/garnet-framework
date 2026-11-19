import * as React from 'react';
import {TabBreadcrumb} from './TabBreadcrumb';
import {TabClose} from './TabClose';

export interface TabDef {
    id: string;
    label: string;
    closeable?: boolean;
    parentId?: string; // enables automatic breadcrumb chain
}

interface Props {
    tabs: TabDef[];
    activeId: string;
    onSelect: (id: string) => void;
    onClose?: (id: string) => void;
}

function buildBreadcrumb(tabs: TabDef[], activeId: string): TabDef[] {
    const map = new Map<string, TabDef>();
    for (const tab of tabs) map.set(tab.id, tab);
    const chain: TabDef[] = [];
    let cur = map.get(activeId);
    while (cur) {
        chain.unshift(cur);
        cur = cur.parentId ? map.get(cur.parentId) : undefined;
    }
    return chain;
}

interface TabButtonProps {
    tab: TabDef;
    active: boolean;
    onSelect: (id: string) => void;
    onClose?: (id: string) => void;
}

class TabButton extends React.Component<TabButtonProps> {
    private readonly handleClick = (): void => { this.props.onSelect(this.props.tab.id); };

    public render() {
        const {tab, active, onClose} = this.props;
        return (
            <li className="tabnav-item">
                <button
                    className="tabnav-link"
                    data-test-id={`tabnav-btn-${tab.id}`}
                    aria-selected={active}
                    onClick={this.handleClick}
                >
                    {tab.label}
                    {tab.closeable && <TabClose tabId={tab.id} onClose={onClose} />}
                </button>
            </li>
        );
    }
}

export class TabNav extends React.Component<Props> {
    private readonly renderTab = (tab: TabDef): React.ReactNode => {
        const {activeId, onSelect, onClose} = this.props;
        return <TabButton key={tab.id} tab={tab} active={activeId === tab.id} onSelect={onSelect} onClose={onClose} />;
    };

    public render() {
        const {tabs, activeId, onSelect} = this.props;
        const breadcrumb = buildBreadcrumb(tabs, activeId);
        return (
            <>
                <ul className="tabnav-bar">{tabs.map(this.renderTab)}</ul>
                <TabBreadcrumb breadcrumb={breadcrumb} onSelect={onSelect} />
            </>
        );
    }
}

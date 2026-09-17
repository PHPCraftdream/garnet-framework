import * as React from 'react';

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
    const map = new Map(tabs.map(t => [t.id, t]));
    const chain: TabDef[] = [];
    let cur = map.get(activeId);
    while (cur) {
        chain.unshift(cur);
        cur = cur.parentId ? map.get(cur.parentId) : undefined;
    }
    return chain;
}

export const TabNav: React.FC<Props> = ({tabs, activeId, onSelect, onClose}) => {
    const breadcrumb = buildBreadcrumb(tabs, activeId);
    const showBreadcrumb = breadcrumb.length > 1;

    return (
        <>
            <ul className="tab-bar mb-0">
                {tabs.map(tab => (
                    <li key={tab.id} className="mr-1">
                        <button
                            className={`tab-link ${activeId === tab.id ? 'tab-link-active' : ''}`}
                            data-test-id={`tabnav-btn-${tab.id}`} aria-selected={activeId === tab.id} onClick={() => onSelect(tab.id)}
                        >
                            {tab.label}
                            {tab.closeable && (
                                <span
                                    className="text-muted hover:text-secondary leading-none text-base cursor-pointer"
                                    title="Close tab"
                                    data-test-id={`tabnav-close-${tab.id}`} onClick={e => { e.stopPropagation(); onClose?.(tab.id); }}
                                >
                                    ×
                                </span>
                            )}
                        </button>
                    </li>
                ))}
            </ul>

            {showBreadcrumb ? (
                <nav className="flex items-center gap-1 text-xs text-muted px-1 py-1.5 mb-3 border-b border-default">
                    {breadcrumb.map((tab, i) => (
                        <React.Fragment key={tab.id}>
                            {i > 0 && <span className="text-muted select-none">›</span>}
                            {i < breadcrumb.length - 1 ? (
                                <button
                                    data-test-id={`breadcrumb-btn-${tab.id}`}
                                    className="hover:text-accent transition-colors"
                                    onClick={() => onSelect(tab.id)}
                                >
                                    {tab.label}
                                </button>
                            ) : (
                                <span className="text-secondary font-medium">{tab.label}</span>
                            )}
                        </React.Fragment>
                    ))}
                </nav>
            ) : (
                <div className="mb-4" />
            )}
        </>
    );
};

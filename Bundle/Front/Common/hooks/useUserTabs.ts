import {useState, useCallback} from 'react';
import {TabDef} from '@common/Components/Navigation/TabNav';

export interface UserTabKind {
    kind: 'user-detail';
    accountId: number;
}

export interface UserInternalTab extends TabDef {
    tabKind: UserTabKind;
}

export function useUserTabs(parentId?: string) {
    const [tabs, setTabs]     = useState<UserInternalTab[]>([]);
    const [activeId, setActiveId] = useState<string | null>(null);

    const openUser = useCallback((accountId: number, label: string) => {
        const tabId = `user-${accountId}`;
        setTabs(prev => {
            if (prev.find(t => t.id === tabId)) return prev;
            return [...prev, {
                id: tabId, label, closeable: true,
                parentId,
                tabKind: {kind: 'user-detail', accountId},
            }];
        });
        setActiveId(tabId);
    }, [parentId]);

    const closeUser = useCallback((tabId: string) => {
        setTabs(prev => prev.filter(t => t.id !== tabId));
        setActiveId(prev => prev === tabId ? null : prev);
    }, []);

    return {userTabs: tabs, activeUserTabId: activeId, setActiveUserTabId: setActiveId, openUser, closeUser};
}

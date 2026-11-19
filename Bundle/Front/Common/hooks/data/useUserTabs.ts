import {useState, useCallback, useSyncExternalStore, Dispatch, SetStateAction} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {TabDef} from '@common/Components/Layout/Navigation/TabNav';

export interface UserTabKind {
    kind: 'user-detail';
    accountId: number;
}

export interface UserInternalTab extends TabDef {
    tabKind: UserTabKind;
}

type TabState = {tabs: UserInternalTab[]; activeId: string | null};

function createUserTabsStore(): UserTabsStore { return new UserTabsStore(); }

class UserTabsStore extends Carburetor<TabState> {
    constructor() { super({tabs: [], activeId: null}); }
    readonly snapshot = () => this.getData();
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    private patch(next: TabState) {
        const previous = this.getData();
        if (next.tabs === previous.tabs && next.activeId === previous.activeId) return;
        const version = this.getVersion();
        this.setData(next);
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    }
    readonly setActiveId: Dispatch<SetStateAction<string | null>> = value => {
        const previous = this.getData();
        const activeId = typeof value === 'function' ? value(previous.activeId) : value;
        this.patch({...previous, activeId});
    };
    readonly open = (accountId: number, label: string, parentId?: string) => {
        const previous = this.getData();
        const tabId = `user-${accountId}`;
        const tabs = previous.tabs.some(t => t.id === tabId) ? previous.tabs : [...previous.tabs, {
            id: tabId, label, closeable: true, parentId,
            tabKind: {kind: 'user-detail' as const, accountId},
        }];
        this.patch({tabs, activeId: tabId});
    };
    readonly close = (tabId: string) => {
        const previous = this.getData();
        this.patch({tabs: previous.tabs.filter(t => t.id !== tabId), activeId: previous.activeId === tabId ? null : previous.activeId});
    };
}

export function useUserTabs(parentId?: string) {
    const [store] = useState(createUserTabsStore);
    const {tabs, activeId} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    const openUser = useCallback((accountId: number, label: string) => {
        store.open(accountId, label, parentId);
    }, [store, parentId]);
    return {userTabs: tabs, activeUserTabId: activeId, setActiveUserTabId: store.setActiveId, openUser, closeUser: store.close};
}

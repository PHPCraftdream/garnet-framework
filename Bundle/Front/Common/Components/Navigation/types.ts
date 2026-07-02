export interface MenuItem {
    /** Stable identity for live-badge updates (e.g. 'bookings'). */
    id?: string;
    label: string;
    href: string;
    icon: string;
    active: boolean;
    badge?: number;
}

export interface UtilityClusterData {
    unreadMessages: number;
    unreadSupport: number;
    balance: number;
    messagesUrl: string;
    supportUrl: string;
    balanceUrl: string;
    profileUrl?: string;
    messagesLabel: string;
    supportLabel: string;
    balanceLabel: string;
    profileLabel?: string;
}

export interface TopMenuProps {
    menuItems: MenuItem[];
    utility?: UtilityClusterData;
}

export interface SidebarMenuProps {
    menuItems: MenuItem[];
    hasTopMenu?: boolean;
}

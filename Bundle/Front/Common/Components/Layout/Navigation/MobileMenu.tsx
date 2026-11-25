import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {Menu, X, LogOut, Wallet, Mail, MessagesSquare, User} from 'lucide-react';
import {MenuItem, UtilityClusterData, LiveBadgeConfig} from './types';
import {ThemeToggle} from '@common/Components/Controls/ThemeToggle';
import {sendPost} from '@common/Api/Send/sendPost';
import {goTo} from '@common/Dom/Nav/GoTo';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {useLiveCounts} from '@common/hooks/data/useLiveCounts';
import {LiveCounts} from '@common/Utils/Data/liveCounts/LiveCounts';
import {applyLiveCounts} from './liveBadges';
import {MobileMenuLink} from './MobileMenuLink';
import {NavigationUnreadDot} from './NavigationUnreadDot';

/** Props used to build the responsive navigation drawer. */
export interface MobileMenuProps {
    topItems: MenuItem[];
    sideItems: MenuItem[];
    utility?: UtilityClusterData;
    brand?: string;
    liveBadgeConfig?: LiveBadgeConfig;
}

type MobileMenuViewProps = MobileMenuProps & {live: LiveCounts | null};

/** Adds a positive item badge to the accumulated menu count. */
function addBadge(sum: number, item: MenuItem): number {
    return sum + (item.badge && item.badge > 0 ? item.badge : 0);
}

/** Redirects to the home page after logout completes. */
function navigateAfterLogout(): void {
    goTo('/');
}

/** Sends the logout request and redirects when it completes. */
const handleLogout = () => {
    void sendPost(window.location.pathname, {action: 'logout'}).then(navigateAfterLogout);
};

const renderTopItem = (item: MenuItem, index: number) => (
    <MobileMenuLink key={`t-${index}`} item={item} testIdPrefix="mobile-nav" />
);

const renderSideItem = (item: MenuItem, index: number) => (
    <MobileMenuLink key={`s-${index}`} item={item} testIdPrefix="mobile-sidebar" />
);

/** Renders the mobile top bar with its aggregate unread indicator. */
function renderTopbar(items: MenuItem[], utility?: UtilityClusterData) {
    const unread = (utility ? (utility.unreadMessages || 0) + (utility.unreadSupport || 0) : 0)
        + items.reduce(addBadge, 0);
    return (
        <header className="mobile-topbar">
            <a href="/"
                className="mobile-topbar-logo"
                data-no-hot
                aria-label={items[0]?.label}
                data-test-id="mobile-topbar-logo">
                <img src="/favicon.ico" alt="" width={26} height={26} />
            </a>
            <label htmlFor="show--menu" className="hamburger-btn" aria-label="Menu" data-test-id="mobile-menu-toggle">
                <Menu size={20} aria-hidden="true" />
                {unread > 0 && <NavigationUnreadDot />}
            </label>
        </header>
    );
}

/** Renders the optional balance, messages, support, and profile links. */
function renderUtility(utility?: UtilityClusterData) {
    if (!utility) return null;
    return (
        <div className="drawer-section-bordered">
            <a href={utility.balanceUrl} className="drawer-nav-link" data-test-id="mobile-drawer-balance">
                <Wallet size={18} aria-hidden="true" />
                <span>{utility.balanceLabel}: {new Intl.NumberFormat('ru-RU').format(utility.balance)} ₽</span>
            </a>
            <a href={utility.messagesUrl} className="drawer-nav-link" data-test-id="mobile-drawer-messages">
                <Mail size={18} aria-hidden="true" />
                <span>{utility.messagesLabel}{utility.unreadMessages > 0 ? ` (${utility.unreadMessages})` : ''}</span>
            </a>
            <a href={utility.supportUrl} className="drawer-nav-link" data-test-id="mobile-drawer-support">
                <MessagesSquare size={18} aria-hidden="true" />
                <span>{utility.supportLabel}{utility.unreadSupport > 0 ? ` (${utility.unreadSupport})` : ''}</span>
            </a>
            {utility.profileUrl && (
                <a href={utility.profileUrl} className="drawer-nav-link" data-test-id="mobile-drawer-profile">
                    <User size={18} aria-hidden="true" />
                    <span>{utility.profileLabel}</span>
                </a>
            )}
        </div>
    );
}

class MobileMenuView extends AntiHookComponent<MobileMenuViewProps> {
    private readonly menu = new Carburetor({open: false});

    /** Updates the drawer's checked state from its checkbox. */
    private readonly changeOpen = (event: React.ChangeEvent<HTMLInputElement>) => {
        this.menu.setData({open: event.currentTarget.checked});
    };

    /** Renders drawer navigation and its footer controls. */
    private renderDrawer(items: MenuItem[], utility?: UtilityClusterData) {
        const {sideItems} = this.props;
        return (
            <aside className="mobile-drawer" aria-label="Navigation drawer">
                <div className="drawer-header">
                    <span className="drawer-brand" />
                    <label htmlFor="show--menu" className="drawer-close" aria-label="Close menu"
                        data-test-id="mobile-menu-close">
                        <X size={20} aria-hidden="true" />
                    </label>
                </div>
                <nav className="drawer-nav">
                    {items && items.length > 0 && <div className="drawer-section">{items.map(renderTopItem)}</div>}
                    {sideItems && sideItems.length > 0 && (
                        <div className="drawer-section-bordered">{sideItems.map(renderSideItem)}</div>
                    )}
                    {renderUtility(utility)}
                </nav>
                <div className="drawer-footer">
                    <ThemeToggle testId="mobile-theme-toggle-btn" />
                    <button type="button" className="mobile-menu-logout" data-test-id="mobile-logout-btn"
                        title={I18nFramework.Auth_Logout()}
                        onClick={handleLogout}
                        aria-label={I18nFramework.Auth_Logout()}>
                        <LogOut size={18} aria-hidden="true" />
                    </button>
                </div>
            </aside>
        );
    }

    /** Renders the mobile menu when any navigation items are available. */
    public render() {
        const {open} = this.useCarburetor(this.menu);
        const {topItems, sideItems, utility, live, liveBadgeConfig} = this.props;
        if ((!topItems || topItems.length === 0) && (!sideItems || sideItems.length === 0)) return null;
        const applied = applyLiveCounts(topItems, utility, live, liveBadgeConfig);
        return (
            <>
                <input className="mobile-menu-checkbox" type="checkbox" id="show--menu"
                    checked={open} onChange={this.changeOpen} />
                {renderTopbar(applied.items, applied.util)}
                {this.renderDrawer(applied.items, applied.util)}
                <label htmlFor="show--menu" className="mobile-overlay" aria-hidden="true"
                    data-test-id="mobile-menu-overlay" />
            </>
        );
    }
}

/** Renders the responsive navigation menu with live unread and booking counts. */
export const MobileMenu: React.FC<MobileMenuProps> = props => {
    const live = useLiveCounts();
    return <MobileMenuView {...props} live={live} />;
};

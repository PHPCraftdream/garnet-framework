import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {MenuItem, TopMenuProps} from './types';
import {LogOut} from 'lucide-react';
import {sendPost} from '@common/Api/Send/sendPost';
import {ThemeToggle} from '@common/Components/Controls/ThemeToggle';
import {goTo} from '@common/Dom/Nav/GoTo';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {UtilityCluster} from './UtilityCluster';
import {NavigationDivider} from './NavigationDivider';
import {useLiveCounts} from '@common/hooks/data/useLiveCounts';
import {LiveCounts} from '@common/Utils/Data/liveCounts/LiveCounts';
import {applyLiveCounts} from './liveBadges';
import {TopMenuLink} from './TopMenuLink';

/** Redirects to the home page after logout completes. */
function navigateAfterLogout(): void {
    goTo('/');
}

/** Sends the logout request and redirects when it completes. */
function handleLogout(): void {
    void sendPost(window.location.pathname, {action: 'logout'}).then(navigateAfterLogout);
}

/** Renders one menu item in the top navigation list. */
function renderItem(item: MenuItem, index: number) {
    return <TopMenuLink key={index} item={item} />;
}

class TopMenuView extends AntiHookComponent<TopMenuProps & {live: LiveCounts | null}> {
    /** Renders the top bar and its navigation and utility controls. */
    public render() {
        const {menuItems, utility, liveBadgeConfig, live} = this.props;
        if (!menuItems || menuItems.length === 0) return null;
        const {items, util} = applyLiveCounts(menuItems, utility, live, liveBadgeConfig);
        return (
            <nav className="nav-topbar">
                <div className="nav-topbar-row">
                    <a href="/"
                        className="nav-topbar-logo" data-no-hot title={items[0]?.label} aria-label={items[0]?.label}
                        data-test-id="topbar-logo">
                        <img className="top-menu-logo-image" src="/favicon.ico" alt="" width={28} height={28} />
                    </a>
                    <ul className="nav-topbar-list">{items.map(renderItem)}</ul>
                    <div className="util-cluster-wrap">
                        {util && <UtilityCluster {...util} />}
                        <NavigationDivider />
                        <ThemeToggle />
                        <button type="button" className="top-menu-logout" data-test-id="logout-btn"
                            title={I18nFramework.Auth_Logout()}
                            onClick={handleLogout}
                            aria-label={I18nFramework.Auth_Logout()}>
                            <LogOut size={18} aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </nav>
        );
    }
}

/** Renders the top navigation menu with utility links and live counts. */
export const TopMenu: React.FC<TopMenuProps> = props => {
    const live = useLiveCounts();
    return <TopMenuView {...props} live={live} />;
};

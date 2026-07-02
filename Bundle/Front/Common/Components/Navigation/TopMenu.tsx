import * as React from 'react';
import {TopMenuProps} from './types';
import {menuIconMap} from '@common/Utils/LucideIcons';
import {LogOut} from 'lucide-react';
import {sendPost} from '@common/Api/sendPost';
import {ThemeToggle} from '@common/Components/ThemeToggle';
import {goTo} from '@common/Dom/Nav/GoTo';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {UtilityCluster} from './UtilityCluster';
import {useLiveCounts} from '@common/hooks/useLiveCounts';
import {applyLiveCounts} from './liveBadges';

function handleLogout() {
    sendPost(window.location.pathname, {action: 'logout'}).then(() => {
        goTo('/');
    });
}

export const TopMenu: React.FC<TopMenuProps> = ({menuItems, utility}) => {
    const live = useLiveCounts();
    if (!menuItems || menuItems.length === 0) return null;

    const {items, util} = applyLiveCounts(menuItems, utility, live);
    menuItems = items;
    utility = util;

    return (
        <nav className="nav-topbar">
            <div className="nav-topbar-row">
                <a
                    href="/"
                    className="nav-topbar-logo no-hot"
                    title={menuItems[0]?.label}
                    aria-label={menuItems[0]?.label}
                    data-test-id="topbar-logo"
                >
                    <img src="/favicon.ico" alt="" width={28} height={28} />
                </a>
                <ul className="nav-topbar-list">
                    {menuItems.map((item, i) => (
                        <li key={i}>
                            <a
                                href={item.href}
                                className={`hot-click ${item.active ? 'nav-top-link-active' : 'nav-top-link'}`}
                                title={item.label}
                                data-test-id={`nav-${item.label.toLowerCase().replace(/ /g, '-')}`}
                            >
                                {menuIconMap[item.icon] ? React.createElement(menuIconMap[item.icon], {size: 18}) : null}
                                <span>{item.label}</span>
                                {item.badge && item.badge > 0 ? <span className="count-badge-warning ms-1">{item.badge}</span> : null}
                            </a>
                        </li>
                    ))}
                </ul>

                <div className="util-cluster-wrap">
                    {utility && <UtilityCluster {...utility} />}
                    <div className="util-divider" aria-hidden="true" />
                    <ThemeToggle />
                    <button
                        type="button"
                        className="util-icon-btn util-icon-btn--danger"
                        data-test-id="logout-btn"
                        title={I18nFramework.Auth_Logout()}
                        onClick={handleLogout}
                        aria-label={I18nFramework.Auth_Logout()}
                    >
                        <LogOut size={18} aria-hidden="true" />
                    </button>
                </div>
            </div>
        </nav>
    );
};

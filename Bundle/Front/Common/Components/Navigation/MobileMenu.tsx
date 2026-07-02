import * as React from 'react';
import {Menu, X, LogOut, Wallet, Mail, MessagesSquare, User} from 'lucide-react';
import {MenuItem, UtilityClusterData} from './types';
import {menuIconMap} from '@common/Utils/LucideIcons';
import {ThemeToggle} from '@common/Components/ThemeToggle';
import {sendPost} from '@common/Api/sendPost';
import {goTo} from '@common/Dom/Nav/GoTo';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {useLiveCounts} from '@common/hooks/useLiveCounts';
import {applyLiveCounts} from './liveBadges';

export interface MobileMenuProps {
    topItems: MenuItem[];
    sideItems: MenuItem[];
    utility?: UtilityClusterData;
    brand?: string;
}

const formatRub = (amount: number): string =>
    new Intl.NumberFormat('ru-RU').format(amount);

function handleLogout() {
    sendPost(window.location.pathname, {action: 'logout'}).then(() => {
        goTo('/');
    });
}

const MenuLink: React.FC<{item: MenuItem; testIdPrefix: string}> = ({item, testIdPrefix}) => {
    const Icon = menuIconMap[item.icon];
    return (
        <a
            href={item.href}
            className={`drawer-nav-link${item.active ? ' active' : ''}`}
            data-test-id={`${testIdPrefix}-${item.label.toLowerCase().replace(/ /g, '-')}`}
        >
            {Icon ? <Icon size={18} aria-hidden="true" /> : null}
            <span>{item.label}</span>
            {item.badge && item.badge > 0 ? <span className="count-badge-warning ms-1">{item.badge}</span> : null}
        </a>
    );
};

export const MobileMenu: React.FC<MobileMenuProps> = ({topItems, sideItems, utility, brand}) => {
    const live = useLiveCounts();
    const hasItems = (topItems && topItems.length > 0) || (sideItems && sideItems.length > 0);
    if (!hasItems) return null;

    // Bookings live in topItems; overlay the live bookings badge + utility counts.
    const applied = applyLiveCounts(topItems, utility, live);
    topItems = applied.items;
    utility = applied.util;

    return (
        <>
            <input className="hidden" type="checkbox" id="show--menu" />

            {/* Mobile top bar — hamburger to the right, no brand, no balance pill */}
            <header className="mobile-topbar lg:hidden">
                <a
                    href="/"
                    className="mobile-topbar-logo no-hot"
                    aria-label={topItems[0]?.label}
                    data-test-id="mobile-topbar-logo"
                >
                    <img src="/favicon.ico" alt="" width={26} height={26} />
                </a>
                <label htmlFor="show--menu" className="hamburger-btn ml-auto" aria-label="Menu" data-test-id="mobile-menu-toggle">
                    <Menu size={20} aria-hidden="true" />
                </label>
            </header>

            {/* Drawer */}
            <aside className="mobile-drawer lg:hidden" aria-label="Navigation drawer">
                <div className="drawer-header">
                    <span className="drawer-brand" />
                    <label
                        htmlFor="show--menu"
                        className="drawer-close"
                        aria-label="Close menu"
                        data-test-id="mobile-menu-close"
                    >
                        <X size={20} aria-hidden="true" />
                    </label>
                </div>

                <nav className="drawer-nav">
                    {topItems && topItems.length > 0 && (
                        <div className="drawer-section">
                            {topItems.map((item, i) => (
                                <MenuLink key={`t-${i}`} item={item} testIdPrefix="mobile-nav" />
                            ))}
                        </div>
                    )}
                    {sideItems && sideItems.length > 0 && (
                        <div className="drawer-section drawer-section-bordered">
                            {sideItems.map((item, i) => (
                                <MenuLink key={`s-${i}`} item={item} testIdPrefix="mobile-sidebar" />
                            ))}
                        </div>
                    )}
                    {utility && (
                        <div className="drawer-section drawer-section-bordered">
                            <a href={utility.balanceUrl} className="drawer-nav-link" data-test-id="mobile-drawer-balance">
                                <Wallet size={18} aria-hidden="true" />
                                <span>{utility.balanceLabel}: {formatRub(utility.balance)} ₽</span>
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
                    )}
                </nav>

                <div className="drawer-footer">
                    <ThemeToggle testId="mobile-theme-toggle-btn" />
                    <button
                        type="button"
                        className="util-icon-btn util-icon-btn--danger"
                        data-test-id="mobile-logout-btn"
                        title={I18nFramework.Auth_Logout()}
                        onClick={handleLogout}
                        aria-label={I18nFramework.Auth_Logout()}
                    >
                        <LogOut size={18} aria-hidden="true" />
                    </button>
                </div>
            </aside>

            {/* Overlay (label = clicking it toggles checkbox off) */}
            <label
                htmlFor="show--menu"
                className="mobile-overlay lg:hidden"
                aria-hidden="true"
                data-test-id="mobile-menu-overlay"
            />
        </>
    );
};

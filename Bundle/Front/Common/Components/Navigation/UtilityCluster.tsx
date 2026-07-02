import * as React from 'react';
import {Wallet, Mail, MessagesSquare, User, type LucideIcon} from 'lucide-react';

export interface UtilityClusterProps {
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

const formatRub = (amount: number): string =>
    new Intl.NumberFormat('ru-RU').format(amount);

const matchesPath = (pathname: string, url: string): boolean => {
    if (!url) return false;
    const u = url.replace(/\/$/, '');
    if (u === '') return pathname === '/';
    return pathname === u || pathname.startsWith(u + '/') || pathname.startsWith(u + '~');
};

export const UtilityCluster: React.FC<UtilityClusterProps> = ({
    unreadMessages,
    unreadSupport,
    balance,
    messagesUrl,
    supportUrl,
    balanceUrl,
    profileUrl,
    messagesLabel,
    supportLabel,
    balanceLabel,
    profileLabel,
}) => {
    const pathname = typeof window !== 'undefined' ? window.location.pathname : '';
    const balanceActive = matchesPath(pathname, balanceUrl);
    const messagesActive = matchesPath(pathname, messagesUrl);
    const supportActive = matchesPath(pathname, supportUrl);
    const profileActive = profileUrl ? (matchesPath(pathname, profileUrl) || pathname.startsWith(profileUrl + '_')) : false;

    return (
        <div className="util-cluster">
            <a
                href={balanceUrl}
                className={`util-balance-pill hot-click${balanceActive ? ' util-balance-pill--active' : ''}`}
                title={balanceLabel}
                data-test-id="util-balance"
                aria-current={balanceActive ? 'page' : undefined}
                aria-label={`${balanceLabel}: ${formatRub(balance)} ₽`}
            >
                <Wallet size={16} className="util-balance-icon" aria-hidden="true" />
                <span className="util-balance-amount">{formatRub(balance)}</span>
                <span className="util-balance-currency" aria-hidden="true">₽</span>
            </a>

            <UtilityIconButton
                icon={Mail}
                href={messagesUrl}
                label={messagesLabel}
                count={unreadMessages}
                active={messagesActive}
                testId="util-messages"
            />

            <UtilityIconButton
                icon={MessagesSquare}
                href={supportUrl}
                label={supportLabel}
                count={unreadSupport}
                active={supportActive}
                testId="util-support"
            />

            {profileUrl && (
                <UtilityIconButton
                    icon={User}
                    href={profileUrl}
                    label={profileLabel ?? ''}
                    count={0}
                    active={profileActive}
                    testId="util-profile"
                />
            )}
        </div>
    );
};

interface UtilityIconButtonProps {
    icon: LucideIcon;
    href: string;
    label: string;
    count: number;
    active: boolean;
    testId: string;
}

const UtilityIconButton: React.FC<UtilityIconButtonProps> = ({
    icon: Icon,
    href,
    label,
    count,
    active,
    testId,
}) => {
    const hasBadge = count > 0;
    const display = count > 99 ? '99+' : String(count);

    return (
        <a
            href={href}
            className={`util-icon-btn hot-click${active ? ' util-icon-btn--active' : ''}`}
            title={label}
            data-test-id={testId}
            aria-current={active ? 'page' : undefined}
            aria-label={hasBadge ? `${label} (${count})` : label}
        >
            <Icon size={18} aria-hidden="true" />
            {hasBadge && (
                <span className="util-badge" aria-hidden="true">
                    {display}
                </span>
            )}
        </a>
    );
};

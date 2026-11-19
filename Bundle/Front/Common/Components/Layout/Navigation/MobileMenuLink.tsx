import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {menuIconMap} from '@common/Utils/Ui/LucideIcons';
import {MenuItem} from './types';
import {NavigationCountBadge} from './NavigationCountBadge';

export class MobileMenuLink extends AntiHookComponent<{item: MenuItem; testIdPrefix: string}> {
    public render() {
        const {item, testIdPrefix} = this.props;
        const Icon = menuIconMap[item.icon];
        return (
            <a
                href={item.href}
                className="drawer-nav-link" data-active={!!item.active}
                data-test-id={`${testIdPrefix}-${item.label.toLowerCase().replace(/ /g, '-')}`}
            >
                {Icon ? <Icon size={18} aria-hidden="true" /> : null}
                <span>{item.label}</span>
                {item.badge && item.badge > 0 ? <NavigationCountBadge count={item.badge} /> : null}
            </a>
        );
    }
}

import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {menuIconMap} from '@common/Utils/Ui/LucideIcons';
import {MenuItem} from './types';
import {NavigationCountBadge} from './NavigationCountBadge';

export class TopMenuLink extends AntiHookComponent<{item: MenuItem}> {
    public render() {
        const {item} = this.props;
        const Icon = menuIconMap[item.icon];
        return (
            <li className="top-menu-item">
                <a href={item.href}
                    data-hot-click
                    className={item.active ? 'nav-top-link-active' : 'nav-top-link'}
                    title={item.label} data-test-id={`nav-${item.label.toLowerCase().replace(/ /g, '-')}`}>
                    {Icon ? <Icon size={18} /> : null}
                    <span className="top-menu-label">{item.label}</span>
                    {item.badge && item.badge > 0 ? <NavigationCountBadge count={item.badge} /> : null}
                </a>
            </li>
        );
    }
}

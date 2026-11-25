import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {MenuItem, SidebarMenuProps} from './types';
import {menuIconMap} from '@common/Utils/Ui/menuIconMap';
import {NavigationCountBadge} from './NavigationCountBadge';

/** Renders a single linked entry in the sidebar navigation. */
class SidebarMenuItem extends AntiHookComponent<{item: MenuItem}> {
    /** Renders the menu item link. */
    public render() {
        const {item} = this.props;
        const Icon = menuIconMap[item.icon];
        return (
            <li className="sidebar-menu-item">
                <a href={item.href}
                    data-hot-click
                    className={item.active ? 'nav-side-link-active' : 'nav-side-link'}
                    title={item.label} data-test-id={`sidebar-${item.label.toLowerCase().replace(/ /g, '-')}`}>
                    {Icon ? <Icon size={20} /> : null}
                    <span className="nav-side-label">{item.label}</span>
                    {item.badge && item.badge > 0 ? <NavigationCountBadge count={item.badge} /> : null}
                </a>
            </li>
        );
    }
}

/** Renders an indexed menu item for the sidebar list. */
function renderItem(item: MenuItem, index: number) {
    return <SidebarMenuItem key={index} item={item} />;
}

/** Renders the primary sidebar navigation list. */
export class SidebarMenu extends AntiHookComponent<SidebarMenuProps> {
    /** Renders all sidebar menu entries. */
    public render() {
        const {menuItems} = this.props;
        return (
            <nav className="nav-sidebar">
                <ul className="nav-side-list">{menuItems && menuItems.map(renderItem)}</ul>
            </nav>
        );
    }
}

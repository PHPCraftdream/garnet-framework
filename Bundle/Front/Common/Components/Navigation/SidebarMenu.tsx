import * as React from 'react';
import {SidebarMenuProps} from './types';
import {menuIconMap} from '@common/Utils/LucideIcons';

export const SidebarMenu: React.FC<SidebarMenuProps> = ({menuItems}) => {
    return (
        <nav className="nav-sidebar">
            <ul className="nav-side-list">
                {menuItems && menuItems.map((item, i) => (
                    <li key={i}>
                        <a
                            href={item.href}
                            className={`hot-click ${item.active ? 'nav-side-link-active' : 'nav-side-link'}`}
                            title={item.label}
                            data-test-id={`sidebar-${item.label.toLowerCase().replace(/ /g, '-')}`}
                        >
                            {menuIconMap[item.icon] ? React.createElement(menuIconMap[item.icon], {size: 20}) : null}
                            <span className="nav-side-label side-title">{item.label}</span>
                            {item.badge && item.badge > 0 ? <span className="count-badge-warning ms-1">{item.badge}</span> : null}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
};

import * as React from 'react';
import {UserDetailContext} from '../AdminPanel/UserDetailContext';
import {appUrl} from '@common/Utils/appUrl';

/**
 * Generic admin user link — opens user detail tab via UserDetailContext,
 * falls back to href navigation when no context provider is present.
 *
 * Identical behavior to the MyApp-specific AdminUserLink, but lives in Common
 * so generic admin log components can use it without coupling to a particular app.
 */
interface Props {
    id: number;
    name: string;
    role?: string;
    className?: string;
}

export const AdminUserLink: React.FC<Props> = ({id, name, role, className = ''}) => {
    const {openUser} = React.useContext(UserDetailContext);
    if (!id) return <span className={className}>{name || '—'}</span>;

    const handleClick = (e: React.MouseEvent) => {
        e.stopPropagation();
        e.preventDefault();
        openUser(id, name || `#${id}`);
    };

    return (
        <span className={`common-entity-link ${className}`}>
            <a href={appUrl(`/admin/#user=${id}`)} className="common-link" onClick={handleClick}>{name || `#${id}`}</a>
            {role && <span className="common-role-tag">{role}</span>}
        </span>
    );
};

import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {UserDetailContext} from '@common/Components/Admin/AdminPanel/UserDetailContext';
import {appUrl} from '@common/Utils/Url/appUrl';

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
}

class AdminUserLinkView extends AntiHookComponent<Props & {openUser: React.ContextType<typeof
    UserDetailContext>['openUser']}> {
    /** Handles handleClick. */
    private readonly handleClick = (event: React.MouseEvent<HTMLAnchorElement>) => {
        const {id, name, openUser} = this.props;
        event.stopPropagation();
        event.preventDefault();
        openUser(id, name || `#${id}`);
    };

    /** Handles render. */
    render() {
        const {id, name, role} = this.props;
        if (!id) {
            return <span>{name || '—'}</span>;
        }
        return (
            <span className="common-entity-link">
                <a href={appUrl(`/admin/#user=${id}`)} className="common-link"
                    onClick={this.handleClick}>{name || `#${id}`}</a>
                {role && <span className="common-role-tag">{role}</span>}
            </span>
        );
    }
}

/** Handles AdminUserLink. */
export const AdminUserLink: React.FC<Props> = props => (
    <UserDetailContext.Consumer>
        {({openUser}) => <AdminUserLinkView {...props} openUser={openUser} />}
    </UserDetailContext.Consumer>
);

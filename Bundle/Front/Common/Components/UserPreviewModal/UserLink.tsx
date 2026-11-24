import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {usePreview} from './PreviewContext';
import {appUrl} from '@common/Utils/Url/appUrl';

interface UserLinkProps {
    id: number;
    name: string;
    isExpert?: boolean;
    profileUrl?: string;
    children?: React.ReactNode;
    onClick?: (e: React.MouseEvent<HTMLAnchorElement>) => void;
}

/**
 * Foreground-only link to a user/expert profile.
 * Inside a PreviewProvider tree, plain left-click opens the preview modal.
 * Modifier-clicks (Ctrl/Meta/Shift, middle button) and absent provider fall back
 * to navigation.
 *
 * @deprecated The `isExpert` prop is legacy. Use `profileUrl` for domain-agnostic routing.
 */
export const UserLink: React.FC<UserLinkProps> = (props) => {
    const preview = usePreview();
    return <UserLinkView {...props} preview={preview} />;
};

class UserLinkView extends AntiHookComponent<UserLinkProps & {preview: ReturnType<typeof usePreview>}> {
    private readonly handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
        const {onClick, preview, id, name} = this.props;
        onClick?.(e);
        if (e.defaultPrevented) return;
        if (preview && !e.metaKey && !e.ctrlKey && !e.shiftKey && e.button === 0) {
            e.preventDefault();
            e.stopPropagation();
            preview.openPreview(id, name);
        }
    };

    render() {
        const {id, name, isExpert, profileUrl, children} = this.props;
        if (!id) return <>{children ?? name}</>;
        const href = appUrl(profileUrl ?? (isExpert ? `/expert/id~${id}` : `/user/id~${id}`));
        return <a href={href} className="common-link" onClick={this.handleClick}>{children ?? name}</a>;
    }
}

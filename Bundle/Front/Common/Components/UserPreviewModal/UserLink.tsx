import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {usePreview} from './usePreview';
import {appUrl} from '@common/Utils/Url/appUrl';

const DEFAULT_ANCHOR = <a className="common-link" />;

interface UserLinkProps {
    id: number;
    name: string;
    isExpert?: boolean;
    profileUrl?: string;
    /** Template for the rendered anchor, e.g. `<a className="my-function" />`; defaults to the framework link. */
    anchor?: React.ReactElement<React.AnchorHTMLAttributes<HTMLAnchorElement>>;
    children?: React.ReactNode;
    onClick?: (e: React.MouseEvent<HTMLAnchorElement>) => void;
}

/** Render a foreground link to a user or expert profile.
 *
 * Inside a provider, plain left-click opens the preview; other clicks navigate.
 * Modifier-clicks and absent providers fall back to navigation.
 *
 * @deprecated The `isExpert` prop is legacy. Use `profileUrl` for domain-agnostic routing.
 */
export const UserLink: React.FC<UserLinkProps> = (props) => {
    const preview = usePreview();
    return <UserLinkView {...props} preview={preview} />;
};

class UserLinkView extends AntiHookComponent<UserLinkProps & {preview: ReturnType<typeof usePreview>}> {
    /** Handle navigation or open the preview for an unmodified left-click. */
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

    /** Render the user link with its profile destination. */
    render() {
        const {id, name, isExpert, profileUrl, anchor = DEFAULT_ANCHOR, children} = this.props;
        if (!id) return <>{children ?? name}</>;
        const href = appUrl(profileUrl ?? (isExpert ? `/expert/id~${id}` : `/user/id~${id}`));
        return React.cloneElement(anchor, {href, onClick: this.handleClick}, children ?? name);
    }
}

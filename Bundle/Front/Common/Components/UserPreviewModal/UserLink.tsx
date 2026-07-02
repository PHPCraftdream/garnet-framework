import * as React from 'react';
import {usePreview} from './PreviewContext';
import {appUrl} from '@common/Utils/appUrl';

interface UserLinkProps {
    id: number;
    name: string;
    isExpert?: boolean;
    className?: string;
    children?: React.ReactNode;
    onClick?: (e: React.MouseEvent<HTMLAnchorElement>) => void;
}

/**
 * Foreground-only link to a user/expert profile.
 * Inside an IrabiPreviewProvider tree, plain left-click opens the preview modal.
 * Modifier-clicks (Ctrl/Meta/Shift, middle button) and absent provider fall back
 * to navigation.
 */
export const UserLink: React.FC<UserLinkProps> = ({id, name, isExpert, className, children, onClick}) => {
    const preview = usePreview();
    if (!id) return <>{children ?? name}</>;
    const href = appUrl(isExpert ? `/expert/id~${id}` : `/user/id~${id}`);

    const handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
        onClick?.(e);
        if (e.defaultPrevented) return;
        if (preview && !e.metaKey && !e.ctrlKey && !e.shiftKey && e.button === 0) {
            e.preventDefault();
            e.stopPropagation();
            preview.openPreview(id, name);
        }
    };

    return (
        <a href={href} className={className ?? 'common-link'} onClick={handleClick}>
            {children ?? name}
        </a>
    );
};

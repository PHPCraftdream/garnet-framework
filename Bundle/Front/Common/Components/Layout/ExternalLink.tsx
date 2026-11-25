import * as React from 'react';

interface ExternalLinkProps extends Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, 'target' | 'rel' | 'referrerPolicy'> {
    /** Optional override for the rel attribute. Defaults are always merged in. */
    extraRel?: string;
}

/**
 * Securely opens an untrusted destination in a new tab through the gateway.
 *
 * The `/external?to=…` interstitial reveals the destination and limits access to
 * the opener document while retaining normal link behavior. Use a plain anchor
 * for trusted first-party links; this wrapper is for user or system input.
 */
export const ExternalLink: React.FC<ExternalLinkProps> = ({extraRel, children, href, ...props}) => {
    const rel = ['noopener', 'noreferrer', 'nofollow', extraRel].filter(Boolean).join(' ');
    const gatewayHref = href ? '/external?to=' + encodeURIComponent(href) : '#';
    return (
        <a {...props} href={gatewayHref} target="_blank" rel={rel} referrerPolicy="no-referrer">
            {children}
        </a>
    );
};

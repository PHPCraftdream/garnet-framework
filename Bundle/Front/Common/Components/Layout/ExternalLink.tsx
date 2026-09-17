import * as React from 'react';

interface ExternalLinkProps extends Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, 'target' | 'rel' | 'referrerPolicy'> {
    /** Optional override for the rel attribute. Defaults are always merged in. */
    extraRel?: string;
}

/**
 * Anchor that routes any **untrusted** href through our `/external?to=…`
 * interstitial page so the user sees the destination, can cancel, and the
 * destination's first request originates from our gateway page (which sets
 * `<meta name="referrer" content="no-referrer">`) — Referer is empty,
 * `window.opener` is null, our DOM is unreachable, all without triggering
 * popup blockers (it's a normal `target=_blank` link click).
 *
 * For first-party "open in new tab" (attachments, internal pages) a plain
 * `<a target="_blank" rel="noopener noreferrer">` is fine; this wrapper is
 * for hrefs that came from user input or external systems.
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

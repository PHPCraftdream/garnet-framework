import * as React from 'react';
import {TimezoneNotice} from './TimezoneNotice';

/**
 * Global warn-only TZ banner — mounted by HtmlLayout for every authenticated
 * user, so any page where the browser TZ disagrees with the profile TZ shows
 * a single bright notice without each island needing to opt in.
 *
 * Default export keeps the createIsland({exportName: 'default'}) wiring
 * symmetric with the rest of the island registry.
 */
export default function TimezoneNoticeWarnIsland(): React.JSX.Element {
    return <TimezoneNotice warnOnly />;
}

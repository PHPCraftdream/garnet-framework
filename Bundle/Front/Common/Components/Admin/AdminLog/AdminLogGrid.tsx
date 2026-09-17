import * as React from 'react';
import {AdminGrid as BaseAdminGrid, AdminGridProps} from '../AdminGrid/AdminGrid';

/**
 * Generic AdminGrid wrapper for the Common AdminLog components.
 * Currently just re-exports BaseAdminGrid — framework i18n is consumed
 * directly by BaseAdminGrid via `@framework/I18nGen/I18nFramework`.
 * Kept as a thin wrapper so callers can plug in log-specific renders later.
 */
export function AdminLogGrid<T>(props: AdminGridProps<T>) {
    return <BaseAdminGrid {...props} />;
}

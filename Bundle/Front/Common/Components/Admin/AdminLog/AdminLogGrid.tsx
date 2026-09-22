import * as React from 'react';
import {AdminGrid as BaseAdminGrid, AdminGridProps, AdminGridHandle} from '../AdminGrid/AdminGrid';

/**
 * Generic AdminGrid wrapper for the Common AdminLog components.
 * Currently just re-exports BaseAdminGrid — framework i18n is consumed
 * directly by BaseAdminGrid via `@framework/I18nGen/I18nFramework`.
 * Kept as a thin wrapper so callers can plug in log-specific renders later.
 */
function AdminLogGridInner<T>(props: AdminGridProps<T>, ref: React.ForwardedRef<AdminGridHandle<T>>) {
    return <BaseAdminGrid {...props} ref={ref} />;
}

export const AdminLogGrid = React.forwardRef(AdminLogGridInner) as <T>(
    props: AdminGridProps<T> & {ref?: React.ForwardedRef<AdminGridHandle<T>>},
) => ReturnType<typeof AdminLogGridInner>;

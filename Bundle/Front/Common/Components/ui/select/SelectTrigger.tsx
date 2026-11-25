import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';

/** Button that opens the select list, with a chevron. */
export const SelectTrigger = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Trigger>,
    Omit<React.ComponentPropsWithoutRef<typeof SelectPrimitive.Trigger>, 'className'>
>(({children, ...props}, ref) => (
    <SelectPrimitive.Trigger
        ref={ref}
        className="radix-select-trigger"
        {...props}
    >
        {children}
        <SelectPrimitive.Icon asChild>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </SelectPrimitive.Icon>
    </SelectPrimitive.Trigger>
));
SelectTrigger.displayName = 'SelectTrigger';

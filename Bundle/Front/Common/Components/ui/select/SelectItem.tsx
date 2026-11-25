import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';

/** Selectable option with a check mark on the chosen one. */
export const SelectItem = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Item>,
    Omit<React.ComponentPropsWithoutRef<typeof SelectPrimitive.Item>, 'className'>
>(({children, ...props}, ref) => (
    <SelectPrimitive.Item
        ref={ref}
        className="radix-select-item"
        {...props}
    >
        <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
        <span className="radix-select-indicator">
            <SelectPrimitive.ItemIndicator>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </SelectPrimitive.ItemIndicator>
        </span>
    </SelectPrimitive.Item>
));
SelectItem.displayName = 'SelectItem';

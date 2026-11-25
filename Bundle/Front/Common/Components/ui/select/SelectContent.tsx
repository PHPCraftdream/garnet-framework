import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';

/** Popup with the select items, rendered in a portal. */
export const SelectContent = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Content>,
    Omit<React.ComponentPropsWithoutRef<typeof SelectPrimitive.Content>, 'className'>
>(({children, position = 'popper', ...props}, ref) => (
    <SelectPrimitive.Portal>
        <SelectPrimitive.Content
            ref={ref}
            className="radix-select-content"
            data-popper={position === 'popper' ? '' : undefined}
            position={position}
            {...props}
        >
            <SelectPrimitive.Viewport
                className="radix-select-viewport"
                data-popper={position === 'popper' ? '' : undefined}
            >
                {children}
            </SelectPrimitive.Viewport>
        </SelectPrimitive.Content>
    </SelectPrimitive.Portal>
));
SelectContent.displayName = 'SelectContent';

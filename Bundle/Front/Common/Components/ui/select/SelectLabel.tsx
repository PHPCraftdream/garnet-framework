import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';

/** Caption of a group of select items. */
export const SelectLabel = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Label>,
    Omit<React.ComponentPropsWithoutRef<typeof SelectPrimitive.Label>, 'className'>
>((props, ref) => (
    <SelectPrimitive.Label
        ref={ref}
        className="radix-select-label"
        {...props}
    />
));
SelectLabel.displayName = 'SelectLabel';

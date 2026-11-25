import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';

/** Accessible description of the dialog. */
export const DialogDescription = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Description>,
    Omit<React.ComponentPropsWithoutRef<typeof DialogPrimitive.Description>, 'className'>
>((props, ref) => (
    <DialogPrimitive.Description
        ref={ref}
        className="radix-dialog-description"
        {...props}
    />
));
DialogDescription.displayName = 'DialogDescription';

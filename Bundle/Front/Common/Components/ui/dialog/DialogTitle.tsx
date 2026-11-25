import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';

/** Accessible title of the dialog. */
export const DialogTitle = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Title>,
    Omit<React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>, 'className'>
>((props, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className="radix-dialog-title"
        {...props}
    />
));
DialogTitle.displayName = 'DialogTitle';

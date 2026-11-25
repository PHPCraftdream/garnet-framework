import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';

const Dialog = DialogPrimitive.Root;

const DialogPortal = DialogPrimitive.Portal;

const DialogOverlay = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Overlay>,
    Omit<React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>, 'className'>
>((props, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className="radix-dialog-overlay"
        {...props}
    />
));
DialogOverlay.displayName = 'DialogOverlay';

const DialogContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    Omit<React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content>, 'className'>
>(({children, ...props}, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            className="radix-dialog-content"
            {...props}
        >
            {children}
            <DialogPrimitive.Close className="radix-dialog-close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
                <span className="radix-dialog-close-label">Close</span>
            </DialogPrimitive.Close>
        </DialogPrimitive.Content>
    </DialogPortal>
));
DialogContent.displayName = 'DialogContent';

const DialogTitle = React.forwardRef<
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

const DialogDescription = React.forwardRef<
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

export {Dialog};

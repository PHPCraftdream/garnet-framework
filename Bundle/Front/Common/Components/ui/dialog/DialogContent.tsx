import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';

/** Dimmed backdrop rendered behind the dialog content. */
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

/** Dialog body in a portal with an overlay and a close button. */
export const DialogContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    Omit<React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content>, 'className'>
>(({children, ...props}, ref) => (
    <DialogPrimitive.Portal>
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
    </DialogPrimitive.Portal>
));
DialogContent.displayName = 'DialogContent';

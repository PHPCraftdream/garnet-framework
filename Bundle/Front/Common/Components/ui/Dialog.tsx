import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import {cn} from '@common/Utils/Ui/cn';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogClose = DialogPrimitive.Close;

const DialogPortal = DialogPrimitive.Portal;

const DialogOverlay = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Overlay>,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
>(({className, ...props}, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        // Внешний className сохраняется для совместимости.
        // carburetor-disable-next-line require-function-class
        className={cn('radix-dialog-overlay', className)}
        {...props}
    />
));
DialogOverlay.displayName = 'DialogOverlay';

const DialogContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content>
>(({className, children, ...props}, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            // Внешний className сохраняется для совместимости.
            // carburetor-disable-next-line require-function-class
            className={cn('radix-dialog-content', className)}
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

const DialogHeader: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({className, ...props}) => (
    // Внешний className сохраняется для совместимости.
    // carburetor-disable-next-line require-function-class
    <div className={cn('radix-dialog-header', className)} {...props} />
);

const DialogTitle = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Title>,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({className, ...props}, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        // Внешний className сохраняется для совместимости.
        // carburetor-disable-next-line require-function-class
        className={cn('radix-dialog-title', className)}
        {...props}
    />
));
DialogTitle.displayName = 'DialogTitle';

const DialogDescription = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Description>,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({className, ...props}, ref) => (
    <DialogPrimitive.Description
        ref={ref}
        // Внешний className сохраняется для совместимости.
        // carburetor-disable-next-line require-function-class
        className={cn('radix-dialog-description', className)}
        {...props}
    />
));
DialogDescription.displayName = 'DialogDescription';

export {Dialog, DialogTrigger, DialogClose, DialogContent, DialogHeader, DialogTitle, DialogDescription};

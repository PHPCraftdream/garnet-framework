import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';

const Select = SelectPrimitive.Root;

const SelectTrigger = React.forwardRef<
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

const SelectContent = React.forwardRef<
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

const SelectItem = React.forwardRef<
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

const SelectLabel = React.forwardRef<
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

export {Select};

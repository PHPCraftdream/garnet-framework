import * as React from 'react';
import * as TooltipPrimitive from '@radix-ui/react-tooltip';

const TooltipRoot = TooltipPrimitive.Root;

const TooltipContent = React.forwardRef<
    React.ElementRef<typeof TooltipPrimitive.Content>,
    Omit<React.ComponentPropsWithoutRef<typeof TooltipPrimitive.Content>, 'className'>
>(({sideOffset = 4, ...props}, ref) => (
    <TooltipPrimitive.Portal>
        <TooltipPrimitive.Content
            ref={ref}
            sideOffset={sideOffset}
            className="radix-tooltip-content"
            {...props}
        />
    </TooltipPrimitive.Portal>
));
TooltipContent.displayName = 'TooltipContent';

export {TooltipRoot as Tooltip};

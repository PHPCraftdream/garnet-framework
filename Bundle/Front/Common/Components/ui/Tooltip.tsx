import * as React from 'react';
import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import {cn} from '@common/Utils/cn';

const TooltipProvider = TooltipPrimitive.Provider;
const TooltipRoot = TooltipPrimitive.Root;
const TooltipTrigger = TooltipPrimitive.Trigger;

const TooltipContent = React.forwardRef<
    React.ElementRef<typeof TooltipPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof TooltipPrimitive.Content>
>(({className, sideOffset = 4, ...props}, ref) => (
    <TooltipPrimitive.Portal>
        <TooltipPrimitive.Content
            ref={ref}
            sideOffset={sideOffset}
            className={cn(
                'z-50 overflow-hidden rounded-lg border px-3 py-2 text-xs shadow-md animate-in fade-in-0 zoom-in-95',
                'border-default bg-surface text-on-surface',
                className,
            )}
            {...props}
        />
    </TooltipPrimitive.Portal>
));
TooltipContent.displayName = 'TooltipContent';

export {TooltipProvider, TooltipRoot as Tooltip, TooltipTrigger, TooltipContent};

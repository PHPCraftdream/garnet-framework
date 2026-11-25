import {createPortal} from 'react-dom';
import type {ReactNode, FC} from 'react';

/** Renders children in the document body. */
export const Portal: FC<{children: ReactNode}> = ({children}) =>
    createPortal(children, document.body);

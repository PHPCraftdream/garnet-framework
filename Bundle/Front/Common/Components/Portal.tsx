import {createPortal} from 'react-dom';
import type {ReactNode, FC} from 'react';

export const Portal: FC<{children: ReactNode}> = ({children}) =>
    createPortal(children, document.body);

import * as React from 'react';
import {UserDetailContext} from './UserDetailContext';

/** Returns the callback for opening a user's detail view. */
export const useOpenUser = (): ((accountId: number, label: string) => void) =>
    React.useContext(UserDetailContext).openUser;

import * as React from 'react';

export interface UserDetailContextValue {
    openUser: (accountId: number, label: string) => void;
}

export const UserDetailContext = React.createContext<UserDetailContextValue>({
    openUser: () => {},
});

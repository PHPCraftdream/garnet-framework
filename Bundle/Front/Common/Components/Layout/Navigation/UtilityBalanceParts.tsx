import * as React from 'react';
import {Wallet} from 'lucide-react';

interface Props {
    amount: string;
}

/** Visual content of the balance link. */
export function UtilityBalanceParts({amount}: Props) {
    return (
        <>
            <Wallet size={16} className="util-balance-icon" aria-hidden="true" />
            <span className="util-balance-amount">{amount}</span>
            <span className="util-balance-currency" aria-hidden="true">₽</span>
        </>
    );
}

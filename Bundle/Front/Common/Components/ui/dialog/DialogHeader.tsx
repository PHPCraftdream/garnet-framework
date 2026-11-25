import * as React from 'react';

/** Container for the dialog title and description. */
export const DialogHeader: React.FC<Omit<React.HTMLAttributes<HTMLDivElement>, 'className'>> = (props) => (
    <div className="radix-dialog-header" {...props} />
);

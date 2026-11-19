import * as React from 'react';

interface Props {
    value: string;
    placeholder: string;
    onChange: React.ChangeEventHandler<HTMLInputElement>;
}

/** Controlled search presentation; the grid owns query and fetching. */
export function AdminGridSearch({value, placeholder, onChange}: Props) {
    return (
        <div className="admin-grid-search-wrap">
            <input
                type="search"
                data-test-id="admin-grid-search"
                className="admin-grid-search-input"
                placeholder={placeholder}
                value={value}
                onChange={onChange}
            />
        </div>
    );
}

import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';

const DURATION_OPTIONS = [30, 45, 60, 90, 120];

interface Props {
    value: number;
    onChange: (value: number) => void;
    variant?: 'compact';
    name?: string;
    id?: string;
    'data-test-id'?: string;
}

const renderOption = (value: number) => <option key={value} value={value}>{value}</option>;

export class DurationSelect extends AntiHookComponent<Props> {
    private readonly handleChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        this.props.onChange(parseInt(event.target.value));
    };

    public render() {
        const {value, variant, name, id} = this.props;
        return (
            <select
                className={variant === 'compact' ? 'duration-select-compact' : 'duration-select'}
                value={value}
                onChange={this.handleChange}
                name={name}
                id={id}
                data-test-id={this.props['data-test-id']}
            >
                {DURATION_OPTIONS.map(renderOption)}
            </select>
        );
    }
}

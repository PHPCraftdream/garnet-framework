import * as React from 'react';
import {FormErrorStore} from './FormErrorStore';

/** Context used by form fields to access validation errors. */
export const FormErrorContext = React.createContext<FormErrorStore | null>(null);

import {EPhase} from '@framework/auth/Enums';
import {MutableRefObject} from 'react';

export interface ICodeRequest {
    auth_email: string;
}

export interface ICodeRequestResponse {
    message: string;
    codeLifeTime: number;
    codeInputTries: number;
}

export interface ICheckCodeRequest {
    code: string;
}

export interface ICheckCodeRequestResponse {
    success: boolean;
    codeInputTries: number;
    codeLifeTime: number;
    timeout?: boolean;
}

export interface IAuthData {
    phase: EPhase;
    codeInputTries?: number;
    codeLifeTime?: number;
    hint?: string;
    isSendingRequest?: boolean;
    title?: string;
}

export type TSetAuthState = (value: (((prevState: IAuthData) => IAuthData) | IAuthData)) => void;
export type TInputRef = MutableRefObject<HTMLInputElement>;

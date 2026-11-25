import {IApiResponse} from '@common/Support/Models';

/** Represents a failed API request and its response details. */
export class ApiError extends Error {
    public status: number;
    public response: IApiResponse | string | null;

    /** Creates an API error with the response status and payload. */
    constructor(message: string, status: number, response?: IApiResponse | string | null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.response = response ?? null;
    }
}

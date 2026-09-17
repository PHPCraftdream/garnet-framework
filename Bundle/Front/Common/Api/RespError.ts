import {IApiResponse} from '@common/Support/Models';

export class RespError extends Error {
    public status: number = 0;
    public response: IApiResponse | string | null = null;
}

import {IApiResponse} from '@common/Models';

export class RespError extends Error {
    public status: number = 0;
    public response: IApiResponse | string | null = null;
}

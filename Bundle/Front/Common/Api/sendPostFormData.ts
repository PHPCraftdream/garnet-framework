import {IGarnetWindow, IApiResponse, TProgressHandler} from '@common/Models';
import {isMaintenance503} from '@common/Api/maintenance503';

const w: IGarnetWindow = window as IGarnetWindow;

export class ApiError extends Error {
    public status: number;
    public response: IApiResponse | string | null;

    constructor(message: string, status: number, response?: IApiResponse | string | null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.response = response ?? null;
    }
}

export const sendPostFormData = <Request extends FormData, Response extends {}>(
    url: string,
    formData: Request,
    onProgress?: TProgressHandler,
): Promise<Response> => {
    if (w.__GARNET_CSRF__) {
        formData.append('CSRF_TOKEN', w.__GARNET_CSRF__);
    }

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        if (onProgress) {
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const progress = Math.round((event.loaded / event.total) * 100);
                    onProgress(progress);
                }
            };
        }

        xhr.onload = () => {
            // Maintenance (503): raise the shared toast, reject with a tagged error.
            if (isMaintenance503(xhr.status)) {
                reject(new ApiError('Common_Maintenance', 503, {maintenance: true} as unknown as IApiResponse));
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300) {
                let responseData: IApiResponse | string;

                try {
                    responseData = JSON.parse(xhr.response) as IApiResponse;
                } catch {
                    responseData = xhr.response;
                }

                reject(new ApiError(
                    `Request failed with status ${xhr.status}`,
                    xhr.status,
                    responseData
                ));

                return;
            }

            try {
                resolve(JSON.parse(xhr.response));
            } catch {
                resolve(xhr.response as Response);
            }
        };

        xhr.onerror = () => {
            reject(new ApiError('Network error or connection failed', 0));
        };

        xhr.ontimeout = () => {
            reject(new ApiError('Request timeout', 0));
        };

        xhr.open('POST', url);
        xhr.send(formData);
    })
}

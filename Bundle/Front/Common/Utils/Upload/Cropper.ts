export const cropperOptions = (): Record<string, number | boolean | string> => {
    return {
        aspectRatio: 1,
        autoCrop: true,
        dragMode: 'none',
        movable: false,
        scalable: false,
        viewMode: 2,
        zoomable: false,
    };
};

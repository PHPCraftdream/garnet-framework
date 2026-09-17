export const secondsToTime = (seconds: number): string => {
    return (new Date(seconds * 1000)).toISOString().substring(14, 19);
};

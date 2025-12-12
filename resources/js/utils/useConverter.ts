
function camelToSnake(str: string): string {
    return str.replace(/([A-Z])/g, "_$1").toLowerCase();
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function convertKeysToSnakeCase(obj: any): any {
    if (obj instanceof FormData) {
        return obj;
    }
    if (Array.isArray(obj)) {
        return obj.map(convertKeysToSnakeCase);
    } else if (obj !== null && typeof obj === "object") {
        return Object.fromEntries(
            Object.entries(obj).map(([key, value]) => [
                camelToSnake(key),
                convertKeysToSnakeCase(value),
            ]),
        );
    }
    return obj;
}

function snakeToCamel(str: string): string {
    return str.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function convertKeysToCamelCase(obj: object): any {
    if (Array.isArray(obj)) {
        return obj.map(convertKeysToCamelCase);
    } else if (obj !== null && typeof obj === "object") {
        return Object.fromEntries(
            Object.entries(obj).map(([key, value]) => [
                snakeToCamel(key),
                convertKeysToCamelCase(value),
            ]),
        );
    }
    return obj;
}

export function kebabToCamel(str: string): string {
    return str.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
}

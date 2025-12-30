import { RouteGuard } from '@/shared/interfaces';

export default async function auth({ to, next, store }: RouteGuard): Promise<void> {
    const uuid = to?.query?.uuid ? to?.query?.uuid as string : store.uuid;
    if (uuid && !store.user) {
        store.setUuid(uuid);
    }
    return next();
}

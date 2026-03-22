import type { User } from '@/types/auth';

export interface Notification {
    id: number;
    unread?: boolean;
    sender: User;
    body: string;
    date: string;
}

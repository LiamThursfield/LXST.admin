export interface Notification {
    id: number;
    unread?: boolean;
    sender: User;
    body: string;
    date: string;
}

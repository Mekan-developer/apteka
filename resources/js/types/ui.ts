export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type NotificationType = 'success' | 'info' | 'warning' | 'error';

export type AppNotification = {
    type: NotificationType;
    message: string;
};

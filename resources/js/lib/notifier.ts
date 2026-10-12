import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { AppNotification, NotificationType } from '@/types/ui';

const sounds: Partial<Record<NotificationType, HTMLAudioElement>> = {};

function playSound(type: NotificationType): void {
    if (typeof Audio === 'undefined') {
        return;
    }

    const audio = (sounds[type] ??= new Audio(`/sounds/${type}.mp3`));

    audio.currentTime = 0;

    // A missing file or the browser's autoplay policy must never break the UI.
    audio.play().catch(() => {});
}

function show(type: NotificationType, message: string): void {
    toast[type](message);
    playSound(type);
}

/**
 * Global notifier: a coloured toast plus a status sound.
 * Messages must already be translated, e.g. notify.error(t('notify.unexpected_error')).
 */
export const notify = {
    success: (message: string): void => show('success', message),
    error: (message: string): void => show('error', message),
    warning: (message: string): void => show('warning', message),
    info: (message: string): void => show('info', message),
};

/**
 * Show the "notify" flash sent by the backend notify() helper.
 */
export function initializeNotifier(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const notification = flash?.notify as AppNotification | undefined;

        if (!notification) {
            return;
        }

        show(notification.type, notification.message);
    });
}

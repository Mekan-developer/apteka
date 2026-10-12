<?php

if (! function_exists('notify')) {
    /**
     * Flash a toast notification for the next Inertia page.
     *
     * The type is one of success, error, warning or info. The message must already
     * be localized, e.g. notify('success', __('messages.profile_updated')).
     *
     * @throws InvalidArgumentException
     */
    function notify(string $type, string $message): void
    {
        if (! in_array($type, ['success', 'error', 'warning', 'info'], true)) {
            throw new InvalidArgumentException("Unknown notification type [{$type}].");
        }

        session()->flash('notify', ['type' => $type, 'message' => $message]);
    }
}

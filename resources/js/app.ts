import { createInertiaApp } from '@inertiajs/svelte';
import AppLayout from '@/layouts/AppLayout.svelte';
import AuthLayout from '@/layouts/AuthLayout.svelte';
import DashboardSettingsLayout from '@/layouts/settings/DashboardSettingsLayout.svelte';
import { initializeFlashToast } from '@/lib/flash-toast';
import { initializeTheme } from '@/lib/theme.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Apply theme before anything renders so the splash screen gets the correct background
initializeTheme();

const splashStart = Date.now();

function dismissSplash() {
    if (typeof document === 'undefined') return;
    const splash = document.getElementById('splash-screen');
    if (!splash) return;
    const elapsed = Date.now() - splashStart;
    const delay = Math.max(0, 900 - elapsed);
    setTimeout(() => {
        splash.style.opacity = '0';
        setTimeout(() => splash.remove(), 500);
    }, delay);
}

// Hard fallback: never leave the splash screen stuck if Inertia fails to mount
if (typeof document !== 'undefined') setTimeout(dismissSplash, 5000);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Dashboard':
            case name === 'Messages':
            case name === 'Notifications':
            case name === 'FlockAI':
            case name === 'Error':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('posts/'):
            case name.startsWith('users/'):
                return null;
            case name.startsWith('settings/'):
                return DashboardSettingsLayout;
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#000000',
        includeCSS: false,
    },
}).then(dismissSplash);

// This will listen for flash toast data from the server...
initializeFlashToast();

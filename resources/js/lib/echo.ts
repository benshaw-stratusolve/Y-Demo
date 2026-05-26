import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const isBrowser = typeof window !== 'undefined';

if (isBrowser) {
    (window as any).Pusher = Pusher;
}

export const echo: Echo = isBrowser
    ? new Echo({
          broadcaster: 'reverb',
          key: import.meta.env.VITE_REVERB_APP_KEY as string,
          wsHost: (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? 'localhost',
          wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
          wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
          forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
          enabledTransports: ['ws', 'wss'],
      })
    : (null as unknown as Echo);

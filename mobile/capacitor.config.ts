import type { CapacitorConfig } from '@capacitor/cli';

// The app is a native shell around the live site: the site stays the single source of truth,
// the shell adds what a plain browser cannot (push notifications, native status bar, app icon, splash).
// Set DEMS_URL to the production address before `cap sync`.
const url = process.env.DEMS_URL || 'https://moderationdems-1.onrender.com';

const config: CapacitorConfig = {
    appId: 'za.ac.cput.moderation', // change to the bundle id registered in your Apple Developer account
    appName: 'Moderation',
    webDir: 'www',
    server: {
        url,
        cleartext: false,
        allowNavigation: [new URL(url).host],
    },
    ios: {
        contentInset: 'always',
        limitsNavigationsToAppBoundDomains: true,
        backgroundColor: '#FFFFFF',
    },
    plugins: {
        SplashScreen: { launchAutoHide: true, backgroundColor: '#006699' },
        PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
    },
};

export default config;

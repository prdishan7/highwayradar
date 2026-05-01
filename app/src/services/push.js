import { PushNotifications } from '@capacitor/push-notifications';
import { LocalNotifications } from '@capacitor/local-notifications';
import { Capacitor } from '@capacitor/core';
import { apiRequest } from './api';

export async function initPush() {
    const isPushSupported = Capacitor.isPluginAvailable('PushNotifications');

    if (!isPushSupported) {
        console.log('Push notifications not supported on this platform');
        return;
    }

    // Request permission
    let perm = await PushNotifications.checkPermissions();
    if (perm.receive !== 'granted') {
        perm = await PushNotifications.requestPermissions();
    }

    if (perm.receive !== 'granted') {
        console.warn('Push notification permission denied');
        return;
    }

    // Register with Apple / Google for a token
    await PushNotifications.register();

    // Listeners
    PushNotifications.addListener('registration', async (token) => {
        console.log('Push registration successful, token:', token.value);
        try {
            await apiRequest('/notifications', 'POST', {
                token: token.value,
                platform: Capacitor.getPlatform()
            });
        } catch (e) {
            console.error('Failed to register token with backend', e);
        }
    });

    PushNotifications.addListener('registrationError', (error) => {
        console.error('Push registration error:', error);
    });

    // Handle incoming notifications while app is in foreground
    PushNotifications.addListener('pushNotificationReceived', async (notification) => {
        console.log('Push received (foreground):', notification);

        // Show local notification as a fallback for foreground
        await LocalNotifications.schedule({
            notifications: [
                {
                    title: notification.title || 'New Alert',
                    body: notification.body || 'Hazard detected on highway.',
                    id: Math.floor(Math.random() * 10000),
                    schedule: { at: new Date(Date.now() + 100) },
                    sound: null,
                    attachments: null,
                    actionTypeId: '',
                    extra: null
                }
            ]
        });
    });

    PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
        console.log('Push action performed:', notification);
        // Could navigate to /community here
    });
}

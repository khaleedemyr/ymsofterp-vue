// Firebase Configuration for Web Push Notifications
import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";

// Your web app's Firebase configuration
const firebaseConfig = {
  apiKey: "AIzaSyC1-A8gZjh7HVDn5Qd0eO4qAItRb5eLi5c",
  authDomain: "justusgroup-46e18.firebaseapp.com",
  projectId: "justusgroup-46e18",
  storageBucket: "justusgroup-46e18.firebasestorage.app",
  messagingSenderId: "218092221068",
  appId: "1:218092221068:web:c1bd083b06fd516c4701a9",
  measurementId: "G-7GMJK585VF"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);

// Initialize Firebase Cloud Messaging and get a reference to the service
let messaging = null;

// Only initialize messaging in browser environment (not SSR)
if (typeof window !== 'undefined') {
  try {
    // Initialize messaging (service worker will be registered separately)
    messaging = getMessaging(app);
  } catch (error) {
    console.error('Error initializing Firebase Messaging:', error);
  }
}

// VAPID Key for web push notifications
// Get this from Firebase Console > Project Settings > Cloud Messaging > Web Push certificates
const VAPID_KEY = 'BGqXuQB8leSCOybGsFN77kp5GIRvivIBV00jBQyWon3p8wZ6IPasD8j1hisQvLAuT9hfR45dL61vCJxw2a5Gt3M'; // TODO: Replace with actual VAPID key from Firebase Console

/**
 * Request notification permission and get FCM token
 */
export async function requestNotificationPermission() {
  if (!messaging) {
    console.warn('Firebase Messaging not initialized');
    return null;
  }

  try {
    // Register service worker first (required for background notifications)
    if ('serviceWorker' in navigator) {
      try {
        const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        console.log('Service Worker registered:', registration.scope);
      } catch (error) {
        console.error('Service Worker registration failed:', error);
        // Continue anyway - foreground notifications will still work
      }
    }
    
    // Request permission
    const permission = await Notification.requestPermission();
    
    if (permission === 'granted') {
      console.log('Notification permission granted');
      
      // Get FCM token (service worker should be ready by now)
      const token = await getToken(messaging, {
        vapidKey: VAPID_KEY
      });
      
      if (token) {
        console.log('FCM Token obtained:', token.substring(0, 20) + '...');
        return token;
      } else {
        console.warn('No FCM token available');
        return null;
      }
    } else {
      console.warn('Notification permission denied');
      return null;
    }
  } catch (error) {
    console.error('Error requesting notification permission:', error);
    return null;
  }
}

/**
 * Register FCM token to backend
 */
export async function registerTokenToBackend(token) {
  try {
    const response = await fetch('/api/web/device-token/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        device_token: token,
        browser: getBrowserName(),
        user_agent: navigator.userAgent
      })
    });

    const data = await response.json();
    
    if (data.success) {
      console.log('FCM token registered successfully:', data);
      return data;
    } else {
      console.error('Failed to register token:', data.message);
      return null;
    }
  } catch (error) {
    console.error('Error registering token to backend:', error);
    return null;
  }
}

/**
 * Unregister FCM token from backend
 */
export async function unregisterTokenFromBackend(token) {
  try {
    const response = await fetch('/api/web/device-token/unregister', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        device_token: token
      })
    });

    const data = await response.json();
    
    if (data.success) {
      console.log('FCM token unregistered successfully');
      return data;
    } else {
      console.error('Failed to unregister token:', data.message);
      return null;
    }
  } catch (error) {
    console.error('Error unregistering token from backend:', error);
    return null;
  }
}

/**
 * Get browser name
 */
function getBrowserName() {
  const ua = navigator.userAgent;
  if (ua.includes('Chrome') && !ua.includes('Edg')) return 'Chrome';
  if (ua.includes('Firefox')) return 'Firefox';
  if (ua.includes('Safari') && !ua.includes('Chrome')) return 'Safari';
  if (ua.includes('Edg')) return 'Edge';
  return 'Unknown';
}

/**
 * Handle foreground messages (when app is open)
 */
export function setupForegroundMessageHandler() {
  if (!messaging) {
    console.warn('Firebase Messaging not initialized');
    return;
  }

  onMessage(messaging, (payload) => {
    console.log('Foreground message received:', payload);
    
    const notificationTitle = payload.notification?.title || 'New Notification';
    const notificationOptions = {
      body: payload.notification?.body || '',
      icon: '/icon-192x192.png', // TODO: Add notification icon
      badge: '/badge-72x72.png', // TODO: Add badge icon
      data: payload.data || {},
      tag: payload.messageId || 'notification',
      requireInteraction: false,
    };
    
    // Show browser notification
    if ('Notification' in window && Notification.permission === 'granted') {
      const notification = new Notification(notificationTitle, notificationOptions);
      
      // Handle notification click
      notification.onclick = (event) => {
        event.preventDefault();
        window.focus();
        
        // Navigate to URL if provided
        if (payload.data?.url) {
          window.location.href = payload.data.url;
        }
        
        notification.close();
      };
    }
  });
}

/**
 * Initialize Firebase Messaging for web push notifications
 * Call this function when user logs in
 */
export async function initializeFirebaseMessaging() {
  if (!messaging) {
    console.warn('Firebase Messaging not available');
    return;
  }

  // Setup foreground message handler
  setupForegroundMessageHandler();

  // Request permission and register token
  const token = await requestNotificationPermission();
  
  if (token) {
    await registerTokenToBackend(token);
  }

  return token;
}

export { app, messaging };


// Firebase Service Worker for Background Push Notifications
// This file must be in the public folder

importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

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
firebase.initializeApp(firebaseConfig);

// Retrieve an instance of Firebase Messaging so that it can handle background messages
const messaging = firebase.messaging();

// Handle background messages (when app is closed or in background)
messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Background message received:', payload);
  
  const notificationTitle = payload.notification?.title || payload.data?.title || 'New Notification';
  const notificationBody = payload.notification?.body || payload.data?.message || '';
  
  const notificationOptions = {
    body: notificationBody,
    icon: '/icon-192x192.png', // App icon for notifications
    badge: '/badge-72x72.png', // Badge icon
    image: payload.notification?.image || payload.data?.image || null, // Large image (if provided)
    data: payload.data || {},
    tag: payload.messageId || payload.data?.notification_id || 'notification',
    requireInteraction: false,
    vibrate: [200, 100, 200], // Vibration pattern for mobile
    timestamp: Date.now(),
    // Android-specific options
    android: {
      channelId: 'fcm_channel',
      priority: 'high',
      sound: 'default',
    },
  };
  
  // Show notification
  return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Handle notification click
self.addEventListener('notificationclick', (event) => {
  console.log('Notification clicked:', event);
  
  event.notification.close();
  
  // Navigate to URL if provided
  if (event.notification.data?.url) {
    event.waitUntil(
      clients.openWindow(event.notification.data.url)
    );
  } else {
    // Default: focus existing window or open new one
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
        if (clientList.length > 0) {
          return clientList[0].focus();
        }
        return clients.openWindow('/');
      })
    );
  }
});


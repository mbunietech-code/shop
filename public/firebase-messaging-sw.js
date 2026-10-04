importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyDHpEBsaLO-TmAVowiRkEK65OKxRjPRIiw",
    authDomain: "mbunieshop-27984.firebaseapp.com",
    projectId: "mbunieshop-27984",
    storageBucket: "mbunieshop-27984.firebasestorage.app",
    messagingSenderId: "941968726461",
    appId: "1:941968726461:web:43effe05341520030e5ad4",
    measurementId: "G-PZD6TJ1GDZ"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || ''
    });
});
import './bootstrap';
import '../css/app.css';

// Hulpfunctie om favicon te wisselen
function changeFavicon(src) {
    let link = document.querySelector("link[rel~='icon']");
    if (!link) {
        link = document.createElement("link");
        link.rel = "icon";
        document.head.appendChild(link);
    }
    link.href = src;
}

// ✅ Alleen luisteren als de user ingelogd is
if (window.Laravel && window.Laravel.userId) {
    window.Echo.private('App.Models.Team.' + window.Laravel.userId)
        .notification((notification) => {
            console.log('Nieuwe notificatie realtime:', notification.message);

            // Meldingenlijst updaten
            const notifBox = document.getElementById('notifications');
            if (notifBox) {
                if (notifBox.children.length === 1 && notifBox.children[0].classList.contains("text-gray-400")) {
                    notifBox.innerHTML = "";
                }
                const li = document.createElement('li');
                li.className = "p-3 text-sm font-bold text-gray-900 bg-gray-50";
                li.innerText = notification.message;
                notifBox.prepend(li);
            }

            // 🔔 Badge teller updaten
            const badge = document.querySelector('button.relative span');
            if (badge) {
                let count = parseInt(badge.textContent) || 0;
                badge.textContent = count + 1;
                badge.classList.remove("hidden");
            }

            // ✅ Favicon rood maken
            changeFavicon("/favicon-alert.ico"); // plaats dit bestand in je /public map

            // ✅ Browser notificatie (system tray/taakbalk)
            if (Notification.permission === "granted") {
                new Notification("MS Infra - Nieuwe melding", {
                    body: notification.message,
                    icon: "/favicon.ico" // optioneel eigen icon
                });
            }
        });
}

// Eenmalig toestemming vragen
if (Notification.permission !== "granted" && Notification.permission !== "denied") {
    Notification.requestPermission();
}


// Optioneel: testkanaal voor debug
window.Echo.channel('test-channel')
    .listen('TestEvent', (e) => {
        console.log('Ontvangen via Reverb:', e.message);
    });

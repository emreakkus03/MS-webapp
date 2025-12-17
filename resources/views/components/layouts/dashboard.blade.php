<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
      <link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#B51D2D">
<link rel="apple-touch-icon" href="{{ asset('images/logo/msinfra_logo.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>{{ $title ?? 'MS-Webapp' }}</title>
    <link rel="stylesheet" href="{{ asset('css/reset.css') }}">

    @if (auth()->check())
        <!-- Globale Laravel helper -->
        <script>
            window.Laravel = {
                userId: {{ auth()->id() }},
                userRole: "{{ auth()->user()->role }}"
            };
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>

<body class="flex min-h-screen">
    <x-layouts.navigation></x-layouts.navigation>

    <main class="flex-1 min-h-screen p-6 overflow-y-auto bg-gray-50">
        {{ $slot }}
    </main>

@if (Auth::check())
<div id="featurePopup" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-xl shadow-lg p-6 max-w-md w-full mx-4">

    <!-- Language selector -->
    <div class="flex justify-center gap-2 mb-4">
      <button data-lang="nl" class="lang-btn px-3 py-1 rounded border text-sm">🇳🇱 NL</button>
      <button data-lang="en" class="lang-btn px-3 py-1 rounded border text-sm">🇬🇧 EN</button>
      <button data-lang="ro" class="lang-btn px-3 py-1 rounded border text-sm">🇷🇴 RO</button>
    </div>

    <!-- Title -->
    <h2 id="popupTitle" class="text-2xl font-bold mb-4 text-center text-gray-400">
      🔔 Kies een taal</br>Select a language</br>Alegeți o limbă
    </h2>

    <!-- Content -->
    <div id="popupContent" class="text-gray-700 text-sm leading-relaxed space-y-4 opacity-40 pointer-events-none"></div>

    <!-- Button -->
    <div class="text-center mt-6">
      <button id="closePopupBtn"
        class="bg-gray-300 text-white px-5 py-2 rounded-lg font-semibold cursor-not-allowed"
        disabled>
        Begrepen
      </button>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const popupKey = 'mswebapp_feature_popup_seen_v5';

  const popup = document.getElementById('featurePopup');
  const content = document.getElementById('popupContent');
  const title = document.getElementById('popupTitle');
  const closeBtn = document.getElementById('closePopupBtn');
  const langButtons = document.querySelectorAll('.lang-btn');

  const texts = {
    nl: {
      title: '🔔 Nieuwe update',
      button: 'Begrepen',
      html: `
        <div>
          <b>🗂️ Vergunningen bekijken</b><br>
          Op de Vergunningenpagina kan je nu:
          <ul class="list-disc list-inside mt-1">
            <li>Vergunningen bekijken</li>
            <li>Afbeeldingen openen en bekijken</li>
            <li>PDF-bestanden openen en lezen</li>
          </ul>
        </div>

        <div>
          <b>⚠️ Vastlopend scherm bij foto’s uploaden</b><br>
          Soms kan het scherm vastlopen na het voltooien van een taak.<br>
          Je ziet dan bijvoorbeeld “1/20 foto’s” die niet verder laadt.<br><br>
          Dit betekent meestal dat de internetverbinding niet stabiel genoeg is.
          <ul class="list-disc list-inside mt-1">
            <li>Ga naar een locatie met betere internetverbinding 📶</li>
            <li>Vernieuw daarna de browser en probeer opnieuw</li>
          </ul>
        </div>

        <div>
          <b>🚧 Verlofaanvragen</b><br>
          De verlofaanvragenpagina is nog in ontwikkeling.<br>
          Niet alle medewerkers zijn al volledig aangevuld.
        </div>
      `
    },
    en: {
      title: '🔔 New update',
      button: 'Got it',
      html: `
        <div>
          <b>🗂️ View permits</b><br>
          On the Permits page you can now:
          <ul class="list-disc list-inside mt-1">
            <li>View permits</li>
            <li>Open and view images</li>
            <li>Open and read PDF files</li>
          </ul>
        </div>

        <div>
          <b>⚠️ Screen freezing after uploading photos</b><br>
          Sometimes the screen may freeze after completing a task.<br>
          You may see “1/20 photos” that does not continue loading.<br><br>
          This usually means your internet connection is not stable enough.
          <ul class="list-disc list-inside mt-1">
            <li>Move to a location with better internet connection 📶</li>
            <li>Refresh the browser and try again</li>
          </ul>
        </div>

        <div>
          <b>🚧 Leave requests</b><br>
          The leave requests page is still under development.<br>
          Not all users have been fully added yet.
        </div>
      `
    },
    ro: {
      title: '🔔 Actualizare nouă',
      button: 'Am înțeles',
      html: `
        <div>
          <b>🗂️ Vizualizarea autorizațiilor</b><br>
          Pe pagina Autorizații puteți:
          <ul class="list-disc list-inside mt-1">
            <li>Vizualiza autorizațiile</li>
            <li>Deschide și vizualiza imagini</li>
            <li>Deschide și citi fișiere PDF</li>
          </ul>
        </div>

        <div>
          <b>⚠️ Blocarea ecranului la încărcarea fotografiilor</b><br>
          Uneori ecranul se poate bloca după finalizarea unei sarcini.<br>
          Puteți vedea „1/20 fotografii” care nu mai continuă.<br><br>
          Acest lucru indică de obicei o conexiune la internet instabilă.
          <ul class="list-disc list-inside mt-1">
            <li>Mutați-vă într-o zonă cu conexiune mai bună 📶</li>
            <li>Reîmprospătați browserul și încercați din nou</li>
          </ul>
        </div>

        <div>
          <b>🚧 Cereri de concediu</b><br>
          Pagina cererilor de concediu este încă în dezvoltare.<br>
          Nu toți angajații sunt încă adăugați complet.
        </div>
      `
    }
  };

  if (!localStorage.getItem(popupKey)) {
    popup.classList.remove('hidden');
  }

  langButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const lang = btn.dataset.lang;

      langButtons.forEach(b => b.classList.remove('bg-gray-200'));
      btn.classList.add('bg-gray-200');

      title.textContent = texts[lang].title;
      content.innerHTML = texts[lang].html;
      content.classList.remove('opacity-40', 'pointer-events-none');

      closeBtn.textContent = texts[lang].button;
      closeBtn.disabled = false;
      closeBtn.classList.remove('bg-gray-300', 'cursor-not-allowed');
      closeBtn.classList.add('bg-[#283142]', 'hover:bg-[#B51D2D]');
    });
  });

  closeBtn.addEventListener('click', () => {
    localStorage.setItem(popupKey, 'true');
    popup.classList.add('hidden');
  });
});
</script>
@endif


<script src="//unpkg.com/alpinejs" defer></script>
</body>
</html>

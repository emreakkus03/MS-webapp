<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>{{ $title ?? 'MS-Webapp' }}</title>
    <link rel="stylesheet" href="{{ asset('css/reset.css') }}">

    @if(auth()->check())
        <!-- Globale Laravel helper -->
        <script>
            window.Laravel = {
                userId: {{ auth()->id() }},
                userRole: "{{ auth()->user()->role }}"
            };
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen">
    <x-layouts.navigation></x-layouts.navigation>
    <main class="flex-1 min-h-screen p-6 overflow-y-auto bg-gray-50">
        {{ $slot }}
    </main>

    @if (Auth::check())
<div id="featurePopup" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-xl shadow-lg p-6 max-w-md w-full mx-4 text-center">
    <h2 class="text-2xl font-bold mb-3">🎉 Nieuwe update!</h2>
    <p class="text-gray-700 text-sm mb-4">
      - Je kunt nu <b>tot 30 foto's</b> tegelijk uploaden 📸<br>
      - Uploads zijn <b>sneller</b> ⚡<br>
      - Foto’s verschijnen binnen <b>~1 minuut</b> in Dropbox ☁️
    </p>
    <button id="closePopupBtn"
      class="bg-[#283142] hover:bg-[#B51D2D] text-white px-4 py-2 rounded-lg font-semibold">
      Ik snap het!
    </button>
  </div>
</div>
<script src="//unpkg.com/alpinejs" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const popupKey = 'mswebapp_feature_popup_seen_v1'; // wijzig "v1" bij volgende updates
  const popup = document.getElementById('featurePopup');
  const closeBtn = document.getElementById('closePopupBtn');

  if (!localStorage.getItem(popupKey)) {
    popup.classList.remove('hidden');
  }

  closeBtn.addEventListener('click', () => {
    popup.classList.add('hidden');
    localStorage.setItem(popupKey, 'true');
  });
});
</script>
@endif

</body>

<script src="//unpkg.com/alpinejs" defer></script>
</html>

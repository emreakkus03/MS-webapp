<x-layouts.app>
    <h1>Inloggen</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <label>Ploegnaam:</label>
            <select name="name" required>
                <option value="">-- Selecteer je ploeg --</option>
                @foreach($teams as $team)
                    <option value="{{ $team->name }}" {{ old('name') == $team->name ? 'selected' : '' }}>
                        {{ $team->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label>Wachtwoord:</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit">Inloggen</button>
    </form>

    @if($errors->any())
        <div style="color:red;">
            {{ $errors->first() }}
        </div>
    @endif
</x-layouts.app>

<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- View-transition opt-in must be inline: Chromium evaluates it before external CSS arrives --}}
    <style>@view-transition { navigation: auto; }</style>
    <title>Login - Macaron</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-dark.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        const savedTheme = localStorage.getItem('macaron-theme');
        document.documentElement.classList.toggle('dark', savedTheme ? savedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
        function toggleTheme() {
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('macaron-theme', dark ? 'dark' : 'light');
            document.getElementById('login-theme-icon').textContent = dark ? 'light_mode' : 'dark_mode';
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { color-scheme: light; --bg:#FFFBFE; --surface:#FFFBFE; --container:#F3EDF7; --text:#1D1B20; --muted:#49454F; --primary:#715300; --on-primary:#FFFFFF; --outline:#79747E; }
        .dark { color-scheme: dark; --bg:#141316; --surface:#1C1B1F; --container:#2B2930; --text:#E6E1E5; --muted:#CAC4D0; --primary:#E2C468; --on-primary:#3B2D00; --outline:#938F99; }
        body { font-family: 'Hanken Grotesk', sans-serif; background:var(--bg); color:var(--text); }
        .login-card { background:var(--surface); border-color:color-mix(in srgb,var(--outline) 35%,transparent); }
        .login-field { background:var(--container); border-color:transparent; color:var(--text); }
        .login-field:focus { border-color:var(--primary); box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 30%,transparent); outline:none; }
        .login-primary { background:var(--primary); color:var(--on-primary); }
        .login-muted { color:var(--muted); }
        .login-accent { color:var(--primary); }
    </style>
</head>
<body class="flex items-center justify-center h-screen p-6">
    <button type="button" onclick="toggleTheme()" class="absolute right-6 top-6 w-12 h-12 rounded-full border flex items-center justify-center login-card" aria-label="Switch light or dark theme">
        <span id="login-theme-icon" class="material-symbols-rounded">dark_mode</span>
    </button>
    <div class="login-card w-full max-w-md p-8 rounded-[28px] border shadow-sm">
        <div class="text-center mb-8">
            <h1 class="login-accent text-4xl font-black mb-2">Macaron</h1>
            <p class="login-muted">Har gram, har sale, hisaab clear.</p>
        </div>
        
        @if($errors->any())
            <div class="mb-4 p-4 bg-error/10 border border-error/20 text-error rounded-xl text-xs font-bold">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-sm font-bold mb-2">Staff ID / Email</label>
                <input type="email" name="email" required class="login-field w-full border rounded-xl h-14 px-4" placeholder="admin@macaron.com" value="{{ old('email') }}">
            </div>
            <div>
                <label class="block text-sm font-bold mb-2">Access PIN / Password</label>
                <input type="password" name="password" required class="login-field w-full border rounded-xl h-14 px-4" placeholder="Enter your password">
            </div>
            
            <button type="submit" class="login-primary w-full h-14 font-bold rounded-full hover:brightness-105 transition-colors">
                UNLOCK TERMINAL
            </button>
        </form>
        
        <p class="login-muted text-center mt-6 text-xs">
            Sweets Counter • Secure Staff Login
        </p>
    </div>
    <script>document.getElementById('login-theme-icon').textContent = document.documentElement.classList.contains('dark') ? 'light_mode' : 'dark_mode';</script>
</body>
</html>

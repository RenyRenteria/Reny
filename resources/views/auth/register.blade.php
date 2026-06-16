<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Create account | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="auth_register">
        <main class="auth-shell">
            <section class="auth-panel" aria-labelledby="register-title">
                <a class="brand-link brand-link-centered" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <h1 id="register-title">Create account</h1>
                <p class="auth-copy">Start Open. Activate Royal Pass when you buy a pass or eligible product.</p>

                <form class="auth-form" method="POST" action="{{ route('register.store') }}">
                    @csrf

                    <label>
                        <span>Name</span>
                        <input name="name" type="text" value="{{ old('name') }}" autocomplete="name" required>
                        @error('name')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Public username</span>
                        <input name="username" type="text" value="{{ old('username') }}" autocomplete="username" inputmode="latin" placeholder="renyfan" required>
                        @error('username')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Email or phone</span>
                        <input name="identifier" type="text" value="{{ old('identifier') }}" autocomplete="email" required>
                        @error('identifier')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Country</span>
                        <select name="country_code" autocomplete="country" required>
                            <option value="" @selected(old('country_code') === null)>Select country</option>
                            @foreach ([
                                'PA' => 'Panama',
                                'DO' => 'Dominican Republic',
                                'US' => 'United States',
                                'PR' => 'Puerto Rico',
                                'MX' => 'Mexico',
                                'CO' => 'Colombia',
                                'ES' => 'Spain',
                                'AR' => 'Argentina',
                                'CL' => 'Chile',
                                'PE' => 'Peru',
                                'EC' => 'Ecuador',
                                'CR' => 'Costa Rica',
                            ] as $code => $country)
                                <option value="{{ $code }}" @selected(old('country_code') === $code)>{{ $country }}</option>
                            @endforeach
                        </select>
                        @error('country_code')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="new-password" required>
                        @error('password')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Confirm password</span>
                        <input name="password_confirmation" type="password" autocomplete="new-password" required>
                    </label>

                    <button class="auth-button" type="submit">Create account</button>
                </form>

                <div class="auth-links">
                    <a href="{{ route('login') }}">Already have an account?</a>
                </div>
            </section>
        </main>
    </body>
</html>

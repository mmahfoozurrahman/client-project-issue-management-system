<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(100%, 420px);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            padding: 32px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        p {
            margin: 0 0 24px;
            color: #6b7280;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            margin-bottom: 14px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 12px 14px;
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            color: #b91c1c;
            font-size: 14px;
            margin: -6px 0 12px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .remember input {
            width: auto;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="shell">
        <form method="POST" action="{{ route('login.store') }}" class="card">
            @csrf

            <h1>Sign in</h1>
            <p>Use your administrator-created account to access your workspace.</p>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror

            <label class="remember" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1">
                <span>Remember me</span>
            </label>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>

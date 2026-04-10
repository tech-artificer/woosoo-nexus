<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found — Woosoo Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100vh;
            background-color: #0a0a0a;
            color: #e5e5e5;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 480px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(246,181,109,0.12);
            border: 1px solid rgba(246,181,109,0.3);
            color: #F6B56D;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            margin-bottom: 1.5rem;
        }
        .code {
            font-size: clamp(5rem, 18vw, 8rem);
            font-weight: 800;
            line-height: 1;
            color: transparent;
            background: linear-gradient(135deg, #F6B56D 0%, #fcd9a8 60%, #e8904a 100%);
            -webkit-background-clip: text;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #f5f5f5;
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 0.9rem;
            color: #737373;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background: #F6B56D;
            color: #1a1a1a;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.88; }
        .footer {
            margin-top: 3rem;
            font-size: 0.75rem;
            color: #404040;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">Woosoo Admin</div>
        <div class="code">404</div>
        <h1>Page not found</h1>
        <p>The page you're looking for doesn't exist or has been moved.<br>Head back to the dashboard to continue.</p>
        <a href="/dashboard" class="btn">Back to Dashboard</a>
        <div class="footer">&copy; {{ date('Y') }} Woosoo. All rights reserved.</div>
    </div>
</body>
</html>

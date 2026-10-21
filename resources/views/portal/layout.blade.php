<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title', $brandName ?? 'WiFi')</title>
    {{-- All styles are inlined on purpose: an unauthenticated phone can only
         reach this host, so no external CSS/fonts/images may be referenced. --}}
    <style>
        :root {
            --brand: #0b6e99;
            --brand-dark: #084e6d;
            --accent: #16a34a;
            --bg: #f3f5f7;
            --card: #ffffff;
            --ink: #1a2b34;
            --muted: #5b7280;
            --line: #e2e8ec;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-text-size-adjust: 100%;
            line-height: 1.4;
        }
        .wrap {
            max-width: 480px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header.brand {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            padding: 22px 20px calc(22px + env(safe-area-inset-top));
            display: flex;
            align-items: center;
            gap: 12px;
        }
        header.brand .logo {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
        }
        header.brand h1 {
            font-size: 1.15rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: .2px;
        }
        header.brand .tag {
            display: block;
            font-size: .72rem;
            font-weight: 500;
            opacity: .85;
            margin-top: 1px;
        }
        main { padding: 18px 16px 28px; flex: 1 1 auto; }
        .lead { color: var(--muted); font-size: .92rem; margin: 2px 0 16px; }

        .flash {
            background: #e7f6ec;
            border: 1px solid #b8e4c6;
            color: #14612f;
            padding: 11px 13px;
            border-radius: 10px;
            font-size: .88rem;
            margin-bottom: 14px;
        }
        .flash-error {
            background: #fdeaea;
            border-color: #f3b5b5;
            color: #8a1c1c;
        }

        .creds {
            background: #f7fafc;
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .85rem;
            color: var(--muted);
            margin-top: 16px;
            word-break: break-all;
        }
        .creds b { color: var(--ink); }
        .btn {
            display: block;
            width: 100%;
            text-align: center;
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 1.05rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            margin-top: 18px;
        }
        .btn:active { background: var(--brand-dark); }
        .spin {
            display: inline-block;
            width: 34px; height: 34px;
            border: 3px solid var(--line);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .plan {
            display: flex;
            align-items: center;
            width: 100%;
            text-align: left;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 2px rgba(16,42,56,.05);
            cursor: pointer;
            font: inherit;
            color: inherit;
            transition: transform .05s ease, border-color .15s ease;
        }
        .plan:active { transform: scale(.99); border-color: var(--brand); }
        .plan .info { flex: 1 1 auto; min-width: 0; }
        .plan .name { display: block; font-size: 1.05rem; font-weight: 700; }
        .plan .dur { display: block; color: var(--muted); font-size: .85rem; margin-top: 2px; }
        .plan .right { flex: 0 0 auto; text-align: right; padding-left: 12px; }
        .plan .price { display: block; font-size: 1.05rem; font-weight: 700; color: var(--brand-dark); }
        .plan .price s { color: var(--muted); font-weight: 500; font-size: .8rem; display: block; }
        .badge {
            display: inline-block;
            background: var(--accent);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 999px;
            margin-top: 6px;
        }
        .go {
            display: block;
            margin-top: 8px;
            font-size: .78rem;
            color: var(--brand);
            font-weight: 600;
        }

        .empty, .connect {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 26px 20px;
            text-align: center;
        }
        .connect .big { font-size: 2.6rem; line-height: 1; margin-bottom: 10px; }
        .connect h2 { margin: 0 0 8px; font-size: 1.2rem; }
        .connect p { color: var(--muted); margin: 0; font-size: .95rem; }

        .status-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 22px 18px;
            text-align: center;
        }
        .status-card .dot {
            display: inline-block;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 4px rgba(22,163,74,.18);
            margin-bottom: 10px;
        }
        .status-card h2 { margin: 0 0 4px; font-size: 1.25rem; }
        .rows { text-align: left; }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 11px 2px;
            border-top: 1px solid var(--line);
            font-size: .95rem;
        }
        .row span { color: var(--muted); }
        .row b { font-size: 1.05rem; }

        footer.fine {
            padding: 14px 16px calc(18px + env(safe-area-inset-bottom));
            text-align: center;
            color: var(--muted);
            font-size: .72rem;
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="brand">
        <svg class="logo" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2 8.5C7.5 3.5 16.5 3.5 22 8.5" stroke="#fff" stroke-width="2.1" stroke-linecap="round"/>
            <path d="M5.5 12C9 9 15 9 18.5 12" stroke="#fff" stroke-width="2.1" stroke-linecap="round" opacity=".85"/>
            <path d="M9 15.5C10.8 14 13.2 14 15 15.5" stroke="#fff" stroke-width="2.1" stroke-linecap="round" opacity=".7"/>
            <circle cx="12" cy="19" r="1.6" fill="#fff"/>
        </svg>
        <div>
            <h1>{{ $brandName }}</h1>
            <span class="tag">Hotspot WiFi</span>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="fine">
        Powered by {{ $brandName }}
    </footer>
</div>
</body>
</html>

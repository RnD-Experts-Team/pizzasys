<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Open PNE Staff</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #fdfdfc;
                color: #1b1b18;
            }
            @media (prefers-color-scheme: dark) {
                body { background: #0a0a0a; color: #ededec; }
                .card { background: #161615 !important; border-color: #3e3e3a !important; }
                .domain { color: #a1a09a !important; }
            }
            .card {
                max-width: 420px;
                width: 100%;
                margin: 24px;
                padding: 32px 28px;
                border: 1px solid #e3e3e0;
                border-radius: 16px;
                text-align: center;
                box-sizing: border-box;
            }
            h1 { font-size: 1.25rem; margin: 0 0 8px; }
            .domain {
                display: inline-block;
                margin: 0 0 24px;
                padding: 4px 12px;
                border-radius: 999px;
                background: rgba(245, 48, 3, 0.08);
                color: #706f6c;
                font-size: 0.875rem;
                word-break: break-all;
            }
            .badges { display: flex; flex-direction: column; gap: 12px; }
            .badge {
                display: block;
                padding: 12px 20px;
                border-radius: 10px;
                background: #1b1b18;
                color: #fff;
                text-decoration: none;
                font-size: 0.95rem;
                font-weight: 500;
            }
            @media (prefers-color-scheme: dark) {
                .badge { background: #eeeeec; color: #1c1c1a; }
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Open in the PNE Staff app</h1>
            @if ($domain !== '')
                <p class="domain">{{ $domain }}</p>
            @endif
            <div class="badges">
                <a class="badge" href="https://play.google.com/store/apps/details?id=com.pneunited.pnestaffapp">
                    Get it on Google Play
                </a>
                {{-- App Store link is a placeholder until the app has a real listing. --}}
                <a class="badge" href="https://apps.apple.com/app/id0000000000">
                    Download on the App Store
                </a>
            </div>
        </div>
    </body>
</html>

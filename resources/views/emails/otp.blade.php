<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>OTP Code</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    .preheader { display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all; }
    html, body { margin:0; padding:0; background:#ffffff; }
    img { border:0; outline:none; text-decoration:none; }
    table { border-collapse:collapse; }
    .font-sans { font-family: Inter, Arial, Helvetica, sans-serif; }
    .font-mono { font-family: "JetBrains Mono", Menlo, Consolas, monospace; }
    .badge {
      display:inline-block;
      background:#fffbeb;
      color:#92400e;
      border:1px solid #e5e7eb;
      border-radius:999px;
      padding:6px 10px;
      font-size:12px;
      font-weight:600;
    }
    .card {
      border:1px solid #e5e7eb;
      border-radius:16px;
      background:#ffffff;
    }
    .rounded-all {
      border-radius:16px !important;
      overflow:hidden;
    }
    .muted { color:#6b7280; }
    .hr { height:1px; background:#e5e7eb; line-height:1px; }
    /* @media (prefers-color-scheme: dark) {
      body { background:#111827 !important; }
      .card { background:#1f2937 !important; border-color:#374151 !important; }
      .muted { color:#9ca3af !important; }
    } */
  </style>
</head>
<body class="font-sans" style="background:#ffffff; color:#262626;">
  <div class="preheader">
    Your one-time code is {{ $otp }} — it expires in 10 minutes.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;">
    <tr>
      <td align="center" style="padding:32px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
          <tr>
            <td style="padding-bottom:12px;" align="left">
              <span class="badge font-sans">
                {{ $type === 'verification' ? 'Email Verification' : 'Password Reset' }}
              </span>
            </td>
          </tr>

          <tr>
            <td class="card" style="padding:32px;">
              <h1 class="font-sans" style="margin:0; font-size:22px; line-height:1.3; color:#262626;">
                Your One-Time Passcode
              </h1>
              <p class="muted" style="font-size:14px; line-height:1.7;">
                Use this code to complete your {{ $type === 'verification' ? 'email verification' : 'password reset' }}.
                It expires in <strong>10 minutes</strong>.
              </p>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="rounded-all" style="background:#fffbeb; border:1px solid #e5e7eb;">
                <tr>
                  <td style="padding:20px;">
                    <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; padding-bottom:8px;">
                      One-Time Code
                    </div>
                    <div class="font-mono" style="font-size:24px; font-weight:700; letter-spacing:0.12em; background:#ffffff; border:1px dashed #e5e7eb; border-radius:10px; padding:10px 14px; color:#262626; display:inline-block;">
                      {{ $otp }}
                    </div>
                  </td>
                </tr>
              </table>

              <div style="height:24px;"></div>
              <div class="hr"></div>
              <div style="height:16px;"></div>
              <p class="muted" style="font-size:13px; line-height:1.7;">
                Didn’t request this? It’s safe to ignore this email. Your account remains secure.
              </p>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:16px 8px;">
              <div class="muted" style="font-size:12px;">
                © {{ date('Y') }} — All rights reserved.
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>

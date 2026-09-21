<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Reset your {{ $appName }} password</title>
</head>
<body style="margin:0;padding:0;background-color:#eef4ff;font-family:Manrope,Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef4ff;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(9,21,58,0.08);">
                    <tr>
                        <td align="center" style="padding:28px 32px 20px;background-color:#ffffff;">
                            <img
                                src="{{ $logoSrc }}"
                                alt="{{ $appName }}"
                                width="220"
                                style="display:block;width:220px;max-width:70%;height:auto;border:0;"
                            >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px;">
                            <div style="border-top:1px solid #e2e8f0;font-size:0;line-height:0;">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 40px 8px;">
                            <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#1f5fd0;">
                                Account recovery
                            </p>
                            <h1 style="margin:0 0 16px;font-size:28px;line-height:1.25;font-weight:700;color:#09153a;">
                                Reset your password
                            </h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#475569;">
                                @if($name)
                                    Hi {{ $name }},
                                @else
                                    Hi,
                                @endif
                                we received a request to reset the password for your {{ $appName }} account.
                            </p>
                            <p style="margin:0 0 28px;font-size:15px;line-height:1.6;color:#475569;">
                                Click the button below to choose a new password. This link expires in
                                <strong style="color:#09153a;">{{ $expireMinutes }} minutes</strong>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 40px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#1f5fd0" style="border-radius:999px;">
                                        <a
                                            href="{{ $resetUrl }}"
                                            style="display:inline-block;padding:14px 36px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;background-color:#1f5fd0;"
                                        >
                                            Reset password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 40px 28px;">
                            <p style="margin:0;font-size:13px;line-height:1.55;color:#64748b;">
                                If the button doesn&apos;t work,
                                <a href="{{ $resetUrl }}" style="color:#1f5fd0;font-weight:600;text-decoration:underline;">
                                    open the reset page
                                </a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 28px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
                            <p style="margin:0 0 10px;font-size:13px;line-height:1.55;color:#64748b;">
                                If you did not request a password reset, you can safely ignore this email. Your password will stay the same.
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
                                {{ $appName }} Genius &mdash; Smarter Systems for Safer Care
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

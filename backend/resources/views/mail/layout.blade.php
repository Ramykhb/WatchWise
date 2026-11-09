<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $action }} · WatchWise</title>
    <style>
        body { margin: 0; padding: 0; }
        table { border-collapse: collapse; }
        a { color: #2b1a63; }
        @media only screen and (max-width: 620px) {
            .outer { padding: 20px 12px !important; }
            .inset { padding-left: 24px !important; padding-right: 24px !important; }
            .headline { font-size: 36px !important; line-height: 40px !important; }
            .brand { font-size: 23px !important; }
            .ring { width: 64px !important; height: 64px !important; }
            .button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eee8ff;color:#16122b;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;font-size:1px;line-height:1px;color:#eee8ff;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $preview }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#eee8ff">
        <tr><td class="outer" align="center" style="padding:44px 20px;">
            <!--[if mso]><table role="presentation" width="600"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                <tr><td bgcolor="#2b1a63" class="inset" style="padding:28px 40px;border-radius:24px 24px 0 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="50" valign="middle"><a href="{{ config('services.frontend_url') }}" style="text-decoration:none;"><img src="{{ asset('images/mail/logo-mark.png') }}" alt="WatchWise owl" width="44" height="44" style="display:block;border:0;"></a></td>
                            <td class="brand" valign="middle" style="padding-left:10px;font-size:27px;letter-spacing:-1px;font-weight:800;color:#fff3e3;">WatchWise<span style="color:#ffc93c;">.</span></td>
                            <td align="right" valign="middle"><img class="ring" src="{{ asset('images/mail/deco-ring.png') }}" alt="" width="80" height="80" style="display:block;border:0;"></td>
                        </tr>
                    </table>
                </td></tr>
                <tr><td bgcolor="#ffc93c" height="6" style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td bgcolor="#fff3e3" class="inset" style="padding:40px 40px 0;">
                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#dcf6ee" style="padding:9px 13px;border:1px solid #7ce0c3;border-radius:20px;font-size:10px;line-height:14px;letter-spacing:1.8px;font-weight:700;color:#234e43;">{{ $eyebrow }}</td></tr></table>
                    <h1 class="headline" style="margin:24px 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:46px;line-height:49px;letter-spacing:-2px;font-weight:800;color:#2b1a63;">{{ $heading }}</h1>
                    <p style="margin:0 0 16px;font-size:16px;line-height:27px;color:#5a5470;">{{ $intro }}</p>
                    <p style="margin:0 0 26px;font-size:16px;line-height:27px;color:#5a5470;">{{ $instruction }}</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td>
                        <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:54px;v-text-anchor:middle;width:240px;" arcsize="50%" stroke="f" fillcolor="#2b1a63"><w:anchorlock/><center style="color:#fff3e3;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">{{ $action }} &rarr;</center></v:roundrect><![endif]-->
                        <!--[if !mso]><!--><a class="button" href="{{ $url }}" style="display:inline-block;background-color:#2b1a63;border:2px solid #2b1a63;border-radius:28px;padding:16px 28px;color:#fff3e3;text-decoration:none;font-size:16px;line-height:20px;font-weight:700;">{{ $action }} &nbsp; &rarr;</a><!--<![endif]-->
                    </td></tr></table>
                    @isset($expiresInMinutes)
                    <p style="margin:16px 0 28px;font-size:12px;line-height:20px;color:#5a5470;">Your link expires in <strong>{{ $expiresInMinutes }} minutes.</strong></p>
                    @else
                    <div style="height:28px;font-size:0;line-height:0;">&nbsp;</div>
                    @endisset
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td bgcolor="#fbe6cf" style="padding:18px 20px;border-radius:12px;border-left:3px solid #ff5a4e;font-size:13px;line-height:22px;color:#5a5470;">{{ $note }}</td></tr></table>
                    <p style="margin:28px 0 0;font-size:14px;line-height:23px;color:#5a5470;">{{ $signoff }}<br><strong style="color:#2b1a63;">The WatchWise crew</strong></p>
                </td></tr>
                <tr><td bgcolor="#fff3e3" class="inset" style="padding:28px 40px 34px;border-radius:0 0 24px 24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="border-top:1px solid #dbd5d1;padding-top:22px;">
                        <p style="margin:0 0 8px;font-size:12px;line-height:20px;color:#5a5470;">Button taking an intermission? Copy this link into your browser:</p>
                        <a href="{{ $url }}" style="font-size:11px;line-height:19px;color:#2b1a63;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;">{{ $url }}</a>
                    </td></tr></table>
                </td></tr>
                <tr><td align="center" style="padding:26px 16px 0;">
                    <p style="margin:0 0 8px;font-size:10px;line-height:16px;letter-spacing:2px;font-weight:700;color:#2b1a63;">GOOD FILMS. GREAT COMPANY.</p>
                    <p style="margin:0;font-size:11px;line-height:19px;color:#5a5470;">&copy; {{ date('Y') }} WatchWise &nbsp;·&nbsp; <a href="{{ config('services.frontend_url') }}" style="color:#5a5470;text-decoration:underline;">Find your next watch</a></p>
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</body>
</html>

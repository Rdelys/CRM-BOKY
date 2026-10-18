<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;">
                <tr>
                    <td style="padding:32px;font-size:15px;line-height:1.6;">{!! $html !!}</td>
                </tr>
                <tr>
                    <td style="padding:16px 32px;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.5;color:#64748b;">
                        Vous recevez ce message car vous êtes en contact avec {{ config('mail.from.name') }}.<br>
                        <a href="{{ $unsubscribeUrl }}" style="color:#64748b;">Se désinscrire</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
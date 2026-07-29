<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                    <tr>
                        <td style="background:#f53003;padding:20px 28px;">
                            <img src="{{ $message->embed($logoPath) }}" width="28" height="28" alt="Logo" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <h1 style="margin:0 0 12px;font-size:22px;color:#1b1b18;">On its way, {{ $customer }}</h1>
                            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#55534e;">
                                Order <strong>{{ $orderNumber }}</strong> has shipped and should reach you
                                within three working days.
                            </p>
                            <a href="https://example.test/orders/{{ $orderNumber }}"
                               style="display:inline-block;background:#1b1b18;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-size:14px;">
                                Track this order
                            </a>
                            <p style="margin:24px 0 0;font-size:13px;color:#a3a09a;">
                                The invoice is attached to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

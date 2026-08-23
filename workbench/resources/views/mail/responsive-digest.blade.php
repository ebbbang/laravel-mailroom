<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{--
        The demo mailbox's one responsive message. SeedMailboxCommand explains
        why it exists; this is how it works.

        Every override carries !important because an inline style attribute
        beats a <style> rule, and the layout below is built out of inline
        styles like any real email. That is not sloppiness -- it is how
        responsive email is written, and without it the breakpoint does
        nothing at all.

        The <style> block itself is allowed through the preview's CSP, which
        sets "style-src 'unsafe-inline'". Scripts are not, and none is used.
    --}}
    <style>
        @media (max-width: 480px) {
            .col {
                display: block !important;
                width: 100% !important;
                padding: 0 0 20px !important;
            }

            .cta {
                display: block !important;
                text-align: center !important;
            }

            .pad {
                padding: 24px 18px !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                    <tr>
                        <td style="background:#1b1b18;padding:20px 28px;color:#ffffff;font-size:15px;font-weight:600;">
                            This week at the warehouse
                        </td>
                    </tr>
                    <tr>
                        <td class="pad" style="padding:32px 28px;">
                            <h1 style="margin:0 0 8px;font-size:22px;color:#1b1b18;">Three orders moved</h1>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#55534e;">
                                Narrow the preview to 375px and the two columns below stack, while the
                                button goes full width.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="col" width="50%" valign="top" style="padding:0 12px 0 0;">
                                        <div style="background:#f4f4f5;border-radius:8px;padding:16px;">
                                            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#a3a09a;">Shipped</p>
                                            <p style="margin:0;font-size:20px;font-weight:600;color:#1b1b18;">A-1101</p>
                                            <p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#55534e;">Left the warehouse on Tuesday.</p>
                                        </div>
                                    </td>
                                    <td class="col" width="50%" valign="top" style="padding:0 0 0 12px;">
                                        <div style="background:#f4f4f5;border-radius:8px;padding:16px;">
                                            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#a3a09a;">Delivered</p>
                                            <p style="margin:0;font-size:20px;font-weight:600;color:#1b1b18;">A-1102</p>
                                            <p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#55534e;">Signed for on Wednesday.</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <a
                                class="cta"
                                href="https://example.test/orders"
                                style="display:inline-block;margin-top:24px;background:#f53003;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:14px;font-weight:600;"
                            >
                                See every order
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px;font-size:12px;color:#a3a09a;">
                            You are receiving this because you have an account with us.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

<?php

return [
    'smtp_ready' => (bool) env('PORTAL_SMTP_READY', false),
    'otp_minutes' => max(1, (int) env('OTP_MINUTES', 10)),
    'otp_resend_seconds' => max(1, (int) env('OTP_RESEND_SECONDS', 60)),
    'otp_max_attempts' => max(1, (int) env('OTP_MAX_ATTEMPTS', 5)),
    'otp_send_email_limit' => max(1, (int) env('OTP_SEND_EMAIL_LIMIT', 5)),
    'otp_send_ip_limit' => max(1, (int) env('OTP_SEND_IP_LIMIT', 20)),
    'otp_verify_email_limit' => max(1, (int) env('OTP_VERIFY_EMAIL_LIMIT', 30)),
    'otp_verify_ip_limit' => max(1, (int) env('OTP_VERIFY_IP_LIMIT', 30)),
    'admin_whatsapp' => preg_match('/^[1-9][0-9]{7,14}$/', (string) env('ADMIN_WHATSAPP', '')) ? env('ADMIN_WHATSAPP') : null,
];

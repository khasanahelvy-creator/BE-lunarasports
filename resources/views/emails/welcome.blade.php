<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F8F8F8; padding: 20px; }
        .container { max-w-[600px]; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; border-top: 5px solid #2FA084; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: #111111; font-weight: 800; margin-top: 0; }
        p { color: #555555; line-height: 1.6; }
        .otp-box { background-color: #F0FDF8; border: 2px dashed #2FA084; color: #2FA084; font-size: 36px; font-weight: 900; letter-spacing: 12px; text-align: center; padding: 24px; margin: 30px 0; border-radius: 12px; }
        .footer { margin-top: 30px; font-size: 12px; color: #888888; text-align: center; border-top: 1px solid #EEEEEE; padding-top: 20px; }
        .warning { font-size: 13px; color: #E53E3E; font-weight: 600; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Halo, {{ $user->name }}! ⚡</h2>
        <p>Terima kasih telah mendaftar di <strong>Lunara Sports</strong>. Untuk menyelesaikan proses pendaftaran dan mengaktifkan akunmu, silakan masukkan kode OTP 4 digit berikut di aplikasi:</p>
        
        <!-- INI ADALAH KOTAK TEMPAT OTP MUNCUL -->
        <div class="otp-box">
            {{ $otp }}
        </div>

        <p class="warning">
            ⚠️ Kode ini bersifat rahasia. Jangan berikan kode ini kepada siapa pun, termasuk admin atau pihak Lunara Sports.
        </p>

        <div class="footer">
            <p>Email ini dibuat otomatis oleh sistem.<br>Tim Lunara Sports, Samata, Gowa, Sulawesi Selatan.</p>
        </div>
    </div>
</body>
</html>
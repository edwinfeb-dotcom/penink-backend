<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Akun PENINK</title>
    <!-- Font fallback untuk email client modern -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #e2e8f0; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #e2e8f0; padding: 40px 10px;">
        <tr>
            <td align="center">
                
                <!-- KARTU UTAMA -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); overflow: hidden;">
                    
                    <!-- HEADER DENGAN AKSEN GRADASI -->
                    <tr>
                        <td align="center" style="background-color: #0f172a; background-image: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 40px 20px; position: relative;">
                            <!-- Logo Teks -->
                            <div style="font-size: 32px; font-weight: 800; color: #ffffff; letter-spacing: 3px; margin-bottom: 8px;">
                                PEN<span style="color: #D4AF37;">INK</span>
                            </div>
                            <div style="font-size: 12px; font-weight: 600; color: #94a3b8; letter-spacing: 1.5px; text-transform: uppercase;">
                                Diskominfo Kabupaten Landak
                            </div>
                        </td>
                    </tr>

                    <!-- BODY EMAIL -->
                    <tr>
                        <td style="padding: 50px 40px;">
                            
                            <!-- Ikon Ilustrasi (Menggunakan emoji sebagai fallback aman) -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin-bottom: 30px;">
                                <tr>
                                    <td align="center" style="background-color: #f1f5f9; width: 80px; height: 80px; border-radius: 50%; border: 4px solid #e2e8f0;">
                                        <span style="font-size: 36px; line-height: 80px;">🔐</span>
                                    </td>
                                </tr>
                            </table>

                            <h1 style="color: #0f172a; margin: 0 0 16px 0; font-size: 24px; font-weight: 800; text-align: center; letter-spacing: -0.5px;">
                                Permintaan Reset Password
                            </h1>
                            
                            <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 24px 0; text-align: center;">
                                Halo <strong style="color: #0f172a;">{{ $user->name }}</strong>, kami mendeteksi permintaan untuk mereset password akun Anda.
                            </p>

                            <!-- TOMBOL UTAMA (Interactive Visual) -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 40px 0;">
                                <tr>
                                    <td align="center">
                                        <!-- Efek Bayangan Tombol -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td align="center" style="border-radius: 12px; background-color: #2563eb;">
                                                    <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 18px 48px; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 12px; border: 1px solid #3b82f6; box-shadow: inset 0 2px 0 rgba(255,255,255,0.2);">
                                                        Reset Password Saya
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- INFO BOX (Bento Style) -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top: 40px; background-color: #fffbeb; border-radius: 12px; border: 1px solid #fde68a;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td width="30" valign="top" style="font-size: 20px; padding-right: 10px;">⏳</td>
                                                <td>
                                                    <p style="color: #92400e; font-size: 14px; margin: 0; font-weight: 700; line-height: 1.4;">
                                                        Tautan ini bersifat rahasia dan hanya berlaku selama {{ $count }} menit.
                                                    </p>
                                                    <p style="color: #b45309; font-size: 13px; margin: 4px 0 0 0; line-height: 1.4;">
                                                        Jika waktu habis, Anda harus meminta tautan baru.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0; text-align: center;">
                                Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini. Akun Anda 100% aman.
                            </p>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 30px 40px; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="color: #94a3b8; font-size: 12px; line-height: 1.6; margin: 0 0 15px 0;">
                                Tombol tidak berfungsi? Copy dan paste tautan di bawah ini ke browser Anda:
                            </p>
                            <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; word-break: break-all; margin-bottom: 20px;">
                                <a href="{{ $url }}" style="color: #2563eb; font-size: 12px; text-decoration: none;">{{ $url }}</a>
                            </div>
                            <p style="color: #cbd5e1; font-size: 11px; margin: 0;">
                                © 2026 Diskominfo Kabupaten Landak.<br>Seluruh hak cipta dilindungi undang-undang.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Teks Keamanan di luar kartu -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; margin-top: 20px;">
                    <tr>
                        <td align="center">
                            <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                                Email ini dikirim secara otomatis oleh sistem keamanan PENINK.<br>Mohon jangan membalas email ini.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
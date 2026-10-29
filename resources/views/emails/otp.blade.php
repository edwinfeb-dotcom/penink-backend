<!DOCTYPE html>
<html>
<head>
    <title>Reset Password PENINK</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; border-top: 5px solid #1A2B4C; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        
        <!-- HEADER -->
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="color: #1A2B4C; margin: 0; font-size: 28px; letter-spacing: 1px;">PENINK</h2>
            <span style="color: #666; font-size: 13px;">Diskominfo Kabupaten Landak</span>
        </div>

        <p style="color: #333333; font-size: 16px;">Halo, <strong>{{ $user->name }}</strong>,</p>
        <p style="color: #333333; font-size: 16px; line-height: 1.5;">Kami menerima permintaan untuk mereset password akun PENINK Anda. Silakan klik tombol di bawah ini untuk membuat password baru:</p>
        
        <!-- TOMBOL RESET -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $url }}" style="background-color: #1A2B4C; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;">Reset Password</a>
        </div>
        
        <p style="color: #e74c3c; font-size: 14px; text-align: center;"><strong>Link ini hanya berlaku selama {{ $count }} menit.</strong></p>
        <p style="color: #666666; font-size: 14px; line-height: 1.5;">Jika Anda tidak merasa meminta reset password, silakan abaikan email ini. Akun Anda tetap aman.</p>
        
        <hr style="border: 0; border-top: 1px solid #eeeeee; margin: 30px 0;">
        
        <!-- FOOTER -->
        <p style="color: #999999; font-size: 12px; text-align: center; line-height: 1.5;">
            © 2026 Diskominfo Kabupaten Landak. All rights reserved.<br><br>
            Jika tombol di atas tidak berfungsi, copy dan paste link ini ke browser Anda:<br>
            <a href="{{ $url }}" style="color: #1A2B4C; word-break: break-all;">{{ $url }}</a>
        </p>
    </div>
</body>
</html>
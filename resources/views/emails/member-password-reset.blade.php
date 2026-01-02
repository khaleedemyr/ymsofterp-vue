<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="color: #333; margin-top: 0;">Reset Password Request</h2>
        
        <p>Hello {{ $member->nama_lengkap }},</p>
        
        <p>You have requested to reset your password. Click the link below to reset your password:</p>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" style="background-color: #FFD700; color: #000; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Reset Password</a>
        </p>
        
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #666; background-color: #f9f9f9; padding: 10px; border-radius: 5px;">{{ $resetUrl }}</p>
        
        <p style="color: #999; font-size: 12px; margin-top: 30px;">This link will expire in 60 minutes.</p>
        
        <p style="color: #999; font-size: 12px;">If you did not request this, please ignore this email.</p>
        
        <p style="margin-top: 30px;">
            Best regards,<br>
            <strong>JUSTUS GROUP</strong>
        </p>
    </div>
</body>
</html>


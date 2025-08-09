<!DOCTYPE html>
<html>
<head>
    <title>OTP Code</title>
</head>
<body>
    <h2>{{ $type === 'verification' ? 'Email Verification' : 'Password Reset' }}</h2>
    <p>Your OTP code is: <strong>{{ $otp }}</strong></p>
    <p>This code will expire in 10 minutes.</p>
    <p>If you didn't request this, please ignore this email.</p>
</body>
</html>

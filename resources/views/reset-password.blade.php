<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - JUSTUS GROUP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #FFD700 0%, #DBAB27 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .logo .just {
            color: #000;
        }
        
        .logo .us {
            color: #FFD700;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
            letter-spacing: 2px;
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #FFD700;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #000;
            color: #FFD700;
            border: 2px solid #FFD700;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #FFD700;
            color: #000;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .message {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            color: #000;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #FFD700;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>
                <span class="just">JUST</span><span class="us">US</span>
            </h1>
            <p>GROUP</p>
        </div>
        
        <h2>Reset Password</h2>
        
        <div id="message"></div>
        
        <form id="resetForm">
            <input type="hidden" id="token" value="{{ request('token') }}">
            <input type="hidden" id="email" value="{{ request('email') }}">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required minlength="6" placeholder="Enter your new password">
            </div>
            
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6" placeholder="Confirm your new password">
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                Reset Password
            </button>
        </form>
        
        <div class="back-link">
            <a href="https://ymsofterp.com">Back to Home</a>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('resetForm');
        const messageDiv = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');
        
        // Check if token and email are present
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token') || document.getElementById('token').value;
        const email = urlParams.get('email') || document.getElementById('email').value;
        
        if (!token || !email) {
            showMessage('Invalid reset link. Please request a new password reset.', 'error');
            form.style.display = 'none';
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('password_confirmation').value;
            
            if (password !== passwordConfirmation) {
                showMessage('Passwords do not match.', 'error');
                return;
            }
            
            if (password.length < 6) {
                showMessage('Password must be at least 6 characters.', 'error');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span> Resetting Password...';
            
            try {
                const response = await fetch('https://ymsofterp.com/api/mobile/member/auth/reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        email: email,
                        password: password,
                        password_confirmation: passwordConfirmation
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message || 'Password has been reset successfully! You can now login with your new password.', 'success');
                    form.style.display = 'none';
                    submitBtn.style.display = 'none';
                } else {
                    showMessage(data.message || 'Failed to reset password. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Reset Password';
                }
            } catch (error) {
                showMessage('An error occurred. Please try again later.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Reset Password';
            }
        });
        
        function showMessage(text, type) {
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = text;
            messageDiv.style.display = 'block';
        }
    </script>
</body>
</html>


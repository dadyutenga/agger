<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Monitoring System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #3498db, #2ecc71);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-title {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .form-control {
            border-radius: 20px;
            padding: 10px 20px;
        }
        .btn-login {
            border-radius: 20px;
            padding: 10px 20px;
            background: linear-gradient(120deg, #3498db, #2ecc71);
            border: none;
            width: 100%;
            margin-top: 20px;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="login-title">Patient Monitoring System</h2>
        <form action="login_process.php" method="post">
            <div class="mb-3">
                <input type="text" class="form-control" name="username" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-login">Login</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
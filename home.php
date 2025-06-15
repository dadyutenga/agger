<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --forest-green: #358927;
            --wattle-green: #D7DE50;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --dark-text: #2c3e50;
            --shadow: rgba(53, 137, 39, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--forest-green) 0%, #2d7a23 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(215, 222, 80, 0.1);
            border-radius: 50%;
            top: 10%;
            left: 10%;
            animation: float 6s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(215, 222, 80, 0.08);
            border-radius: 50%;
            bottom: 20%;
            right: 15%;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .welcome-container {
            text-align: center;
            padding: 50px 40px;
            background: var(--white);
            border-radius: 25px;
            box-shadow: 0 25px 50px var(--shadow);
            max-width: 650px;
            width: 90%;
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            margin-bottom: 40px;
            position: relative;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--forest-green), var(--wattle-green));
            border-radius: 20px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(53, 137, 39, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon i {
            font-size: 2.5rem;
            color: var(--white);
        }

        .logo h1 {
            color: var(--forest-green);
            font-size: 2.8rem;
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo p {
            color: #666;
            font-size: 1.2rem;
            font-weight: 400;
            line-height: 1.5;
        }

        .login-options {
            display: flex;
            gap: 25px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .login-btn {
            padding: 18px 45px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
            justify-content: center;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .doctor-btn {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
            box-shadow: 0 8px 25px rgba(53, 137, 39, 0.3);
        }

        .doctor-btn:hover {
            background: linear-gradient(135deg, #2d7a23, var(--forest-green));
            color: var(--white);
        }

        .caretaker-btn {
            background: linear-gradient(135deg, var(--wattle-green), #c5d645);
            color: var(--dark-text);
            box-shadow: 0 8px 25px rgba(215, 222, 80, 0.4);
        }

        .caretaker-btn:hover {
            background: linear-gradient(135deg, #c5d645, var(--wattle-green));
            color: var(--dark-text);
        }

        .feature-highlight {
            margin-top: 35px;
            padding: 25px;
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.05), rgba(215, 222, 80, 0.05));
            border-radius: 15px;
            border-left: 4px solid var(--forest-green);
        }

        .feature-highlight h5 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .feature-highlight p {
            color: #666;
            font-size: 0.95rem;
            margin: 0;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .welcome-container {
                padding: 40px 25px;
                margin: 20px;
            }
            
            .logo h1 {
                font-size: 2.2rem;
            }
            
            .login-options {
                flex-direction: column;
                align-items: center;
            }
            
            .login-btn {
                width: 100%;
                max-width: 280px;
            }
        }

        /* Loading animation */
        .welcome-container {
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-heartbeat"></i>
            </div>
            <h1>Patient Monitoring System</h1>
            <p>Advanced real-time monitoring and comprehensive care management platform</p>
        </div>
        
        <div class="login-options">
            <a href="index.php?type=doctor" class="login-btn doctor-btn">
                <i class="fas fa-user-md"></i>
                Doctor Portal
            </a>
            <a href="index.php?type=caretaker" class="login-btn caretaker-btn">
                <i class="fas fa-hands-helping"></i>
                Caretaker Portal
            </a>
        </div>

       
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scroll effect and interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effect to buttons
            const buttons = document.querySelectorAll('.login-btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add click ripple effect
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    let ripple = document.createElement('span');
                    let rect = this.getBoundingClientRect();
                    let size = Math.max(rect.width, rect.height);
                    let x = e.clientX - rect.left - size / 2;
                    let y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255,255,255,0.5);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
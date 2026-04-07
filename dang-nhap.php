<?php
require_once 'config.php';
session_start();
// Redirect if already logged in
// if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit; }
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập hệ thống | ROSA TECHNICAL</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="css/dang-nhap.css">
</head>

<body>
    <div class="header-container">
        <div class="sidebar-logo">
            <img src="./image/logo.png" alt="">
        </div>
    </div>

    <div class="login-card">
        <div class="left-panel">
            <div class="left-panel-content">
                <h1>HỆ THỐNG QUẢN LÝ <br> LẮP RÁP MÁY TÍNH </h1>
                <p>Giải pháp tối ưu cho quy trình lắp ráp, kiểm kho và vận hành nội bộ chuyên nghiệp </p>
                <div class="feature-icons">
                    <div class="icon-item" title="Security"><i data-lucide="shield-check"></i></div>
                    <div class="icon-item" title="Speed"><i data-lucide="gauge"></i></div>
                    <div class="icon-item" title="Hardware"><i data-lucide="box"></i></div>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <h2>Đăng nhập hệ thống</h2>
            <p class="welcome-text">Vui lòng nhập thông tin để truy cập nền tảng</p>

            <form action="dashboard-ke-toan.php" method="POST">
                <div class="form-group">
                    <div class="label-wrap">
                        <label for="username" id="l-username">Tên đăng nhập</label>
                    </div>
                    <div class="input-relative">
                        <i data-lucide="user" class="field-icon"></i>
                        <input type="text" name="username" id="username" class="input-field"
                            placeholder="Vui lòng nhập tên đăng nhập" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-wrap">
                        <label for="password" id="l-username">Mật khẩu</label>
                        <a href="#" class="forgot-pass">Quên mật khẩu?</a>
                    </div>
                    <div class="input-relative">
                        <i data-lucide="lock" class="field-icon"></i>
                        <input type="password" name="password" id="password" class="input-field" placeholder="••••••••"
                            required>
                        <i data-lucide="eye" class="password-toggle" id="eyeIcon"></i>
                    </div>
                </div>

                <div class="remember-row">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Ghi nhớ đăng nhập</label>
                </div>

                <button class="btn-submit" id="loginbtn" onclick="login()">Đăng nhập</button>
            </form>
            <!-- register form -->
            <div class="form" id="registerForm">
                <div class="field">
                    <label>Username</label>
                    <input type="text" id="r-username" placeholder="chọn username">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" id="password" placeholder="admin@gmail.com ">
                </div>
                <div class="field">
                    <label>Mật khẩu</label>
                    <input type="password" id="r-password" placeholder="Tối thiểu 6 ký tự">
                </div>
                <div class="msg" id="registerMsg"></div>
                <button class="btn-submit" id="registerBtn" onclick="register()">Tạo tào khoản</button>
            </div>
        </div>

        <div class="copyright-text">
            © 2026 ROSA - AI Computer
        </div>
    </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach((t, i) => {
                t.classList.toggle('active', (i === 0) === (tab === 'login'));
            });
            document.getElementById('loginForm').classList.toggle('active', tab === 'login');
            document.getElementById('registerForm').classList.toggle('active', tab === 'register');
        }

        function showMsg(id, text, type) {
            const el = document.getElementById(id);
            el.textContent = text;
            el.className = `msg ${type}`;
        }

        async function login() {
            const username = document.getElementById('l-username').value.trim();
            const password = document.getElementById('l-password').value;
            if (!username || !password) return showMsg('loginMsg', 'Vui lòng nhập đầy đủ', 'error');

            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            try {
                const res = await fetch('/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include', // Quan trọng: để nhận cookie
                    body: JSON.stringify({
                        username,
                        password
                    })
                });
                const data = await res.json();
                if (res.ok) {
                    showMsg('loginMsg', '✓ Đăng nhập thành công, đang chuyển hướng...', 'success');
                    setTimeout(() => window.location.href = '/', 800);
                } else {
                    showMsg('loginMsg', data.detail || 'Đăng nhập thất bại', 'error');
                }
            } catch {
                showMsg('loginMsg', 'Lỗi kết nối server', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Đăng nhập';
            }
        }

        async function register() {
            const username = document.getElementById('r-username').value.trim();
            const email = document.getElementById('r-email').value.trim();
            const password = document.getElementById('r-password').value;

            if (!username || !email || !password) return showMsg('registerMsg', 'Vui lòng nhập đầy đủ', 'error');
            if (password.length < 6) return showMsg('registerMsg', 'Mật khẩu tối thiểu 6 ký tự', 'error');

            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            try {
                const res = await fetch('/auth/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username,
                        email,
                        password
                    })
                });
                const data = await res.json();
                if (res.ok) {
                    showMsg('registerMsg', '✓ Tạo tài khoản thành công! Hãy đăng nhập.', 'success');
                    setTimeout(() => switchTab('login'), 1500);
                } else {
                    showMsg('registerMsg', data.detail || 'Đăng ký thất bại', 'error');
                }
            } catch {
                showMsg('registerMsg', 'Lỗi kết nối server', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Tạo tài khoản';
            }
        }

        // Enter key support
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const loginActive = document.getElementById('loginForm').classList.contains('active');
                loginActive ? login() : register();
            }
        });
    </script>

</body>

</html>
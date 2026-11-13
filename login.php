<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập rồi thì điều hướng theo role
if (!empty($_SESSION['user'])) {
    if (!empty($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    if ($password === '') {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    }

    if (empty($errors)) {
        // Lấy user theo email
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, role, status, password_hash FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $userRow = $rs ? mysqli_fetch_assoc($rs) : null;
        mysqli_stmt_close($stmt);

        if (!$userRow) {
            $errors[] = 'Email hoặc mật khẩu không đúng.';
        } else {
            if ((int)$userRow['status'] !== 1) {
                $errors[] = 'Tài khoản của bạn đang bị khóa. Vui lòng liên hệ quản trị viên.';
            } else {
                $storedHash = (string)($userRow['password_hash'] ?? '');

                // Detect hash kiểu mới (bcrypt/argon)
                $isModernHash = str_starts_with($storedHash, '$2y$')
                             || str_starts_with($storedHash, '$2a$')
                             || str_starts_with($storedHash, '$argon2');

                $ok = false;

                if ($isModernHash) {
                    $ok = password_verify($password, $storedHash);
                } else {
                    // MD5 cũ
                    $ok = (md5($password) === strtolower($storedHash));
                }

                if (!$ok) {
                    $errors[] = 'Email hoặc mật khẩu không đúng.';
                } else {
                    // ✅ Nếu đang là MD5 -> tự nâng cấp lên bcrypt sau khi login OK
                    if (!$isModernHash) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
                        mysqli_stmt_bind_param($upd, "si", $newHash, $userRow['id']);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                    }

                    // Lưu session (không lưu password_hash)
                    $user = [
                        'id'        => (int)$userRow['id'],
                        'full_name' => (string)$userRow['full_name'],
                        'email'     => (string)$userRow['email'],
                        'role'      => (string)$userRow['role'],
                        'status'    => (int)$userRow['status'],
                    ];

                    $_SESSION['user'] = $user;
                    $_SESSION['user_id'] = (int)$userRow['id'];

                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }
            }
        }
    }
}

// CHỈ include header sau khi xử lý login xong để tránh lỗi header already sent
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <h2 class="mb-3">Đăng nhập</h2>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">
                Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="card shadow-sm p-4">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-pink w-100 mb-2">Đăng nhập</button>
            <div class="text-center small">
                Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

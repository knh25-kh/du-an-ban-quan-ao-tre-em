<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function verify_password_compat(string $input, string $stored): bool {
    $stored = trim((string)$stored);

    // bcrypt ($2y$...) / argon2
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
        return password_verify($input, $stored);
    }

    // md5 32 hex
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
        return md5($input) === strtolower($stored);
    }

    // fallback (tránh dùng, nhưng để không “chết” nếu dữ liệu lạ)
    return hash_equals($stored, $input);
}

$success = '';
$error   = '';
$passMsg = null; // ['type' => 'success|danger', 'text' => '...']

/* ================== CẬP NHẬT THÔNG TIN ================== */
if (isset($_POST['update_info'])) {
    $full_name  = trim($_POST['full_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $gender     = $_POST['gender'] ?? 'other';
    $address    = trim($_POST['address'] ?? '');

    // Nullable birth_year: nếu rỗng -> 0 -> NULLIF(?,0) => NULL
    $birth_year = 0;
    if (isset($_POST['birth_year']) && trim($_POST['birth_year']) !== '') {
        $birth_year = (int)$_POST['birth_year'];
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE users
        SET full_name = ?, phone = ?, gender = ?, birth_year = NULLIF(?,0), address = ?
        WHERE id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "sssisi", $full_name, $phone, $gender, $birth_year, $address, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // update session để header hiển thị đúng tên
    $_SESSION['user']['full_name'] = $full_name;

    $success = "Cập nhật thông tin thành công.";
}

/* ================== ĐỔI MẬT KHẨU ================== */
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // ✅ FIX: form đang dùng old_password -> đọc đúng
    $current = trim($_POST['old_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($current === '') {
        $passMsg = ['type'=>'danger','text'=>'Vui lòng nhập mật khẩu hiện tại.'];
    } elseif ($new === '' || strlen($new) < 6) {
        $passMsg = ['type'=>'danger','text'=>'Mật khẩu mới tối thiểu 6 ký tự.'];
    } elseif ($new !== $confirm) {
        $passMsg = ['type'=>'danger','text'=>'Nhập lại mật khẩu mới không khớp.'];
    } else {
        // Lấy hash hiện tại từ DB
        $stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        mysqli_stmt_close($stmt);

        $stored = trim((string)($row['password_hash'] ?? ''));

        if ($stored === '' || !verify_password_compat($current, $stored)) {
            $passMsg = ['type'=>'danger','text'=>'Mật khẩu hiện tại không đúng.'];
        } else {
            // ✅ Lưu bcrypt (password_hash) cho chuẩn
            $newHash = password_hash($new, PASSWORD_DEFAULT);

            $upd = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($upd, "si", $newHash, $userId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $passMsg = ['type'=>'success','text'=>'Đổi mật khẩu thành công.'];
        }
    }
}

/* ================== LẤY USER ================== */
$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, phone, gender, birth_year, address FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

include 'includes/header.php';
?>

<div class="container py-4">
    <h2 class="mb-4">Quản lý tài khoản</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($passMsg)): ?>
        <div class="alert alert-<?php echo h($passMsg['type']); ?>">
            <?php echo h($passMsg['text']); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Thông tin cá nhân -->
        <div class="col-md-7">
            <form method="post" class="card p-4">
                <h5 class="mb-3">Thông tin cá nhân</h5>

                <input type="hidden" name="update_info" value="1">

                <div class="mb-2">
                    <label class="form-label">Họ tên</label>
                    <input class="form-control" name="full_name" value="<?php echo h($user['full_name'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input class="form-control" value="<?php echo h($user['email'] ?? ''); ?>" disabled>
                </div>

                <div class="mb-2">
                    <label class="form-label">Số điện thoại</label>
                    <input class="form-control" name="phone" value="<?php echo h($user['phone'] ?? ''); ?>">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Giới tính</label>
                        <select class="form-select" name="gender">
                            <option value="male"   <?php if(($user['gender'] ?? '')==='male') echo 'selected'; ?>>Nam</option>
                            <option value="female" <?php if(($user['gender'] ?? '')==='female') echo 'selected'; ?>>Nữ</option>
                            <option value="other"  <?php if(($user['gender'] ?? 'other')==='other') echo 'selected'; ?>>Khác</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Năm sinh</label>
                        <input class="form-control" name="birth_year"
                               value="<?php echo h($user['birth_year'] ?? ''); ?>"
                               placeholder="VD: 2005">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <textarea class="form-control" name="address" rows="3"
                              placeholder="Nhập địa chỉ"><?php echo h($user['address'] ?? ''); ?></textarea>
                </div>

                <button class="btn btn-pink">Lưu thay đổi</button>
            </form>
        </div>

        <!-- Đổi mật khẩu -->
        <div class="col-md-5">
            <form method="post" class="card p-4">
                <h5 class="mb-3">Đổi mật khẩu</h5>

                <input type="hidden" name="action" value="change_password">

                <!-- ✅ name old_password để khớp PHP ở trên -->
                <input class="form-control mb-2" type="password" name="old_password" placeholder="Mật khẩu hiện tại" required>
                <input class="form-control mb-2" type="password" name="new_password" placeholder="Mật khẩu mới (>= 6 ký tự)" required>
                <input class="form-control mb-3" type="password" name="confirm_password" placeholder="Nhập lại mật khẩu mới" required>

                <button class="btn btn-outline-secondary w-100">Đổi mật khẩu</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

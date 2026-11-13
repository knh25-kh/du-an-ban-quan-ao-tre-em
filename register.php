<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
include 'includes/header.php';

$errors = [];
$full_name = $email = $phone = $gender = $birth_year = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $phone      = trim($_POST['phone'] ?? '');
    $gender     = $_POST['gender'] ?? 'other';
    $birth_year = trim($_POST['birth_year'] ?? '');
    $address    = trim($_POST['address'] ?? '');

    /* ===== Validate ===== */
    if ($full_name === '') {
        $errors[] = 'Họ tên không được để trống.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải từ 6 ký tự trở lên.';
    }

    if ($password !== $password2) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    $birthYearVal = null;
    if ($birth_year !== '') {
        $y = (int)$birth_year;
        if ($y < 1900 || $y > (int)date('Y')) {
            $errors[] = 'Năm sinh không hợp lệ.';
        } else {
            $birthYearVal = $y;
        }
    }

    /* ===== Check email exists ===== */
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Email này đã được đăng ký.';
        }
        mysqli_stmt_close($stmt);
    }

    /* ===== Insert user ===== */
    if (empty($errors)) {
        // Hash mật khẩu bằng bcrypt (chuẩn)
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "
            INSERT INTO users
                (full_name, email, password_hash, phone, gender, birth_year, address, role, status)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'customer', 1)
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "sssssis",
            $full_name,
            $email,
            $password_hash,
            $phone,
            $gender,
            $birthYearVal,
            $address
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: login.php?registered=1');
            exit;
        } else {
            $errors[] = 'Lỗi hệ thống, vui lòng thử lại sau.';
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <h2 class="mb-3">Đăng ký tài khoản</h2>
        <p class="text-muted mb-4">
            Tạo tài khoản để mua sắm và theo dõi đơn hàng dễ dàng hơn.
        </p>

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
                <label class="form-label">Họ và tên *</label>
                <input type="text" name="full_name" class="form-control"
                       value="<?php echo htmlspecialchars($full_name); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mật khẩu *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nhập lại mật khẩu *</label>
                    <input type="password" name="password2" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Số điện thoại</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($phone); ?>">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Giới tính</label>
                    <select name="gender" class="form-select">
                        <option value="other"  <?php echo $gender === 'other'  ? 'selected' : ''; ?>>Khác / Không nói</option>
                        <option value="male"   <?php echo $gender === 'male'   ? 'selected' : ''; ?>>Nam</option>
                        <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Nữ</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Năm sinh</label>
                    <input type="number" name="birth_year" class="form-control"
                           value="<?php echo htmlspecialchars($birth_year); ?>"
                           min="1900" max="<?php echo date('Y'); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Địa chỉ</label>
                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($address); ?></textarea>
            </div>

            <button type="submit" class="btn btn-pink w-100 mb-2">
                Đăng ký
            </button>

            <div class="text-center">
                Đã có tài khoản? <a href="login.php">Đăng nhập</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

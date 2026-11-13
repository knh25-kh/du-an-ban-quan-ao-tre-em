<?php
require_once 'config/db.php';
include 'includes/header.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
?>

<div class="text-center my-5">
    <h2 class="mb-3 text-pink">Đặt hàng thành công!</h2>
    <?php if ($order_id > 0): ?>
        <p class="mb-2">
            Mã đơn hàng của bạn là: <strong>#<?php echo $order_id; ?></strong>
        </p>
    <?php endif; ?>
    <p class="mb-4">
        Chúng tôi sẽ liên hệ xác nhận và giao hàng trong thời gian sớm nhất.
    </p>
    <a href="orders.php" class="btn btn-pink me-2">Xem đơn hàng</a>
    <a href="products.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
</div>

<?php include 'includes/footer.php'; ?>

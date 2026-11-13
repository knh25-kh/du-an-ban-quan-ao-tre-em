<?php
require_once 'config/db.php';
include 'includes/header.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user']['id'];

$sql = "SELECT * FROM orders
        WHERE user_id = $user_id
        ORDER BY created_at DESC";
$rs = mysqli_query($conn, $sql);
?>

<h2 class="mb-3">Đơn hàng của tôi</h2>

<?php if (!$rs || mysqli_num_rows($rs) === 0): ?>
    <div class="alert alert-info">
        Bạn chưa có đơn hàng nào. <a href="products.php">Mua sắm ngay</a>.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày tạo</th>
                    <th>Người nhận</th>
                    <th>Địa chỉ</th>
                    <th class="text-end">Tổng tiền</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($o = mysqli_fetch_assoc($rs)): ?>
                    <tr>
                        <td>#<?php echo $o['id']; ?></td>
                        <td><?php echo $o['created_at']; ?></td>
                        <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($o['address']); ?></td>
                        <td class="text-end">
                            <?php echo number_format($o['total_price']); ?> đ
                        </td>
                        <td>
                            <?php
                            $statusText = [
                                'pending'   => 'Chờ xác nhận',
                                'confirmed' => 'Đã xác nhận',
                                'shipping'  => 'Đang giao',
                                'completed' => 'Hoàn thành',
                                'cancelled' => 'Đã hủy'
                            ];
                            echo $statusText[$o['status']] ?? $o['status'];
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

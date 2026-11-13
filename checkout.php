<?php
// checkout.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ưu tiên dùng giỏ hàng đã chọn từ cart.php
$cart = $_SESSION['checkout_cart'] ?? ($_SESSION['cart'] ?? []);

// Nếu giỏ hàng trống thì cho quay về trang giỏ hàng
if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

// Bắt buộc đăng nhập mới được đặt hàng
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user   = $_SESSION['user'];
$errors = [];

// Giá trị mặc định cho form
$name           = $user['full_name'] ?? '';
$phone          = '';
$address        = 'Hà Nội, Việt Nam';
$note           = '';
$payment_method = 'cod'; // cod | bank | momo

// Tính tổng tiền thật (real total)
$realTotal = 0;
foreach ($cart as $item) {
    $realTotal += (int)$item['price'] * (int)$item['qty'];
}

// ================= XỬ LÝ SUBMIT ================= //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $note           = trim($_POST['note'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';

    // QR confirmed (chỉ dùng cho online)
    $qr_confirmed = ($_POST['qr_confirmed'] ?? '0') === '1';

    // Validate đơn giản
    if ($name === '')    $errors[] = 'Vui lòng nhập họ tên người nhận.';
    if ($phone === '')   $errors[] = 'Vui lòng nhập số điện thoại.';
    if ($address === '') $errors[] = 'Vui lòng nhập địa chỉ giao hàng.';
    if (!in_array($payment_method, ['cod','bank','momo'], true)) {
        $errors[] = 'Phương thức thanh toán không hợp lệ.';
    }

    // Nếu online mà chưa confirm QR => không cho tạo đơn
    if (in_array($payment_method, ['bank','momo'], true) && !$qr_confirmed) {
        $errors[] = 'Vui lòng quét QR và bấm "Tôi đã chuyển khoản" để tiếp tục.';
    }

    if (empty($errors)) {
        $uid = (int)$user['id'];

        // Theo yêu cầu: online => lưu total_price = 0
        $saveTotal = $realTotal;
        if (in_array($payment_method, ['bank','momo'], true)) {
            $saveTotal = 0;
        }

        mysqli_begin_transaction($conn);

        try {
            // 1. Tạo đơn hàng
            $orderSql = "
                INSERT INTO orders (
                    user_id,
                    customer_name,
                    phone,
                    address,
                    total_price,
                    payment_method,
                    status,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ";

            $stmt = mysqli_prepare($conn, $orderSql);
            if (!$stmt) {
                throw new Exception('Lỗi chuẩn bị câu lệnh orders: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "isssis",
                $uid,
                $name,
                $phone,
                $address,
                $saveTotal,
                $payment_method
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Lỗi khi thêm đơn hàng: ' . mysqli_stmt_error($stmt));
            }

            $orderId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // 2. Thêm các sản phẩm vào order_items
            $itemSql = "
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ";
            $stmtItem = mysqli_prepare($conn, $itemSql);
            if (!$stmtItem) {
                throw new Exception('Lỗi chuẩn bị câu lệnh order_items: ' . mysqli_error($conn));
            }

            foreach ($cart as $item) {
                $pid   = (int)($item['id'] ?? $item['product_id'] ?? 0);
                $qty   = (int)$item['qty'];
                $price = (int)$item['price'];

                mysqli_stmt_bind_param(
                    $stmtItem,
                    "iiii",
                    $orderId,
                    $pid,
                    $qty,
                    $price
                );

                if (!mysqli_stmt_execute($stmtItem)) {
                    throw new Exception('Lỗi khi thêm sản phẩm vào order_items: ' . mysqli_stmt_error($stmtItem));
                }
            }

            mysqli_stmt_close($stmtItem);

            // 3. Commit
            mysqli_commit($conn);

            // 4. Xoá các sản phẩm đã đặt khỏi giỏ
            // Nếu đang dùng checkout_cart (đã chọn từ cart.php)
            if (isset($_SESSION['checkout_cart'])) {
                // Nếu bạn đang dùng giỏ DB thì không xoá session cart ở đây.
                // Nhưng hiện tại logic session guest vẫn ok.
                foreach ($_SESSION['checkout_cart'] as $key => $_item) {
                    // session cart có thể khác key, nên chỉ unset nếu tồn tại
                    if (isset($_SESSION['cart'][$key])) {
                        unset($_SESSION['cart'][$key]);
                    }
                }
                unset($_SESSION['checkout_cart']);
            } else {
                unset($_SESSION['cart']);
            }

            // 5. Redirect
            header('Location: order_success.php?id=' . $orderId);
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Có lỗi xảy ra khi tạo đơn hàng: ' . $e->getMessage();
        }
    }
}

// TỪ ĐÂY TRỞ ĐI MỚI OUTPUT HTML
include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-3">Thanh toán đơn hàng</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $er): ?>
                        <li><?php echo htmlspecialchars($er); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="card shadow-sm p-4 mb-4" id="checkoutForm">
            <h5 class="mb-3">Thông tin giao hàng</h5>

            <!-- hidden: xác nhận đã quét QR -->
            <input type="hidden" name="qr_confirmed" id="qr_confirmed" value="0">

            <div class="mb-3">
                <label class="form-label">Họ tên người nhận *</label>
                <input type="text" name="name" class="form-control"
                       value="<?php echo htmlspecialchars($name); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Số điện thoại *</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($phone); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Địa chỉ giao hàng *</label>
                <textarea name="address" rows="3" class="form-control" required><?php
                    echo htmlspecialchars($address);
                ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Ghi chú thêm</label>
                <textarea name="note" rows="3" class="form-control"
                          placeholder="Không bắt buộc"><?php
                    echo htmlspecialchars($note);
                ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Phương thức thanh toán</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio"
                           name="payment_method" id="pm_cod"
                           value="cod" <?php echo ($payment_method === 'cod') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="pm_cod">
                        Thanh toán khi nhận hàng (COD)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio"
                           name="payment_method" id="pm_bank"
                           value="bank" <?php echo ($payment_method === 'bank') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="pm_bank">
                        Chuyển khoản ngân hàng
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio"
                           name="payment_method" id="pm_momo"
                           value="momo" <?php echo ($payment_method === 'momo') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="pm_momo">
                        Ví MoMo
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-pink w-100" id="btnSubmitOrder">
                Xác nhận đặt hàng (<?php echo number_format($realTotal); ?> đ)
            </button>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm p-3">
            <h5 class="mb-3">Tóm tắt đơn hàng</h5>

            <?php foreach ($cart as $item): ?>
                <div class="d-flex justify-content-between mb-2">
                    <div>
                        <div class="fw-semibold">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </div>
                        <div class="small text-muted">
                            x <?php echo (int)$item['qty']; ?>
                            <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                (
                                <?php if (!empty($item['size'])): ?>
                                    Size: <?php echo htmlspecialchars($item['size']); ?>
                                <?php endif; ?>
                                <?php if (!empty($item['color'])): ?>
                                    <?php if (!empty($item['size'])) echo ' | '; ?>
                                    Màu: <?php echo htmlspecialchars($item['color']); ?>
                                <?php endif; ?>
                                )
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php echo number_format((int)$item['price'] * (int)$item['qty']); ?> đ
                    </div>
                </div>
            <?php endforeach; ?>

            <hr>
            <div class="d-flex justify-content-between">
                <strong>Tổng cộng:</strong>
                <span class="text-pink fw-bold">
                    <?php echo number_format($realTotal); ?> đ
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL QR ===== -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Thanh toán online</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body text-center">
        <p class="mb-2" id="qrTitle">Quét mã QR để chuyển khoản.</p>

        <img id="qrImg" src="assets/img/qr_bank.png" alt="QR thanh toán"
             style="max-width:320px;width:100%;height:auto;">

        <div class="small text-muted mt-2">
          Chuyển khoản xong bấm “Tôi đã chuyển khoản” để tạo đơn (đơn sẽ lưu tạm total = 0đ).
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
        <button type="button" class="btn btn-pink" id="btnPaidThenCreate">Tôi đã chuyển khoản</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('checkoutForm');
  const hidden = document.getElementById('qr_confirmed');
  const btnPaid = document.getElementById('btnPaidThenCreate');
  const qrImg = document.getElementById('qrImg');
  const qrTitle = document.getElementById('qrTitle');

  function getPay() {
    const el = document.querySelector('input[name="payment_method"]:checked');
    return el ? el.value : 'cod';
  }

  if (!form) return;

  form.addEventListener('submit', function (e) {
    const pay = getPay();

    // Online mà chưa confirmed => chặn submit và mở QR
    if ((pay === 'bank' || pay === 'momo') && hidden.value !== '1') {
      e.preventDefault();

      // đổi ảnh QR theo phương thức
      if (pay === 'momo') {
        qrTitle.textContent = 'Quét QR MoMo để thanh toán.';
        qrImg.src = 'assets/img/qr_momo.jpg';
      } else {
        qrTitle.textContent = 'Quét QR ngân hàng để chuyển khoản.';
        qrImg.src = 'assets/img/qr_bank.jpg';
      }

      const modal = new bootstrap.Modal(document.getElementById('qrModal'));
      modal.show();
    }
  });

  btnPaid?.addEventListener('click', function () {
    hidden.value = '1';
    form.submit();
  });
})();
</script>

<?php include 'includes/footer.php'; ?>

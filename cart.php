<?php
// cart.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_logged_in_user_id() {
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    if (!empty($_SESSION['auth']['id'])) return (int)$_SESSION['auth']['id'];
    if (!empty($_SESSION['id'])) return (int)$_SESSION['id'];
    return 0;
}

$userId = get_logged_in_user_id();
$isLoggedIn = $userId > 0;

/**
 * Lưu/đọc trạng thái tick (theo user hoặc guest)
 * - Logged in: lưu theo user id để không lẫn người khác
 * - Guest: lưu 1 key chung
 */
$selectedSessionKey = $isLoggedIn ? ('cart_selected_keys_u' . $userId) : 'cart_selected_keys_guest';
$selectedKeys = $_SESSION[$selectedSessionKey] ?? [];
if (!is_array($selectedKeys)) $selectedKeys = [];

/* =========================================================
   MODE 1: LOGGED IN => DB cart_items
========================================================= */

// --- REMOVE (GET) ---
if ($isLoggedIn && isset($_GET['remove'])) {
    $cid = (int)$_GET['remove'];
    if ($cid > 0) {
        $del = mysqli_prepare($conn, "DELETE FROM cart_items WHERE id = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($del, "ii", $cid, $userId);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }

    // nếu vừa xóa 1 dòng đang tick -> bỏ khỏi selectedKeys
    $selectedKeys = array_values(array_filter($selectedKeys, fn($v) => (string)$v !== (string)$cid));
    $_SESSION[$selectedSessionKey] = $selectedKeys;

    header('Location: cart.php');
    exit;
}

// --- POST actions (update / checkout) ---
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Lấy tick hiện tại từ form (nếu có)
    $postedSelected = $_POST['selected'] ?? [];
    $postedSelectedIds = array_keys($postedSelected); // dạng string keys
    // lưu vào session để sau reload vẫn giữ
    $_SESSION[$selectedSessionKey] = $postedSelectedIds;
    $selectedKeys = $postedSelectedIds;

    if ($action === 'update') {
        $qtyForm = $_POST['qty'] ?? [];

        foreach ($qtyForm as $cid => $q) {
            $cid = (int)$cid;
            $q   = (int)$q;
            if ($cid <= 0) continue;

            if ($q <= 0) {
                $del = mysqli_prepare($conn, "DELETE FROM cart_items WHERE id = ? AND user_id = ? LIMIT 1");
                mysqli_stmt_bind_param($del, "ii", $cid, $userId);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);

                // xóa khỏi selectedKeys nếu đang tick
                $selectedKeys = array_values(array_filter($selectedKeys, fn($v) => (string)$v !== (string)$cid));
            } else {
                $upd = mysqli_prepare($conn, "UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? LIMIT 1");
                mysqli_stmt_bind_param($upd, "iii", $q, $cid, $userId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        }

        // cập nhật lại selectedKeys vào session sau khi xóa dòng
        $_SESSION[$selectedSessionKey] = $selectedKeys;

        header('Location: cart.php');
        exit;
    }

    if ($action === 'checkout') {
        $selected = $_POST['selected'] ?? [];
        $selectedIds = array_map('intval', array_keys($selected));
        $selectedIds = array_values(array_filter($selectedIds, fn($v) => $v > 0));

        if (empty($selectedIds)) {
            header('Location: cart.php?error=no_selection');
            exit;
        }

        // Lấy đúng các dòng cart_items thuộc user + được tick
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $types = str_repeat('i', count($selectedIds) + 1); // +1 cho user_id

        $sql = "
            SELECT
                ci.id AS cart_item_id,
                ci.product_id,
                ci.size,
                ci.color,
                ci.image,
                ci.quantity,
                p.name,
                p.price
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id = ?
              AND ci.id IN ($placeholders)
        ";

        $stmt = mysqli_prepare($conn, $sql);

        // bind_param động
        $params = array_merge([$userId], $selectedIds);
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);

        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);

        $checkoutCart = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $checkoutCart[(int)$row['cart_item_id']] = [
                'cart_item_id' => (int)$row['cart_item_id'],
                'product_id'   => (int)$row['product_id'],
                'id'           => (int)$row['product_id'],
                'name'         => (string)$row['name'],
                'price'        => (int)$row['price'],
                'image'        => (string)($row['image'] ?? ''),
                'qty'          => (int)$row['quantity'],
                'size'         => (string)($row['size'] ?? ''),
                'color'        => (string)($row['color'] ?? ''),
            ];
        }
        mysqli_stmt_close($stmt);

        if (empty($checkoutCart)) {
            header('Location: cart.php?error=no_selection');
            exit;
        }

        $_SESSION['checkout_cart'] = $checkoutCart;
        header('Location: checkout.php');
        exit;
    }
}

// --- Load cart from DB ---
$cart = [];
if ($isLoggedIn) {
    $sql = "
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            ci.size,
            ci.color,
            ci.image,
            ci.quantity,
            p.name,
            p.price
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.user_id = ?
        ORDER BY ci.updated_at DESC, ci.id DESC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $cid = (int)$row['cart_item_id'];
        $cart[$cid] = [
            'cart_item_id' => $cid,
            'product_id'   => (int)$row['product_id'],
            'id'           => (int)$row['product_id'],
            'name'         => (string)$row['name'],
            'price'        => (int)$row['price'],
            'image'        => (string)($row['image'] ?? ''),
            'qty'          => (int)$row['quantity'],
            'size'         => (string)($row['size'] ?? ''),
            'color'        => (string)($row['color'] ?? ''),
        ];
    }
    mysqli_stmt_close($stmt);

    // dọn selectedKeys: bỏ các key không còn tồn tại trong cart
    if (!empty($selectedKeys)) {
        $valid = array_map('strval', array_keys($cart));
        $selectedKeys = array_values(array_filter($selectedKeys, fn($k) => in_array((string)$k, $valid, true)));
        $_SESSION[$selectedSessionKey] = $selectedKeys;
    }
}

/* =========================================================
   MODE 2: GUEST => SESSION cart
========================================================= */
if (!$isLoggedIn) {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $cart = $_SESSION['cart'];

    if (isset($_GET['remove'])) {
        $key = (string)$_GET['remove'];
        if (isset($cart[$key])) {
            unset($cart[$key]);
            $_SESSION['cart'] = $cart;
        }

        // remove khỏi selectedKeys
        $selectedKeys = array_values(array_filter($selectedKeys, fn($v) => (string)$v !== (string)$key));
        $_SESSION[$selectedSessionKey] = $selectedKeys;

        header('Location: cart.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // lưu tick hiện tại
        $postedSelected = $_POST['selected'] ?? [];
        $selectedKeys = array_keys($postedSelected);
        $_SESSION[$selectedSessionKey] = $selectedKeys;

        if ($action === 'update') {
            $qtyForm = $_POST['qty'] ?? [];
            foreach ($qtyForm as $key => $q) {
                $key = (string)$key;
                $q   = (int)$q;
                if (!isset($cart[$key])) continue;

                if ($q <= 0) {
                    unset($cart[$key]);
                    // xóa khỏi selectedKeys nếu đang tick
                    $selectedKeys = array_values(array_filter($selectedKeys, fn($v) => (string)$v !== (string)$key));
                } else {
                    $cart[$key]['qty'] = $q;
                }
            }
            $_SESSION['cart'] = $cart;
            $_SESSION[$selectedSessionKey] = $selectedKeys;

            header('Location: cart.php');
            exit;
        }

        if ($action === 'checkout') {
            $selected = $_POST['selected'] ?? [];
            $selectedKeys = array_keys($selected);

            $checkoutCart = [];
            foreach ($selectedKeys as $key) {
                $key = (string)$key;
                if (isset($cart[$key])) $checkoutCart[$key] = $cart[$key];
            }

            if (empty($checkoutCart)) {
                header('Location: cart.php?error=no_selection');
                exit;
            }

            $_SESSION['checkout_cart'] = $checkoutCart;
            header('Location: checkout.php');
            exit;
        }
    }

    // dọn selectedKeys: bỏ key không còn tồn tại trong cart session
    if (!empty($selectedKeys)) {
        $valid = array_map('strval', array_keys($cart));
        $selectedKeys = array_values(array_filter($selectedKeys, fn($k) => in_array((string)$k, $valid, true)));
        $_SESSION[$selectedSessionKey] = $selectedKeys;
    }
}

/* =========================================================
   UI
========================================================= */
include 'includes/header.php';

$total = 0;
foreach ($cart as $item) {
    $price = (int)($item['price'] ?? 0);
    $qty   = (int)($item['qty'] ?? 0);
    $total += $price * $qty;
}

$errorMsg = '';
if (isset($_GET['error']) && $_GET['error'] === 'no_selection') {
    $errorMsg = 'Vui lòng chọn ít nhất một sản phẩm để đặt hàng.';
}
?>

<div class="container py-4">
    <h1 class="mb-3">Giỏ hàng của bạn</h1>

    <?php if ($errorMsg): ?>
        <div class="alert alert-warning"><?php echo h($errorMsg); ?></div>
    <?php endif; ?>

    <?php if (empty($cart)): ?>
        <p>Giỏ hàng đang trống. <a href="products.php">Mua sắm ngay</a></p>
    <?php else: ?>

        <form method="post">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th style="width:40px;">
                        <!-- KHÔNG checked mặc định -->
                        <input type="checkbox" id="check-all">
                    </th>
                    <th>Sản phẩm</th>
                    <th style="width: 120px;">Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                    <th></th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($cart as $rowKey => $item): ?>
                    <?php
                        $removeKey   = $rowKey;
                        $selectedKey = $rowKey;
                        $qtyKey      = $rowKey;
                        $pidLink     = (int)($item['product_id'] ?? $item['id'] ?? 0);

                        $isChecked = in_array((string)$selectedKey, $selectedKeys, true);
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox"
                                   name="selected[<?php echo h($selectedKey); ?>]"
                                   class="item-check"
                                   <?php echo $isChecked ? 'checked' : ''; ?>>
                        </td>

                        <td>
                            <div class="d-flex align-items-center">
                                <img src="assets/img/<?php echo h($item['image']); ?>"
                                     alt="<?php echo h($item['name']); ?>"
                                     style="width: 60px; height: 60px; object-fit: cover;"
                                     class="me-2">

                                <div>
                                    <a href="product_detail.php?id=<?php echo $pidLink; ?>">
                                        <?php echo h($item['name']); ?>
                                    </a>

                                    <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                        <div class="small text-muted">
                                            <?php if (!empty($item['size'])): ?>
                                                Size: <?php echo h($item['size']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($item['color'])): ?>
                                                <?php if (!empty($item['size'])) echo ' | '; ?>
                                                Màu: <?php echo h($item['color']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td>
                            <input type="number"
                                   name="qty[<?php echo h($qtyKey); ?>]"
                                   class="form-control form-control-sm"
                                   min="0"
                                   value="<?php echo (int)$item['qty']; ?>">
                        </td>

                        <td><?php echo number_format((int)$item['price']); ?> đ</td>
                        <td><?php echo number_format(((int)$item['price']) * ((int)$item['qty'])); ?> đ</td>

                        <td>
                            <a href="cart.php?remove=<?php echo urlencode((string)$removeKey); ?>"
                               class="text-danger small"
                               onclick="return confirm('Xoá sản phẩm này khỏi giỏ?');">
                                Xóa
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                <a href="products.php" class="btn btn-outline-secondary">
                    ← Tiếp tục mua sắm
                </a>

                <div class="text-end">
                    <div class="mb-2">
                        <strong>Tổng cộng: </strong>
                        <span class="text-pink fw-bold"><?php echo number_format((int)$total); ?> đ</span>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="submit" name="action" value="update" class="btn btn-outline-secondary">
                            Cập nhật giỏ hàng
                        </button>
                        <button type="submit" name="action" value="checkout" class="btn btn-pink">
                            Tiến hành đặt hàng
                        </button>
                    </div>
                </div>
            </div>
        </form>

    <?php endif; ?>
</div>

<script>
const checkAll = document.getElementById('check-all');
const itemChecks = () => Array.from(document.querySelectorAll('.item-check'));

function syncCheckAll(){
  if (!checkAll) return;
  const list = itemChecks();
  if (list.length === 0) { checkAll.checked = false; return; }
  checkAll.checked = list.every(cb => cb.checked);
}

checkAll?.addEventListener('change', function () {
  itemChecks().forEach(cb => cb.checked = this.checked);
});

document.querySelectorAll('.item-check').forEach(cb => {
  cb.addEventListener('change', syncCheckAll);
});

syncCheckAll();
</script>

<?php include 'includes/footer.php'; ?>

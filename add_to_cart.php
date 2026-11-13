<?php
// add_to_cart.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   Helpers
========================= */
function normalize_text($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}
function canon_space($s) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}
function make_cart_key($productId, $size, $color) {
    $sizeN  = normalize_text($size);
    $colorN = normalize_text($color);
    return $productId . '|' . $sizeN . '|' . $colorN;
}
function get_logged_in_user_id() {
    // Bạn có thể đang lưu user theo nhiều kiểu, mình check vài kiểu phổ biến
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    if (!empty($_SESSION['auth']['id'])) return (int)$_SESSION['auth']['id'];
    if (!empty($_SESSION['id'])) return (int)$_SESSION['id'];
    return 0;
}

/* =========================
   Input
========================= */
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$qty       = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
$qty       = max(1, $qty);

$size  = canon_space($_POST['size']  ?? '');
$color = canon_space($_POST['color'] ?? '');

$ref = $_POST['ref'] ?? ("product_detail.php?id=" . $productId);

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

/* =========================
   Load product
========================= */
$sql = "SELECT id, name, price, image
        FROM products
        WHERE id = ? AND status = 1
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

if (!$rs || mysqli_num_rows($rs) === 0) {
    header('Location: index.php');
    exit;
}
$product = mysqli_fetch_assoc($rs);

/* =========================
   Pick image by color (product_images)
========================= */
$finalImage = (string)($product['image'] ?? '');

if ($color !== '') {
    $ck = normalize_text($color);

    // Nếu bảng product_images có cột color_key (đã normalize/lower) thì truy vấn như này là chuẩn
    $imgSql = "SELECT image
               FROM product_images
               WHERE product_id = ?
                 AND LOWER(TRIM(color_key)) = ?
               ORDER BY sort_order ASC, id ASC
               LIMIT 1";
    $imgStmt = @mysqli_prepare($conn, $imgSql);
    if ($imgStmt) {
        mysqli_stmt_bind_param($imgStmt, "is", $productId, $ck);
        mysqli_stmt_execute($imgStmt);
        $imgRs = mysqli_stmt_get_result($imgStmt);
        if ($imgRs && ($row = mysqli_fetch_assoc($imgRs))) {
            $img = trim((string)($row['image'] ?? ''));
            if ($img !== '') $finalImage = $img;
        }
        mysqli_stmt_close($imgStmt);
    }
}

/* =========================
   Save cart:
   - Logged in => DB cart_items
   - Guest     => SESSION cart
========================= */
$userId = get_logged_in_user_id();

if ($userId > 0) {
    // DB cart_items: uniq_cart_variant(user_id, product_id, size, color)
    $ins = "INSERT INTO cart_items (user_id, product_id, size, color, image, quantity)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                image    = VALUES(image),
                updated_at = CURRENT_TIMESTAMP";

    $st = mysqli_prepare($conn, $ins);
    if (!$st) {
        // fallback an toàn: nếu vì lý do gì prepare fail
        $_SESSION['cart'] = $_SESSION['cart'] ?? [];
        $cartKey = make_cart_key($productId, $size, $color);
        if (isset($_SESSION['cart'][$cartKey])) $_SESSION['cart'][$cartKey]['qty'] += $qty;
        else {
            $_SESSION['cart'][$cartKey] = [
                'cart_key'   => $cartKey,
                'product_id' => (int)$product['id'],
                'id'         => (int)$product['id'],
                'name'       => (string)$product['name'],
                'price'      => (int)$product['price'],
                'image'      => (string)$finalImage,
                'qty'        => $qty,
                'size'       => $size,
                'color'      => $color,
            ];
        }
    } else {
        $pname  = (string)$product['name'];   // không lưu vào cart_items nhưng để debug nếu cần
        $price  = (int)$product['price'];     // không lưu vào cart_items (cart.php sẽ join products để lấy giá)
        $img    = (string)$finalImage;
        $q      = (int)$qty;
        $sz     = (string)$size;
        $cl     = (string)$color;

        mysqli_stmt_bind_param($st, "iisssi", $userId, $productId, $sz, $cl, $img, $q);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

} else {
    // Guest => session variant key
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $cartKey = make_cart_key($productId, $size, $color);

    if (isset($_SESSION['cart'][$cartKey])) {
        $_SESSION['cart'][$cartKey]['qty'] += $qty;
        $_SESSION['cart'][$cartKey]['size']  = $size;
        $_SESSION['cart'][$cartKey]['color'] = $color;
        $_SESSION['cart'][$cartKey]['image'] = $finalImage;
    } else {
        $_SESSION['cart'][$cartKey] = [
            'cart_key'   => $cartKey,
            'product_id' => (int)$product['id'],
            'id'         => (int)$product['id'],
            'name'       => (string)$product['name'],
            'price'      => (int)$product['price'],
            'image'      => (string)$finalImage,
            'qty'        => $qty,
            'size'       => $size,
            'color'      => $color,
        ];
    }
}

/* =========================
   Redirect back
========================= */
$sep = (strpos($ref, '?') === false) ? '?' : '&';
header("Location: {$ref}{$sep}added=1");
exit;

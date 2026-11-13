<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
include 'includes/header.php';

function normalize_text($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="alert alert-danger">Sản phẩm không hợp lệ.</div>';
    include 'includes/footer.php';
    exit;
}

$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = $id AND p.status = 1
        LIMIT 1";
$rs = mysqli_query($conn, $sql);
$product = $rs ? mysqli_fetch_assoc($rs) : null;

if (!$product) {
    echo '<div class="alert alert-danger">Không tìm thấy sản phẩm.</div>';
    include 'includes/footer.php';
    exit;
}

$added = isset($_GET['added']);

/* ===== PARSE SIZE & MÀU ===== */
$sizeField  = array_key_exists('sizes',  $product) ? 'sizes'
            : (array_key_exists('size',  $product) ? 'size'  : null);
$colorField = array_key_exists('colors', $product) ? 'colors'
            : (array_key_exists('color', $product) ? 'color' : null);

$sizes  = [];
$colors = [];

if ($sizeField && !empty($product[$sizeField])) {
    $sizes = array_filter(array_map('trim', explode(',', $product[$sizeField])));
}
if ($colorField && !empty($product[$colorField])) {
    $colors = array_filter(array_map('trim', explode(',', $product[$colorField])));
}

/* ===== MAP MÀU -> HEX ===== */
$COLOR_MAP = [
    'đen' => '#000000',
    'trắng' => '#ffffff',
    'kem' => '#f6f0d9',
    'vàng' => '#f6d64a',
    'cam' => '#ff9f1a',
    'đỏ' => '#e53935',
    'hồng' => '#ff6fae',
    'tím' => '#8e44ad',
    'xanh dương' => '#2f6bff',
    'xanh biển' => '#2f6bff',
    'xanh lá' => '#43a047',
    'nâu' => '#8d6e63',
    'xám' => '#9e9e9e',
    'bạc' => '#d9d9d9',
];

/* ===== ẢNH THEO MÀU (nếu có bảng product_images) ===== */
$imagesByColor = [];   // ['do' => ['img1.jpg',...]]
$allImages = [];       // full paths for slider (unique)
$mainImgFile = trim((string)($product['image'] ?? ''));

if ($mainImgFile !== '') {
    $allImages[] = "assets/img/" . $mainImgFile; // ảnh chính luôn là slide đầu tiên
}

$imgSql = "SELECT color_key, image
           FROM product_images
           WHERE product_id = $id
           ORDER BY sort_order ASC, id ASC";
$imgRs = @mysqli_query($conn, $imgSql);
if ($imgRs) {
    while ($row = mysqli_fetch_assoc($imgRs)) {
        $ck  = normalize_text($row['color_key'] ?? '');
        $img = trim((string)($row['image'] ?? ''));
        if ($ck === '' || $img === '') continue;

        $imagesByColor[$ck][] = $img;
        $allImages[] = "assets/img/" . $img;
    }
}
$allImages = array_values(array_unique($allImages));

/* ===== map: colorKey -> slideIndex (để chọn màu nhảy đúng slide) ===== */
$colorToSlideIndex = [];
foreach ($colors as $i => $colorName) {
    $key = normalize_text($colorName);

    // ưu tiên ảnh theo màu, nếu không có thì ảnh chính
    $imgFile = $mainImgFile;
    if (!empty($imagesByColor[$key][0])) {
        $imgFile = $imagesByColor[$key][0];
    }
    $fullPath = "assets/img/" . $imgFile;

    // tìm index trong allImages
    $idx = array_search($fullPath, $allImages, true);
    if ($idx === false) $idx = 0;
    $colorToSlideIndex[$key] = $idx;
}

/* Gợi ý sản phẩm cùng danh mục */
$relatedSql = "SELECT id, name, price, image
               FROM products
               WHERE status = 1 AND category_id = " . (int)$product['category_id'] . "
                 AND id <> $id
               ORDER BY created_at DESC
               LIMIT 4";
$relatedRs = mysqli_query($conn, $relatedSql);
?>

<?php if (!empty($added)): ?>
  <div class="alert alert-success mb-3">
      Đã thêm sản phẩm vào giỏ hàng. <a href="cart.php">Xem giỏ hàng</a>
  </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-5 mb-3 mb-lg-0">
        <div class="card shadow-sm border-0">
            <div class="ratio ratio-4x3 pd-img-wrap">
                <div class="pd-stage">
                    <div class="pd-track" id="pdTrack">
                        <?php foreach ($allImages as $img): ?>
                            <img src="<?php echo htmlspecialchars($img); ?>"
                                class="pd-slide"
                                alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($allImages) > 1): ?>
                        <button type="button" class="pd-nav pd-prev" aria-label="Prev"><span>‹</span></button>
                        <button type="button" class="pd-nav pd-next" aria-label="Next"><span>›</span></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

  <div class="col-lg-7">
    <h1 class="h3 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>

    <div class="mb-2 small text-muted">
      Danh mục: <?php echo htmlspecialchars($product['category_name']); ?>
    </div>

    <div class="h4 text-pink mb-3">
      <?php echo number_format((int)$product['price']); ?> đ
    </div>

    <?php if (!empty($product['age_range'])): ?>
      <div class="mb-2">
        <strong>Độ tuổi phù hợp:</strong> <?php echo htmlspecialchars($product['age_range']); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($product['gender'])): ?>
      <div class="mb-2">
        <strong>Giới tính:</strong>
        <?php
          $genderText = ['boy'=>'Bé trai','girl'=>'Bé gái','unisex'=>'Cả bé trai và bé gái'];
          echo $genderText[$product['gender']] ?? 'Không xác định';
        ?>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <strong>Mô tả sản phẩm:</strong>
      <p class="mt-1"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
    </div>

    <form action="add_to_cart.php" method="post" class="card border-0 shadow-sm p-3 mb-3">
      <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
      <input type="hidden" name="ref" value="product_detail.php?id=<?php echo (int)$product['id']; ?>">

      <?php if (!empty($sizes)): ?>
        <div class="mb-3">
          <label class="form-label"><strong>Chọn size:</strong></label>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($sizes as $index => $size): ?>
              <?php $idSize = 'size_' . $index; ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input"
                       type="radio"
                       name="size"
                       id="<?php echo $idSize; ?>"
                       value="<?php echo htmlspecialchars($size); ?>"
                       <?php echo $index === 0 ? 'checked' : ''; ?>>
                <label class="form-check-label" for="<?php echo $idSize; ?>">
                  <?php echo htmlspecialchars($size); ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($colors)): ?>
        <div class="mb-3">
          <label class="form-label"><strong>Màu sắc:</strong></label>

          <div class="pd-color-list">
            <?php foreach ($colors as $index => $color): ?>
              <?php
                $idColor = 'color_' . $index;
                $key = normalize_text($color);
                $hex = $COLOR_MAP[$key] ?? '#ffffff';
                $isWhite = ($hex === '#ffffff');
                $slideIndex = $colorToSlideIndex[$key] ?? 0;
              ?>
              <label class="pd-color-item" title="<?php echo htmlspecialchars($color); ?>">
                <input class="pd-color-input"
                       type="radio"
                       name="color"
                       id="<?php echo $idColor; ?>"
                       value="<?php echo htmlspecialchars($color); ?>"
                       data-slide="<?php echo (int)$slideIndex; ?>">
                <span class="pd-color-swatch <?php echo $isWhite ? 'is-white' : ''; ?>"
                      style="--swatch: <?php echo htmlspecialchars($hex); ?>;"></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="small text-muted mt-1">Di chuột vào màu để xem tên màu.</div>
        </div>
      <?php endif; ?>

      <div class="mb-3 d-flex align-items-center gap-2">
        <label class="form-label mb-0"><strong>Số lượng:</strong></label>
        <input type="number" name="qty" class="form-control" value="1" min="1" style="max-width: 90px;">
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-pink">Thêm vào giỏ</button>
        <a href="products.php" class="btn btn-outline-secondary">← Quay lại danh sách</a>
      </div>
    </form>
  </div>
</div>

<?php if ($relatedRs && mysqli_num_rows($relatedRs) > 0): ?>
  <hr class="my-4">
  <h2 class="h5 mb-3">Sản phẩm cùng danh mục</h2>
  <div class="row g-3">
    <?php while ($r = mysqli_fetch_assoc($relatedRs)): ?>
      <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm border-0 product-card">
          <div class="ratio ratio-4x3">
            <img src="assets/img/<?php echo htmlspecialchars($r['image']); ?>"
                 class="card-img-top rounded-top object-fit-cover"
                 alt="<?php echo htmlspecialchars($r['name']); ?>">
          </div>
          <div class="card-body p-2">
            <h3 class="card-title product-title">
              <a href="product_detail.php?id=<?php echo (int)$r['id']; ?>">
                <?php echo htmlspecialchars($r['name']); ?>
              </a>
            </h3>
            <div class="fw-bold text-pink">
              <?php echo number_format((int)$r['price']); ?> đ
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const track = document.getElementById('pdTrack');
  if (!track) return;

  const slides = track.querySelectorAll('.pd-slide');
  const total = slides.length;

  const prevBtn = document.querySelector('.pd-prev');
  const nextBtn = document.querySelector('.pd-next');

  let index = 0;

  function goTo(i){
    if (!total) return;
    index = (i + total) % total;
    track.style.transform = `translateX(-${index * 100}%)`;
  }

  if (prevBtn) prevBtn.addEventListener('click', () => goTo(index - 1));
  if (nextBtn) nextBtn.addEventListener('click', () => goTo(index + 1));

  // chọn màu -> nhảy slide tương ứng (KHÔNG auto chọn màu khi load)
  document.querySelectorAll('.pd-color-input').forEach(radio => {
    radio.addEventListener('change', () => {
      const s = parseInt(radio.getAttribute('data-slide') || '0', 10);
      goTo(isNaN(s) ? 0 : s);
    });
  });

  // đảm bảo luôn bắt đầu ở ảnh chính
  goTo(0);
});
</script>

<?php include 'includes/footer.php'; ?>

<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
include 'includes/header.php';

/**
 * 1. SẢN PHẨM MỚI (toàn shop)
 */
$homeSql = "SELECT p.id, p.name, p.price, p.image, c.name AS category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.status = 1
            ORDER BY p.created_at DESC
            LIMIT 8";
$homeRs = mysqli_query($conn, $homeSql);

/**
 * 2. SẢN PHẨM BÉ GÁI
 */
$girlsSql = "SELECT p.id, p.name, p.price, p.image, c.name AS category_name
             FROM products p
             JOIN categories c ON p.category_id = c.id
             WHERE p.status = 1
               AND p.gender IN ('girl', 'unisex')
             ORDER BY p.created_at DESC
             LIMIT 4";
$girlsRs = mysqli_query($conn, $girlsSql);

/**
 * 3. SẢN PHẨM BÉ TRAI
 */
$boysSql = "SELECT p.id, p.name, p.price, p.image, c.name AS category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.status = 1
              AND p.gender IN ('boy', 'unisex')
            ORDER BY p.created_at DESC
            LIMIT 4";
$boysRs = mysqli_query($conn, $boysSql);
/**
 * 4. LẤY ID CÁC DANH MỤC CHI TIẾT (Đầm váy, Áo, Đồ bộ...)
 *    để làm link products.php?category_id=...
 */
$iconSql = "SELECT id, name, slug 
            FROM categories
            WHERE slug IN (
                'dam-vay',
                'ao',
                'do-bo',
                'do-lot',
                'quan',
                'chan-vay',
                'phu-kien',
                'ao-khoac'
            )";

$iconRs = mysqli_query($conn, $iconSql);

$iconCategories = [];
if ($iconRs) {
    while ($row = mysqli_fetch_assoc($iconRs)) {
        $iconCategories[$row['slug']] = $row;
    }
}

/**
 * Cấu hình dải icon danh mục 
 */
$iconConfig = [
    'dam-vay'  => ['label' => 'Đầm váy',  'image' => 'dam_vay.png'],
    'ao'       => ['label' => 'Áo',       'image' => 'ao.png'],
    'do-bo'    => ['label' => 'Đồ bộ',    'image' => 'do_bo.png'],
    'do-lot'   => ['label' => 'Đồ lót',   'image' => 'do_lot.png'],
    'quan'     => ['label' => 'Quần',     'image' => 'quan.png'],
    'chan-vay' => ['label' => 'Chân váy', 'image' => 'chan_vay.png'],
    'phu-kien' => ['label' => 'Phụ kiện', 'image' => 'phu_kien.png'],
    'ao-khoac' => ['label' => 'Áo khoác', 'image' => 'ao_khoac.png'],
];

?>

<!-- Thanh khuyến mãi -->
<div class="promo-bar text-center">
    Miễn phí giao hàng toàn quốc cho hóa đơn từ <strong>299.000đ</strong>
</div>

<!-- CAROUSEL BANNER -->
<section class="home-carousel">
    <div id="homeCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="0" class="active"
                    aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="1"
                    aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="2"
                    aria-label="Slide 3"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active" data-bs-interval="5000">
                <img src="assets/img/banner_1.jpg" class="d-block w-100 home-banner-img"
                     alt="Bộ sưu tập Thu Đông">
            </div>
            <div class="carousel-item" data-bs-interval="5000">
                <img src="assets/img/banner_2.png" class="d-block w-100 home-banner-img"
                     alt="Ưu đãi -10%">
            </div>
            <div class="carousel-item" data-bs-interval="5000">
                <img src="assets/img/banner_3.jpg" class="d-block w-100 home-banner-img"
                     alt="Thời trang cho bé sơ sinh">
            </div>
        </div>
        <button class="carousel-control-prev" type="button"
                data-bs-target="#homeCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Trước</span>
        </button>
        <button class="carousel-control-next" type="button"
                data-bs-target="#homeCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Sau</span>
        </button>
    </div>
</section>

<!-- CÂU CHUYỆN BỘ SƯU TẬP -->
<section class="story-section">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <div class="story-badge mb-2">Câu chuyện bộ sưu tập</div>
                <h2 class="story-title mb-3">
                    “Mùa quả mộng chín”
                </h2>
                <p class="story-text mb-2">
                    Hà My Baby kể về tuổi thơ trong trẻo, rộn ràng và ấm áp như mùa quả đang độ chín rộ,
                    giữa khoảnh khắc thiên nhiên đang khẽ chuyển mình sang đông.
                </p>
                <p class="story-text mb-3">
                    Mỗi thiết kế là một mẩu chuyện nhỏ, nơi bé có thể chạy nhảy, cười đùa và khám phá thế giới
                    với những gam màu rực rỡ, họa tiết đáng yêu.
                </p>
                <p class="story-signature">
                    play in joy, grow in love ♡
                </p>
            </div>
            <div class="col-lg-6">
                <div class="story-image-wrapper">
                    <img src="assets/img/story_section.jpg"
                         alt="Câu chuyện bộ sưu tập Hà My Baby"
                         class="img-fluid story-image">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DẢI DANH MỤC SẢN PHẨM (Đầm váy / Áo / Đồ bộ / ...) -->
<section class="home-section home-category-strip">
    <div class="container">
        <div class="text-center mb-3">
            <h2 class="home-section-title mb-1">Danh mục sản phẩm</h2>
            <div class="text-muted small">Chọn nhanh kiểu đồ bé cần</div>
        </div>

        <div class="row home-category-row">
            <?php foreach ($iconConfig as $slug => $cfg): 
                $catId = $iconCategories[$slug]['id'] ?? null;
                $label = $cfg['label'];
                $image = $cfg['image'];
                $link  = $catId 
                    ? 'products.php?category_id=' . $catId
                    : 'products.php?keyword=' . urlencode($label);
            ?>
                <div class="col-6 col-sm-3 col-md-3 col-lg-1-8 home-category-col">
                    <a href="<?php echo $link; ?>" 
                       class="text-decoration-none text-dark home-category-item">
                        <div class="home-category-icon">
                            <img src="assets/img/<?php echo htmlspecialchars($image); ?>"
                                 alt="<?php echo htmlspecialchars($label); ?>">
                        </div>
                        <div class="home-category-label">
                            <?php echo htmlspecialchars($label); ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- THỜI TRANG BÉ GÁI -->
<section class="home-section bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="home-section-title mb-0">Thời trang Bé gái</h2>
            <a href="products.php?gender=girl" class="text-pink small">
                Xem tất cả →
            </a>
        </div>

        <div class="row g-3">
            <?php if ($girlsRs && mysqli_num_rows($girlsRs) > 0): ?>
                <?php while ($p = mysqli_fetch_assoc($girlsRs)): ?>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="product_detail.php?id=<?php echo $p['id']; ?>"
                           class="card h-100 text-decoration-none text-dark product-card-link">
                            <img src="assets/img/<?php echo htmlspecialchars($p['image']); ?>"
                                 class="card-img-top"
                                 alt="<?php echo htmlspecialchars($p['name']); ?>">
                            <div class="card-body">
                                <h6 class="card-title mb-1">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </h6>
                                <div class="text-pink fw-bold">
                                    <?php echo number_format($p['price']); ?> đ
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Chưa có sản phẩm cho danh mục Bé gái.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- THỜI TRANG BÉ TRAI -->
<section class="home-section bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="home-section-title mb-0">Thời trang Bé trai</h2>
            <a href="products.php?gender=boy" class="text-pink small">
                Xem tất cả →
            </a>
        </div>

        <div class="row g-3">
            <?php if ($boysRs && mysqli_num_rows($boysRs) > 0): ?>
                <?php while ($p = mysqli_fetch_assoc($boysRs)): ?>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="product_detail.php?id=<?php echo $p['id']; ?>"
                           class="card h-100 text-decoration-none text-dark product-card-link">
                            <img src="assets/img/<?php echo htmlspecialchars($p['image']); ?>"
                                 class="card-img-top"
                                 alt="<?php echo htmlspecialchars($p['name']); ?>">
                            <div class="card-body">
                                <h6 class="card-title mb-1">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </h6>
                                <div class="text-pink fw-bold">
                                    <?php echo number_format($p['price']); ?> đ
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Chưa có sản phẩm cho danh mục Bé trai.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- SẢN PHẨM MỚI (toàn shop) -->
<section class="home-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="home-section-title mb-0">Sản phẩm mới</h2>
            <a href="products.php" class="text-pink small">
                Xem tất cả →
            </a>
        </div>

        <div class="row g-3">
            <?php if ($homeRs && mysqli_num_rows($homeRs) > 0): ?>
                <?php while ($p = mysqli_fetch_assoc($homeRs)): ?>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="product_detail.php?id=<?php echo $p['id']; ?>"
                           class="card h-100 text-decoration-none text-dark product-card-link">
                            <img src="assets/img/<?php echo htmlspecialchars($p['image']); ?>"
                                 class="card-img-top"
                                 alt="<?php echo htmlspecialchars($p['name']); ?>">
                            <div class="card-body">
                                <h6 class="card-title mb-1">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </h6>
                                <div class="text-pink fw-bold">
                                    <?php echo number_format($p['price']); ?> đ
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Chưa có sản phẩm để hiển thị.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

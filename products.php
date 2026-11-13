<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
include 'includes/header.php';

/* =========================
   Helpers
========================= */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function normalize_text($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

function parse_csv_list($s) {
    if ($s === null) return [];
    $parts = preg_split('/\s*,\s*/u', (string)$s);
    $parts = array_map('trim', $parts);
    $parts = array_filter($parts, fn($v) => $v !== '');
    return array_values(array_unique($parts));
}

function extract_years_from_sizes($sizes) {
    $years = [];
    if (preg_match_all('/(\d{1,2})\s*Y/i', (string)$sizes, $m)) {
        foreach ($m[1] as $y) $years[] = (int)$y;
    }
    return array_values(array_unique($years));
}

function extract_age_range_from_text($age_range) {
    $t = normalize_text($age_range);

    // 0-12 tháng
    if (preg_match('/(\d{1,2})\s*-\s*(\d{1,2})\s*tháng/u', $t, $m)) {
        return ['unit' => 'month', 'min' => (int)$m[1], 'max' => (int)$m[2]];
    }
    // 3-5 tuổi
    if (preg_match('/(\d{1,2})\s*-\s*(\d{1,2})\s*tuổi/u', $t, $m)) {
        return ['unit' => 'year', 'min' => (int)$m[1], 'max' => (int)$m[2]];
    }
    // 3 tuổi
    if (preg_match('/\b(\d{1,2})\s*tuổi\b/u', $t, $m)) {
        $y = (int)$m[1];
        return ['unit' => 'year', 'min' => $y, 'max' => $y];
    }
    // 12 tháng
    if (preg_match('/\b(\d{1,2})\s*tháng\b/u', $t, $m)) {
        $mo = (int)$m[1];
        return ['unit' => 'month', 'min' => $mo, 'max' => $mo];
    }
    return null;
}

function extract_weight_ranges_from_sizes($sizes) {
    $ranges = [];

    // 2Y-11-12kg
    if (preg_match_all('/\b\d{1,2}\s*Y\s*-\s*(\d{1,2})\s*-\s*(\d{1,2})\s*kg\b/i', (string)$sizes, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) $ranges[] = [(int)$mm[1], (int)$mm[2]];
    }
    // 11-12kg
    if (preg_match_all('/\b(\d{1,2})\s*-\s*(\d{1,2})\s*kg\b/i', (string)$sizes, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $mm) $ranges[] = [(int)$mm[1], (int)$mm[2]];
    }

    $uniq = [];
    foreach ($ranges as $r) $uniq[$r[0].'-'.$r[1]] = $r;
    return array_values($uniq);
}

function range_overlap($aMin, $aMax, $bMin, $bMax) {
    return max($aMin, $bMin) <= min($aMax, $bMax);
}

function build_url_keep($overrides = []) {
    $q = $_GET;
    foreach ($overrides as $k=>$v) {
        if ($v === null || $v === '' || $v === []) unset($q[$k]);
        else $q[$k] = $v;
    }
    $qs = http_build_query($q);
    return 'products.php' . ($qs ? ('?' . $qs) : '');
}

// remove 1 value from CSV param
function build_url_remove_value($key, $valueToRemove) {
    $arr = parse_csv_list($_GET[$key] ?? '');
    $arr = array_values(array_filter($arr, fn($v) => normalize_text($v) !== normalize_text($valueToRemove)));
    $new = $arr ? implode(',', $arr) : null;
    return build_url_keep([$key => $new]);
}

function chip_remove_one($label, $key, $value) {
    $url = build_url_remove_value($key, $value);
    return '<a class="hb-chip" href="'.h($url).'"><span>'.h($label).'</span><b>×</b></a>';
}

/* =========================
   Load categories
========================= */
$categories = [];
$catSql = "SELECT id, name FROM categories ORDER BY name";
$catRs  = mysqli_query($conn, $catSql);
if ($catRs) {
    while ($row = mysqli_fetch_assoc($catRs)) $categories[] = $row;
}

/* =========================
   Options like Rabity
========================= */
$ageOptions = [
    ['key'=>'0-12m', 'label'=>'Từ 0-12 tháng'],
    ['key'=>'1-3',   'label'=>'Từ 1-3 tuổi'],
    ['key'=>'3-5',   'label'=>'Từ 3-5 tuổi'],
    ['key'=>'5-6',   'label'=>'Từ 5-6 tuổi'],
    ['key'=>'6-7',   'label'=>'Từ 6-7 tuổi'],
    ['key'=>'7-8',   'label'=>'Từ 7-8 tuổi'],
    ['key'=>'8-9',   'label'=>'Từ 8-9 tuổi'],
    ['key'=>'9-10',  'label'=>'Từ 9-10 tuổi'],
    ['key'=>'10-11', 'label'=>'Từ 10-11 tuổi'],
    ['key'=>'11-12', 'label'=>'Từ 11-12 tuổi'],
    ['key'=>'12-13', 'label'=>'Từ 12-13 tuổi'],
    ['key'=>'13-14', 'label'=>'Từ 13-14 tuổi'],
];

$weightOptions = [
    ['key'=>'9-11',   'label'=>'Từ 9-11kg'],
    ['key'=>'11-12',  'label'=>'Từ 11-12kg'],
    ['key'=>'12-13',  'label'=>'Từ 12-13kg'],
    ['key'=>'14-16',  'label'=>'Từ 14-16kg'],
    ['key'=>'17-19',  'label'=>'Từ 17-19kg'],
    ['key'=>'19-21',  'label'=>'Từ 19-21kg'],
    ['key'=>'21-23',  'label'=>'Từ 21-23kg'],
    ['key'=>'23-25',  'label'=>'Từ 23-25kg'],
    ['key'=>'25-27',  'label'=>'Từ 25-27kg'],
    ['key'=>'27-31',  'label'=>'Từ 27-31kg'],
    ['key'=>'31-33',  'label'=>'Từ 31-33kg'],
    ['key'=>'33-35',  'label'=>'Từ 33-35kg'],
    ['key'=>'35-37',  'label'=>'Từ 35-37kg'],
    ['key'=>'37-40',  'label'=>'Từ 37-40kg'],
];

// nhiều màu như Rabity
$colorOptions = [
    ['name'=>'Đen',   'hex'=>'#000000'],
    ['name'=>'Trắng', 'hex'=>'#ffffff'],
    ['name'=>'Kem',   'hex'=>'#f6f0d9'],
    ['name'=>'Vàng',  'hex'=>'#f6d64a'],
    ['name'=>'Cam',   'hex'=>'#ff9f1a'],
    ['name'=>'Đỏ',    'hex'=>'#e53935'],
    ['name'=>'Hồng',  'hex'=>'#ff6fae'],
    ['name'=>'Tím',   'hex'=>'#8e44ad'],
    ['name'=>'Xanh dương','hex'=>'#2f6bff'],
    ['name'=>'Xanh lá','hex'=>'#43a047'],
    ['name'=>'Nâu',   'hex'=>'#8d6e63'],
    ['name'=>'Xám',   'hex'=>'#9e9e9e'],
    ['name'=>'Bạc',   'hex'=>'#d9d9d9'],
];

/* =========================
   GET filters (multi-select)
========================= */
$genderArr   = parse_csv_list($_GET['gender'] ?? '');        // girl,boy,unisex
$ageArr      = parse_csv_list($_GET['age'] ?? '');           // 1-3,3-5,0-12m
$weightArr   = parse_csv_list($_GET['weight'] ?? '');        // 11-12,14-16
$catArrRaw   = parse_csv_list($_GET['category_id'] ?? '');   // 1,2,3
$colorArr    = parse_csv_list($_GET['color'] ?? '');         // Tím,Đen,...

$min_price   = (int)($_GET['min_price'] ?? 0);
$max_price   = (int)($_GET['max_price'] ?? 0);

// category ids sanitize
$categoryIds = [];
foreach ($catArrRaw as $v) {
    if (preg_match('/^\d+$/', $v)) $categoryIds[] = (int)$v;
}
$categoryIds = array_values(array_unique(array_filter($categoryIds, fn($x)=>$x>0)));

/* =========================
   Build SQL (lọc được cái nào lọc trước)
========================= */
$where = "WHERE p.status = 1";

// category IN (...)
if ($categoryIds) {
    $in = implode(',', array_map('intval', $categoryIds));
    $where .= " AND p.category_id IN ($in)";
}

// price
if ($min_price > 0) $where .= " AND p.price >= " . (int)$min_price;
if ($max_price > 0) $where .= " AND p.price <= " . (int)$max_price;

// gender union logic: girl => girl+unisex; boy => boy+unisex; unisex => unisex
if ($genderArr) {
    $allowed = [];
    foreach ($genderArr as $g) {
        $g = strtolower($g);
        if ($g === 'girl') { $allowed[] = 'girl'; $allowed[] = 'unisex'; }
        if ($g === 'boy')  { $allowed[] = 'boy';  $allowed[] = 'unisex'; }
        if ($g === 'unisex') { $allowed[] = 'unisex'; }
    }
    $allowed = array_values(array_unique($allowed));
    $allowedEsc = array_map(fn($x)=>"'".mysqli_real_escape_string($conn, $x)."'", $allowed);
    $where .= " AND p.gender IN (" . implode(',', $allowedEsc) . ")";
}

$productSql = "
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY p.created_at DESC
";
$rs = mysqli_query($conn, $productSql);

$products = [];
if ($rs) {
    while ($p = mysqli_fetch_assoc($rs)) $products[] = $p;
}

/* =========================
   PHP filters: age / weight / color (multi => match ANY selected)
========================= */

// build age filters list
$ageFilters = [];
foreach ($ageArr as $age) {
    if (preg_match('/^(\d{1,2})-(\d{1,2})m$/i', $age, $m)) {
        $ageFilters[] = ['unit'=>'month','min'=>(int)$m[1],'max'=>(int)$m[2]];
    } elseif (preg_match('/^(\d{1,2})-(\d{1,2})$/', $age, $m)) {
        $ageFilters[] = ['unit'=>'year','min'=>(int)$m[1],'max'=>(int)$m[2]];
    }
}

// build weight filters list
$weightFilters = [];
foreach ($weightArr as $w) {
    if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $w, $m)) {
        $weightFilters[] = ['min'=>(int)$m[1],'max'=>(int)$m[2]];
    }
}

$colorNormArr = array_map('normalize_text', $colorArr);

$filtered = [];
foreach ($products as $p) {

    // color: match ANY selected
    if ($colorNormArr) {
        $colors = parse_csv_list($p['colors'] ?? '');
        $colorsNorm = array_map('normalize_text', $colors);
        $okColor = false;
        foreach ($colorNormArr as $cNeed) {
            if (in_array($cNeed, $colorsNorm, true)) { $okColor = true; break; }
        }
        if (!$okColor) continue;
    }

    // age: match ANY selected
    if ($ageFilters) {
        $okAge = false;

        $years = extract_years_from_sizes($p['sizes'] ?? '');
        $ar = extract_age_range_from_text($p['age_range'] ?? '');

        foreach ($ageFilters as $f) {

            // prefer sizes years
            if ($f['unit'] === 'year' && $years) {
                foreach ($years as $y) {
                    if ($y >= $f['min'] && $y <= $f['max']) { $okAge = true; break 2; }
                }
            }

            // fallback age_range text
            if ($ar) {
                if ($f['unit'] === $ar['unit']) {
                    if (range_overlap($f['min'], $f['max'], $ar['min'], $ar['max'])) { $okAge = true; break; }
                } else {
                    // month <-> year approx
                    if ($f['unit'] === 'year' && $ar['unit'] === 'month') {
                        $minY = $ar['min'] / 12.0; $maxY = $ar['max'] / 12.0;
                        if (!($f['max'] < $minY || $f['min'] > $maxY)) { $okAge = true; break; }
                    } elseif ($f['unit'] === 'month' && $ar['unit'] === 'year') {
                        $minM = $ar['min'] * 12; $maxM = $ar['max'] * 12;
                        if (range_overlap($f['min'], $f['max'], $minM, $maxM)) { $okAge = true; break; }
                    }
                }
            }
        }

        if (!$okAge) continue;
    }

    // weight: match ANY selected
    if ($weightFilters) {
        $ranges = extract_weight_ranges_from_sizes($p['sizes'] ?? '');
        $okW = false;
        foreach ($weightFilters as $wf) {
            foreach ($ranges as $r) {
                if (range_overlap($wf['min'], $wf['max'], $r[0], $r[1])) { $okW = true; break 2; }
            }
        }
        if (!$okW) continue;
    }

    $filtered[] = $p;
}
$products = $filtered;

$count = count($products);

// map labels for chips
$ageLabelMap = [];
foreach ($ageOptions as $o) $ageLabelMap[$o['key']] = $o['label'];
$wLabelMap = [];
foreach ($weightOptions as $o) $wLabelMap[$o['key']] = $o['label'];

?>
<div class="container hb-products-page">
    <div class="hb-products-header">
        <h2 class="hb-title">Sản phẩm</h2>
        <div class="hb-count"><?php echo (int)$count; ?> sản phẩm</div>
    </div>

    <!-- Filter bar -->
    <div class="hb-filterbar" id="hbFilterBar">
        <button class="hb-pill hb-pill-filter" type="button" data-panel="panel-filter">
            <span class="hb-icon-filter" aria-hidden="true"></span>
            Lọc
        </button>

        <button class="hb-pill" type="button" data-panel="panel-gender">Giới tính <i class="hb-caret"></i></button>
        <button class="hb-pill" type="button" data-panel="panel-age">Độ tuổi <i class="hb-caret"></i></button>
        <button class="hb-pill" type="button" data-panel="panel-weight">Cân nặng <i class="hb-caret"></i></button>
        <button class="hb-pill" type="button" data-panel="panel-category">Loại sản phẩm <i class="hb-caret"></i></button>
        <button class="hb-pill" type="button" data-panel="panel-color">Màu sắc <i class="hb-caret"></i></button>
        <button class="hb-pill" type="button" data-panel="panel-price">Giá <i class="hb-caret"></i></button>

        <a class="hb-clear" href="products.php">Xóa lọc</a>
    </div>

    <!-- Chips -->
    <div class="hb-chips">
        <?php
        // gender chips
        foreach ($genderArr as $g) {
            if ($g === 'girl')   echo chip_remove_one('Bé gái', 'gender', 'girl');
            if ($g === 'boy')    echo chip_remove_one('Bé trai', 'gender', 'boy');
            if ($g === 'unisex') echo chip_remove_one('Unisex', 'gender', 'unisex');
        }

        // age chips
        foreach ($ageArr as $a) {
            $label = $ageLabelMap[$a] ?? $a;
            echo chip_remove_one($label, 'age', $a);
        }

        // weight chips
        foreach ($weightArr as $w) {
            $label = $wLabelMap[$w] ?? $w;
            echo chip_remove_one($label, 'weight', $w);
        }

        // category chips
        if ($categoryIds) {
            foreach ($categoryIds as $cid) {
                $catName = (string)$cid;
                foreach ($categories as $c) {
                    if ((int)$c['id'] === (int)$cid) { $catName = $c['name']; break; }
                }
                echo chip_remove_one('Loại: '.$catName, 'category_id', (string)$cid);
            }
        }

        // color chips
        foreach ($colorArr as $c) {
            echo chip_remove_one('Màu: '.$c, 'color', $c);
        }

        // price chip (remove both)
        if ($min_price > 0 || $max_price > 0) {
            $pLabel = 'Giá: ' . ($min_price > 0 ? number_format($min_price) : '0') . ' - ' . ($max_price > 0 ? number_format($max_price) : '∞');
            echo '<a class="hb-chip" href="'.h(build_url_keep(['min_price'=>null,'max_price'=>null])).'"><span>'.h($pLabel).'</span><b>×</b></a>';
        }
        ?>
    </div>

    <!-- Global apply form (JS sẽ đổ giá trị vào hidden inputs) -->
    <form id="hbApplyForm" method="get" action="products.php">
        <input type="hidden" name="gender" id="hb_gender" value="<?php echo h(implode(',', $genderArr)); ?>">
        <input type="hidden" name="age" id="hb_age" value="<?php echo h(implode(',', $ageArr)); ?>">
        <input type="hidden" name="weight" id="hb_weight" value="<?php echo h(implode(',', $weightArr)); ?>">
        <input type="hidden" name="category_id" id="hb_category_id" value="<?php echo h(implode(',', array_map('strval', $categoryIds))); ?>">
        <input type="hidden" name="color" id="hb_color" value="<?php echo h(implode(',', $colorArr)); ?>">
        <input type="hidden" name="min_price" id="hb_min_price" value="<?php echo (int)$min_price; ?>">
        <input type="hidden" name="max_price" id="hb_max_price" value="<?php echo (int)$max_price; ?>">
    </form>

    <!-- Panels -->
    <div class="hb-panels">

        <!-- panel-filter -->
        <div class="hb-panel" id="panel-filter">
            <div class="hb-panel-body hb-filter-hint">
                Chọn tiêu chí phía trên để lọc sản phẩm.
            </div>
        </div>

        <!-- Giới tính -->
        <div class="hb-panel" id="panel-gender" data-key="gender">
            <div class="hb-panel-body hb-choices-row">
                <button type="button" class="hb-choice js-choice" data-value="girl">Bé gái</button>
                <button type="button" class="hb-choice js-choice" data-value="boy">Bé trai</button>
                <button type="button" class="hb-choice js-choice" data-value="unisex">Unisex</button>
            </div>
            <div class="hb-panel-footer">
                <button type="button" class="hb-link js-clear">Bỏ chọn</button>
                <button type="button" class="hb-btn js-apply">Xem kết quả</button>
            </div>
        </div>

        <!-- Độ tuổi -->
        <div class="hb-panel" id="panel-age" data-key="age">
            <div class="hb-panel-body hb-grid">
                <?php foreach ($ageOptions as $o): ?>
                    <button type="button" class="hb-choice js-choice" data-value="<?php echo h($o['key']); ?>">
                        <?php echo h($o['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="hb-panel-footer">
                <button type="button" class="hb-link js-clear">Bỏ chọn</button>
                <button type="button" class="hb-btn js-apply">Xem kết quả</button>
            </div>
        </div>

        <!-- Cân nặng -->
        <div class="hb-panel" id="panel-weight" data-key="weight">
            <div class="hb-panel-body hb-grid">
                <?php foreach ($weightOptions as $o): ?>
                    <button type="button" class="hb-choice js-choice" data-value="<?php echo h($o['key']); ?>">
                        <?php echo h($o['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="hb-panel-footer">
                <button type="button" class="hb-link js-clear">Bỏ chọn</button>
                <button type="button" class="hb-btn js-apply">Xem kết quả</button>
            </div>
        </div>

        <!-- Loại sản phẩm -->
        <div class="hb-panel" id="panel-category" data-key="category_id">
            <div class="hb-panel-body hb-grid">
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="hb-choice js-choice" data-value="<?php echo (int)$cat['id']; ?>">
                        <?php echo h($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="hb-panel-footer">
                <button type="button" class="hb-link js-clear">Bỏ chọn</button>
                <button type="button" class="hb-btn js-apply">Xem kết quả</button>
            </div>
        </div>

        <!-- Màu sắc -->
        <div class="hb-panel" id="panel-color" data-key="color">
            <div class="hb-panel-body hb-colors">
                <?php foreach ($colorOptions as $co): ?>
                    <button type="button" class="hb-color js-color" data-value="<?php echo h($co['name']); ?>" title="<?php echo h($co['name']); ?>">
                        <span class="hb-color-swatch" style="--swatch: <?php echo h($co['hex']); ?>"></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="hb-panel-footer">
                <button type="button" class="hb-link js-clear">Bỏ chọn</button>
                <button type="button" class="hb-btn js-apply">Xem kết quả</button>
            </div>
        </div>

        <!-- Giá -->
        <div class="hb-panel" id="panel-price" data-key="price">
            <div class="hb-panel-body hb-price">
                <div class="hb-price-row">
                    <div class="hb-field">
                        <label>Giá từ</label>
                        <input type="number" id="ui_min_price" min="0" value="<?php echo (int)$min_price; ?>" placeholder="0">
                    </div>
                    <div class="hb-field">
                        <label>Đến</label>
                        <input type="number" id="ui_max_price" min="0" value="<?php echo (int)$max_price; ?>" placeholder="1000000">
                    </div>
                </div>
            </div>
            <div class="hb-panel-footer">
                <button type="button" class="hb-link" id="btnClearPrice">Bỏ chọn</button>
                <button type="button" class="hb-btn" id="btnApplyPrice">Xem kết quả</button>
            </div>
        </div>

    </div>

    <!-- Products grid -->
    <div class="hb-grid-products">
        <?php if ($products): ?>
            <?php foreach ($products as $p): ?>
                <a class="hb-card" href="product_detail.php?id=<?php echo (int)$p['id']; ?>">
                    <div class="hb-thumb">
                        <img src="assets/img/<?php echo h($p['image']); ?>" alt="<?php echo h($p['name']); ?>">
                    </div>
                    <div class="hb-meta">
                        <div class="hb-cat"><?php echo h($p['category_name']); ?></div>
                        <div class="hb-name"><?php echo h($p['name']); ?></div>
                        <div class="hb-price"><?php echo number_format((int)$p['price']); ?> đ</div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="hb-empty">Không tìm thấy sản phẩm phù hợp. Bạn thử bộ lọc khác nhé.</div>
        <?php endif; ?>
    </div>

</div>

<script>
(function(){
  // ===== dropdown open/close =====
  const bar = document.getElementById('hbFilterBar');
  const panels = document.querySelectorAll('.hb-panel');
  const btns = bar ? bar.querySelectorAll('[data-panel]') : [];

  function closeAll(){
    panels.forEach(p => p.classList.remove('is-open'));
    btns.forEach(b => b.classList.remove('is-open'));
  }

  btns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = btn.getAttribute('data-panel');
      const panel = document.getElementById(id);
      if (!panel) return;

      const isOpen = panel.classList.contains('is-open');
      closeAll();
      if (!isOpen){
        panel.classList.add('is-open');
        btn.classList.add('is-open');
      }
      e.stopPropagation();
    });
  });

  document.addEventListener('click', closeAll);
  panels.forEach(p => p.addEventListener('click', e => e.stopPropagation()));

  // ===== selection store =====
  const form = document.getElementById('hbApplyForm');

  const state = {
    gender: (document.getElementById('hb_gender').value || '').split(',').filter(Boolean),
    age: (document.getElementById('hb_age').value || '').split(',').filter(Boolean),
    weight: (document.getElementById('hb_weight').value || '').split(',').filter(Boolean),
    category_id: (document.getElementById('hb_category_id').value || '').split(',').filter(Boolean),
    color: (document.getElementById('hb_color').value || '').split(',').filter(Boolean),
  };

  function uniq(arr){
    return Array.from(new Set(arr));
  }
  function toggle(arr, val){
    const i = arr.indexOf(val);
    if (i >= 0) arr.splice(i, 1);
    else arr.push(val);
    return arr;
  }

  function syncHidden(){
    document.getElementById('hb_gender').value = state.gender.join(',');
    document.getElementById('hb_age').value = state.age.join(',');
    document.getElementById('hb_weight').value = state.weight.join(',');
    document.getElementById('hb_category_id').value = state.category_id.join(',');
    document.getElementById('hb_color').value = state.color.join(',');
  }

  function refreshUI(){
    // mark active for each panel
    document.querySelectorAll('.hb-panel[data-key]').forEach(panel => {
      const key = panel.getAttribute('data-key');
      const selected = state[key] || [];

      // normal choices
      panel.querySelectorAll('.js-choice').forEach(btn => {
        const v = btn.getAttribute('data-value');
        btn.classList.toggle('is-active', selected.includes(v));
      });

      // colors
      panel.querySelectorAll('.js-color').forEach(btn => {
        const v = btn.getAttribute('data-value');
        btn.classList.toggle('is-active', selected.includes(v));
      });
    });
  }

  // init
  for (const k in state) state[k] = uniq(state[k]);
  syncHidden();
  refreshUI();

  // click choice (NO navigate)
  document.querySelectorAll('.hb-panel[data-key] .js-choice').forEach(btn => {
    btn.addEventListener('click', () => {
      const panel = btn.closest('.hb-panel');
      const key = panel.getAttribute('data-key');
      const val = btn.getAttribute('data-value');
      state[key] = uniq(toggle(state[key] || [], val));
      syncHidden();
      refreshUI();
    });
  });

  // click color
  document.querySelectorAll('.hb-panel[data-key="color"] .js-color').forEach(btn => {
    btn.addEventListener('click', () => {
      const val = btn.getAttribute('data-value');
      state.color = uniq(toggle(state.color || [], val));
      syncHidden();
      refreshUI();
    });
  });

  // clear panel selections
  document.querySelectorAll('.hb-panel .js-clear').forEach(btn => {
    btn.addEventListener('click', () => {
      const panel = btn.closest('.hb-panel');
      const key = panel.getAttribute('data-key');
      if (!key || !state[key]) return;
      state[key] = [];
      syncHidden();
      refreshUI();
    });
  });

  // apply (submit)
  document.querySelectorAll('.hb-panel .js-apply').forEach(btn => {
    btn.addEventListener('click', () => {
      syncHidden();
      form.submit();
    });
  });

  // price apply
  const uiMin = document.getElementById('ui_min_price');
  const uiMax = document.getElementById('ui_max_price');
  const hbMin = document.getElementById('hb_min_price');
  const hbMax = document.getElementById('hb_max_price');

  document.getElementById('btnApplyPrice').addEventListener('click', () => {
    hbMin.value = (uiMin.value || 0);
    hbMax.value = (uiMax.value || 0);
    form.submit();
  });

  document.getElementById('btnClearPrice').addEventListener('click', () => {
    uiMin.value = 0; uiMax.value = 0;
    hbMin.value = 0; hbMax.value = 0;
  });

})();
</script>

<?php include 'includes/footer.php'; ?>

<?php
$page_title = 'Trang Chủ';
require_once __DIR__ . '/includes/header.php';

$all_products = get_all_products();
$categories = get_all_categories();
$articles = get_all_articles();

// Lọc sản phẩm nổi bật và bán chạy bằng vòng lặp đơn giản
$featured_products = [];
$bestseller_products = [];

foreach ($all_products as $p) {
    if (!empty($p['is_featured'])) {
        $featured_products[] = $p;
    }
    if (!empty($p['is_bestseller'])) {
        $bestseller_products[] = $p;
    }
}

// Lấy tối đa 5 sản phẩm nổi bật, 5 bán chạy và 4 bài viết
$featured_products = array_slice($featured_products, 0, 5);
$bestseller_products = array_slice($bestseller_products, 0, 5);
$articles_preview = array_slice($articles, 0, 4);
?>

<div class="container">
    <!-- Hero Section -->
    <section class="hero-section">
        <aside class="category-sidebar">
            <h3><i class="fas fa-bars"></i> Danh Mục Sản Phẩm</h3>
            <ul class="category-menu">
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/products.php?category=<?php echo $cat['id']; ?>">
                            <span><i class="fas <?php echo $cat['icon']; ?>" style="margin-right:6px; width:18px; color:var(--primary-color);"></i> <?php echo sanitize($cat['name']); ?></span>
                            <i class="fas fa-chevron-right" style="font-size:0.7rem; color:var(--text-muted);"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <div class="hero-banner">
            <span class="badge badge-sale" style="width: fit-content; margin-bottom: 8px; font-size:0.75rem;">SIÊU ƯU ĐÃI</span>
            <h1>Siêu Phẩm Điện Thoại & Máy Tính Bảng AI</h1>
            <p>Trải nghiệm công nghệ đỉnh cao với các dòng iPhone 16 Series, Galaxy S24 Ultra và iPad M4 chính hãng.</p>
            <div>
                <a href="<?php echo BASE_URL; ?>/products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-cart"></i> Khám Phá Ngay
                </a>
                <!-- <a href="<?php echo BASE_URL; ?>/about.php" class="btn btn-secondary btn-lg" style="margin-left: 8px;">
                    Tìm hiểu thêm
                </a> -->
            </div>
        </div>
    </section>

    <!-- Sản Phẩm Nổi Bật -->
    <section style="margin-bottom: 30px;">
        <div class="section-header">
            <h2 class="section-title">Sản Phẩm Nổi Bật</h2>
            <a href="<?php echo BASE_URL; ?>/products.php" class="btn btn-outline btn-sm">Xem tất cả <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach ($featured_products as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Sản Phẩm Bán Chạy -->
    <section style="margin-bottom: 30px;">
        <div class="section-header">
            <h2 class="section-title">Sản Phẩm Bán Chạy</h2>
            <a href="<?php echo BASE_URL; ?>/products.php" class="btn btn-outline btn-sm">Xem tất cả <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach ($bestseller_products as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Tin Tức Mới -->
    <section style="margin-bottom: 30px;">
        <div class="section-header">
            <h2 class="section-title">Tin Tức & Tư Vấn Công Nghệ</h2>
            <a href="<?php echo BASE_URL; ?>/news.php" class="btn btn-outline btn-sm">Xem thêm <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="article-grid">
            <?php foreach ($articles_preview as $article): ?>
                <div class="article-card">
                    <div class="article-thumb">
                        <img src="<?php echo get_image_src($article['thumbnail'], $article['title']); ?>" alt="<?php echo sanitize($article['title']); ?>">
                    </div>
                    <div class="article-body">
                        <div class="article-date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($article['published_at'])); ?></div>
                        <h3 class="article-title"><?php echo sanitize($article['title']); ?></h3>
                        <p class="article-summary"><?php echo sanitize($article['summary']); ?></p>
                        <a href="<?php echo BASE_URL; ?>/news.php?id=<?php echo $article['id']; ?>" class="btn btn-outline btn-sm" style="margin-top:auto; align-self: flex-start;">
                            Đọc chi tiết
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

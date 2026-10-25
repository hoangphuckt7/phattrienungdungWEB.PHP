<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data/data_helper.php';

$article_id = $_GET['id'] ?? 0;
$single_article = null;

if (!empty($article_id)) {
    $single_article = get_article_by_id($article_id);
    // SỬA LỖI 1: Đổi 'published' thành 'Xuất bản' cho khớp với Admin
    if (!$single_article || $single_article['status'] !== 'Xuất bản') {
        set_flash('error', 'Bài viết không tồn tại.');
        redirect('news.php');
    }
}

$search = $_GET['search'] ?? '';
$articles = get_all_articles($search);

$page_title = $single_article ? $single_article['title'] : 'Tin Tức & Tư Vấn Công Nghệ';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if ($single_article): ?>

        <!-- Xem chi tiết bài viết -->
        <div style="max-width: 860px; margin: 0 auto 40px;">
            <div style="margin-bottom: 20px;">
                <a href="<?php echo BASE_URL; ?>/news.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Quay lại danh sách bài viết
                </a>
            </div>

            <article style="background: #fff; padding: 36px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--secondary-color); margin-bottom: 16px; line-height: 1.3;">
                    <?php echo sanitize($single_article['title']); ?>
                </h1>

                <div style="display: flex; gap: 20px; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                    <span><i class="fas fa-user-edit text-primary"></i> Tác giả: <strong><?php echo sanitize($single_article['author']); ?></strong></span>
                    <span><i class="far fa-calendar-alt text-primary"></i> <?php echo date('d/m/Y H:i', strtotime($single_article['created_at'])); ?></span>
                </div>

                <div style="background: var(--primary-light); padding: 18px; border-radius: var(--radius-md); border-left: 4px solid var(--primary-color); font-weight: 500; margin-bottom: 24px; font-size: 1.05rem; line-height: 1.6;">
                    <?php echo sanitize($single_article['summary']); ?>
                </div>

                <div style="margin-bottom: 30px; border-radius: var(--radius-md); overflow: hidden; height: 400px;">
                    <img src="<?php echo get_image_src($single_article['thumbnail'], $single_article['title'], 'baiviet', 800, 400); ?>" style="width:100%; height:100%; object-fit:cover;" alt="<?php echo sanitize($single_article['title']); ?>">
                </div>

                <div style="font-size: 1.05rem; line-height: 1.8; color: var(--text-primary); margin-bottom: 30px;">
                    <?php echo nl2br(sanitize($single_article['content'])); ?>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Chia sẻ bài viết này:</span>
                    <div style="display: flex; gap: 10px; font-size: 1.2rem;">
                        <a href="#" style="color:#1877f2;"><i class="fab fa-facebook"></i></a>
                        <a href="#" style="color:#1da1f2;"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="color:#25d366;"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </article>
        </div>

    <?php else: ?>

        <!-- Danh sách bài viết -->
        <div class="section-header" style="align-items: flex-end;">
            <div>
                <h1 class="section-title"><i class="fas fa-newspaper text-primary"></i> Tin Tức & Tư Vấn Công Nghệ</h1>
                <p style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 4px;">Cập nhật xu hướng smartphone, mẹo sử dụng và đánh giá sản phẩm mới nhất.</p>
            </div>
            
            <!-- SỬA LỖI 2: Đã mở khóa Form Tìm kiếm -->
            <form action="<?php echo BASE_URL; ?>/news.php" method="GET" style="display:flex; gap:8px;">
                <input type="text" name="search" class="form-control" placeholder="Tìm bài viết..." value="<?php echo sanitize($search); ?>" style="width:240px; padding:8px 14px;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <?php if (!empty($articles)): ?>
            <div class="article-grid">
                <?php foreach ($articles as $article): ?>
                    <div class="article-card">
                        <div class="article-thumb">
                            <img src="<?php echo get_image_src($article['thumbnail'], $article['title'], 'baiviet'); ?>" alt="<?php echo sanitize($article['title']); ?>">
                        </div>
                        <div class="article-body">
                            <div class="article-date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])); ?></div>
                            <h2 class="article-title"><?php echo sanitize($article['title']); ?></h2>
                            <p class="article-summary"><?php echo sanitize($article['summary']); ?></p>
                            
                            <!-- SỬA LỖI 3: Đã mở khóa Nút "Đọc bài viết" -->
                            <a href="<?php echo BASE_URL; ?>/chitietbaiviet.php?id=<?php echo $article['id']; ?>" class="btn btn-outline btn-sm" style="margin-top:auto; align-self: flex-start;">
                                Đọc bài viết <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: #fff; padding: 60px 20px; text-align: center; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 40px;">
                <i class="fas fa-newspaper" style="font-size: 3.5rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="font-size: 1.3rem; margin-bottom: 8px;">Không tìm thấy bài viết nào</h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">Vui lòng thử tìm kiếm lại với từ khóa khác.</p>
                <a href="<?php echo BASE_URL; ?>/news.php" class="btn btn-primary">Xem tất cả bài viết</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
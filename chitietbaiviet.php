<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data/data_helper.php';

$article_id = $_GET['id'] ?? 0;
$single_article = null;

if (!empty($article_id)) {
    $single_article = get_article_by_id($article_id);
}

// Kiểm tra nếu bài viết không tồn tại hoặc chưa xuất bản
if (!$single_article || $single_article['status'] !== 'Xuất bản') {
    echo "<script>alert('Bài viết không tồn tại hoặc đã bị ẩn!'); window.location.href='news.php';</script>";
    exit();
}

// THÊM DÒNG NÀY ĐỂ TĂNG LƯỢT XEM
increment_article_view($article_id);

// Cập nhật lại số liệu hiển thị ngay lập tức cho User thấy
$single_article['view_count'] += 1;

$page_title = $single_article['title'];

// Gọi hàm lấy tối đa 3 bài viết liên quan
$related_articles = get_related_articles($single_article['category_id'], $single_article['id'], 3);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="max-width: 860px; margin: 40px auto;">
        <!-- Nút quay lại -->
        <div style="margin-bottom: 20px;">
            <a href="<?php echo BASE_URL; ?>/news.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #475569;">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách bài viết
            </a>
        </div>

        <!-- Nội dung bài viết -->
        <article style="background: #fff; padding: 36px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h1 style="font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 16px; line-height: 1.3;">
                <?php echo htmlspecialchars($single_article['title']); ?>
            </h1>

            <!-- Thông tin tác giả & ngày tháng -->
            <div style="display: flex; gap: 20px; color: #64748b; font-size: 0.95rem; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">
                <span><i class="fas fa-user-edit" style="color: #3b82f6;"></i> Tác giả: <strong><?php echo htmlspecialchars($single_article['author']); ?></strong></span>
                <span><i class="far fa-calendar-alt" style="color: #3b82f6;"></i> <?php echo date('d/m/Y H:i', strtotime($single_article['published_at'] ?? $single_article['created_at'])); ?></span>
                <span><i class="fas fa-eye" style="color: #3b82f6;"></i> <?php echo number_format($single_article['view_count']); ?> lượt xem</span>
            </div>

            <!-- Tóm tắt bài viết -->
            <?php if (!empty($single_article['summary'])): ?>
            <div style="background: #f8fafc; padding: 18px; border-radius: 8px; border-left: 4px solid #3b82f6; font-weight: 500; margin-bottom: 24px; font-size: 1.1rem; line-height: 1.6; color: #334155;">
                <?php echo htmlspecialchars($single_article['summary']); ?>
            </div>
            <?php endif; ?>

            <!-- Ảnh đại diện bài viết -->
            <div style="margin-bottom: 30px; border-radius: 8px; overflow: hidden;">
                <img src="<?php echo BASE_URL . '/uploads/baiviet/' . htmlspecialchars($single_article['thumbnail']); ?>" 
                     style="width: 100%; max-height: 500px; object-fit: cover;" 
                     alt="<?php echo htmlspecialchars($single_article['title']); ?>">
            </div>

            <!-- Nội dung chi tiết (Không dùng htmlspecialchars để giữ nguyên định dạng HTML từ CKEditor) -->
            <div style="font-size: 1.1rem; line-height: 1.8; color: #1e293b; margin-bottom: 40px; overflow-wrap: break-word;">
                <?php echo $single_article['content']; ?>
            </div>

            <!-- Footer bài viết (Chia sẻ) -->
            <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.95rem; color: #64748b; font-weight: 500;">Chia sẻ bài viết này:</span>
                <div style="display: flex; gap: 15px; font-size: 1.4rem;">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . "/chitietbaiviet.php?id=" . $single_article['id']); ?>" target="_blank" style="color:#1877f2; text-decoration: none;"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . "/chitietbaiviet.php?id=" . $single_article['id']); ?>" target="_blank" style="color:#1da1f2; text-decoration: none;"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </article>
		</article>

        <!-- Khu vực Bài viết liên quan -->
        <?php if (!empty($related_articles)): ?>
        <div style="margin-top: 50px; border-top: 2px solid #e2e8f0; padding-top: 30px;">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 20px; border-left: 4px solid #3b82f6; padding-left: 10px;">
                Các bài viết liên quan
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <?php foreach ($related_articles as $rel): ?>
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s;">
                        <a href="<?php echo BASE_URL; ?>/chitietbaiviet.php?id=<?php echo $rel['id']; ?>" style="display: block; text-decoration: none; color: inherit;">
                            <img src="<?php echo BASE_URL . '/uploads/baiviet/' . htmlspecialchars($rel['thumbnail']); ?>" 
                                 style="width: 100%; height: 160px; object-fit: cover;" 
                                 alt="<?php echo htmlspecialchars($rel['title']); ?>">
                            <div style="padding: 15px;">
                                <h4 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 8px; line-height: 1.4; color: #1e293b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($rel['title']); ?>
                                </h4>
                                <span style="font-size: 0.85rem; color: #64748b;">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($rel['published_at'])); ?>
                                </span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
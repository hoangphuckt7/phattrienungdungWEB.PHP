<?php
require_once '../config.php';
include("xyly/xuly.php");

$pageTitle = "Quản lý bài viết";
$p = new xuly();

// Hàm cắt chuỗi giới hạn số từ
function limitWords($string, $limit = 5)
{
    $words = explode(" ", $string);
    if (count($words) > $limit) {
        return implode(" ", array_slice($words, 0, $limit)) . "...";
    }
    return $string;
}

// Xử lý xóa bài viết
if (isset($_REQUEST['nut']) && $_REQUEST['nut'] === 'Xóa bài viết') {
    $myid = isset($_REQUEST['txtid']) ? intval($_REQUEST['txtid']) : 0;
    if ($myid > 0) {
        if ($p->themxoasua("DELETE FROM articles WHERE id='$myid' LIMIT 1")) {
            header("Location:baiviet.php?msg=deleted");
            exit();
        }
    }
}

// Nhận dữ liệu tìm kiếm và lọc từ form GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? trim($_GET['category_id']) : '';
$status_filter   = isset($_GET['status']) ? trim($_GET['status']) : '';

// Xây dựng câu lệnh SQL truy vấn bài viết kết hợp bảng news_categories
$sql = "SELECT a.*, nc.name as category_name 
        FROM articles a 
        LEFT JOIN news_categories nc ON a.category_id = nc.id 
        WHERE 1=1";

if ($search !== '') {
    $search_esc = addslashes($search);
    $sql .= " AND a.title LIKE '%$search_esc%'";
}

if ($category_filter !== '') {
    $cat_esc = intval($category_filter);
    $sql .= " AND a.category_id = '$cat_esc'";
}

if ($status_filter !== '') {
    $status_esc = addslashes($status_filter);
    $sql .= " AND a.status = '$status_esc'";
}

$sql .= " ORDER BY a.id DESC";
$articles = $p->xuatdulieu($sql);

// Lấy danh sách danh mục bài viết cho thẻ select lọc
$list_categories = $p->xuatdulieu("SELECT * FROM news_categories");

include("includes/header.php");
include("includes/sidebar.php");
include("includes/navbar.php");
?>
<main class="main-content">
    <div class="content-card">

        <div class="page-header">
            <div class="page-title">
                <h1>Quản Lý Bài Viết</h1>
                <p>Thêm mới, chỉnh sửa và quản lý nội dung bài viết, tin tức</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/thembaiviet.php" class="btn-primary" title="Thêm bài viết mới">
                <i class="fa-solid fa-plus"></i> <span>Thêm bài viết mới</span>
            </a>
        </div>

        <form method="GET" action="" class="filter-section" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 250px;">
                <input type="text" name="search" placeholder="Tìm theo tiêu đề bài viết, mã ID..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 10px 14px;" class="product-input">
            </div>

            <select name="category_id" class="filter-select product-input" style="width: 200px; padding: 10px;">
                <option value="">-- Danh mục bài viết --</option>
                <?php if (!empty($list_categories)): ?>
                    <?php foreach ($list_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <select name="status" class="filter-select product-input" style="width: 180px; padding: 10px;">
                <option value="">-- Trạng thái --</option>
                <option value="Xuất bản" <?php echo ($status_filter === 'Xuất bản') ? 'selected' : ''; ?>>Đã xuất bản</option>
                <option value="Bản nháp" <?php echo ($status_filter === 'Bản nháp') ? 'selected' : ''; ?>>Bản nháp</option>
                <option value="Chờ duyệt" <?php echo ($status_filter === 'Chờ duyệt') ? 'selected' : ''; ?>>Chờ duyệt</option>
            </select>

            <button type="submit" class="btn-filter" style="padding: 10px 20px; cursor: pointer;">Lọc</button>

            <?php if ($search !== '' || $category_filter !== '' || $status_filter !== ''): ?>
                <a href="baiviet.php" class="btn-filter" style="text-decoration:none;">Làm mới</a>
            <?php endif; ?>
        </form>

        <!-- Bảng danh sách bài viết -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Ảnh đại diện</th>
                        <th>Tiêu đề bài viết</th>
                        <th>Tác giả</th>
                        <th>Lượt xem</th>
                        <th>Danh mục</th>
                        <th>Trạng thái</th>
                        <th>Chức năng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($articles)) {
                        $stt = 1;
                        foreach ($articles as $art) {
                            $thumbSrc = '../uploads/baiviet/' . $art['thumbnail'];
                            // Gán màu nhãn trạng thái tương ứng
                            $stt_class = 'status-yellow';
                            if ($art['status'] === 'Xuất bản') $stt_class = 'status-green';
                            elseif ($art['status'] === 'Chờ duyệt') $stt_class = 'status-red';

                            echo '<tr>
                                <td><strong>' . $stt++ . '</strong></td>
                                <td><img src="' . htmlspecialchars($thumbSrc) . '" alt="Thumbnail" class="product-img" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;"></td>
                                <td><span style="font-weight: 600; color: #0f172a;" title="' . htmlspecialchars($art['title']) . '">' . limitWords(htmlspecialchars($art['title']), 5) . '</span></td>
                                <td>' . htmlspecialchars($art['author']) . '</td>
                                <td>' . number_format($art['view_count']) . '</td>
                                <td><span class="badge-category">' . htmlspecialchars($art['category_name']) . '</span></td>
                                <td><span class="badge-status ' . $stt_class . '">' . htmlspecialchars($art['status']) . '</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="' . BASE_URL . '/capnhatbaiviet.php?id=' . $art['id'] . '" class="btn-action btn-edit" title="Chỉnh sửa">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </a>
                                        <button type="button" class="btn-action btn-delete nutxoa" title="Xóa" data-id="' . $art['id'] . '" data-name="' . htmlspecialchars($art['title']) . '">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #64748b;">Chưa có bài viết nào trong hệ thống.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>


<!-- Script thông báo thành công khi xóa -->
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Xóa bài viết thành công!',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        });
    </script>
<?php endif; ?>

<!-- Script bắt sự kiện click nút xóa bằng -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.nutxoa').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.dataset.id;
                const name = this.dataset.name || 'bài viết này';

                Swal.fire({
                    title: 'Xác nhận xóa?',
                    text: `Bạn có chắc chắn muốn xóa "${name}" không?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Có, xóa ngay!',
                    cancelButtonText: 'Hủy bỏ'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';

                        const inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'txtid';
                        inputId.value = id;
                        form.appendChild(inputId);

                        const inputNut = document.createElement('input');
                        inputNut.type = 'hidden';
                        inputNut.name = 'nut';
                        inputNut.value = 'Xóa bài viết';
                        form.appendChild(inputNut);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    });
</script>

<?php
include("includes/footer.php");
?>
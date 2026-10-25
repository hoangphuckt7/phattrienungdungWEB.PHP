<!-- Lớp nền mờ khi mở menu trên điện thoại -->
<div class="overlay" id="overlay"></div>
<!-- Thanh menu bên (Sidebar) -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-header">
            <!-- Nút thu gọn / mở rộng menu -->
            <button class="sidebar-toggle-btn" id="desktopToggleBtn" title="Thu gọn / Mở rộng">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="logo-box">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <div class="brand-name">ITMobile</div>
            <div class="brand-sub">Hệ thống quản lý</div>
        </div>

        <!-- Danh sách các chức năng trong menu -->
        <div class="sidebar-menu">
            <div class="menu-category">HỆ THỐNG</div>
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i><span>Bảng điều khiển</span></a>
            <a href="<?php echo BASE_URL; ?>/admin/danhsachsanpham.php" class="menu-item"><i class="fa-solid fa-mobile-screen-button"></i><span>Quản lý sản phẩm</span></a>
            <a href="<?php echo BASE_URL; ?>/admin/danhsachdanhmuc.php" class="menu-item"><i class="fa-solid fa-list"></i><span>Quản lý danh mục</span></a>
            <a href="<?php echo BASE_URL; ?>/admin/thanhtoan.php" class="menu-item"><i class="fa-solid fa-credit-card"></i><span>Quản lý thanh toán</span></a>
            <a href="<?php echo BASE_URL; ?>/admin/donhang.php" class="menu-item"><i class="fa-solid fa-cart-shopping"></i><span>Quản lý đơn hàng</span></a>
            <a href="<?php echo BASE_URL; ?>/admin/qlnguoidung.php" class="menu-item"><i class="fa-solid fa-users"></i><span>Quản lý tài khoản</span></a>
            <a href="<?php echo BASE_URL; ?>/admin/baiviet.php" class="menu-item"><i class="fa-solid fa-newspaper"></i><span>Bài viết</span></a>
            <!-- <a href="#" class="menu-item"><i class="fa-solid fa-gears"></i><span>Cài đặt hệ thống</span></a> -->
        </div>
    </div>

    <!-- Đăng xuất  -->
    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="menu-item logout-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>
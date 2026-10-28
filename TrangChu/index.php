<!doctype html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Trang chủ - CMTC Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="TrangChu.css?v=1.2" rel="stylesheet" />
</head>

<body>
    <!-- THANH MENU ĐIỀU HƯỚNG ĐỒNG BỘ TẤT CẢ CÁC TRANG -->
    <!-- ========================================================================= -->
    <!-- CSS THANH MENU & FOOTER CHUYÊN NGHIỆP ĐA THIẾT BỊ (PC 16:9 & MOBILE 9:16) -->
    <!-- ========================================================================= -->
    <style>
    :root {
        --cmtc-gold: #e6b96f;
        --cmtc-gold-dark: #c09e6c;
        --cmtc-dark: #0f1216;
        --cmtc-dark-2: #181c22;
    }

    /* --- 1. NAVBAR MÁY TÍNH & ĐIỆN THOẠI (GLASSMORPHISM) --- */
    .cmtc-navbar {
        background: rgba(15, 18, 22, 0.94) !important;
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(230, 185, 111, 0.25);
        padding: 10px 0;
        transition: all 0.3s ease;
        z-index: 1040;
    }

    .cmtc-brand-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }

    .cmtc-brand-logo-circle {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        border: 2px solid var(--cmtc-gold);
        overflow: hidden;
        background: #111;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(230, 185, 111, 0.25);
    }

    .cmtc-brand-logo-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cmtc-brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.15;
    }

    .cmtc-brand-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--cmtc-gold);
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .cmtc-brand-sub {
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.65);
        letter-spacing: 0.5px;
    }

    /* Link Menu trên Desktop */
    .cmtc-nav-link {
        color: rgba(255, 255, 255, 0.85) !important;
        font-weight: 600;
        font-size: 0.94rem;
        padding: 8px 15px !important;
        border-radius: 8px;
        position: relative;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .cmtc-nav-link i {
        font-size: 1rem;
        color: var(--cmtc-gold);
        transition: transform 0.25s ease;
    }

    .cmtc-nav-link:hover {
        color: var(--cmtc-gold) !important;
        background: rgba(230, 185, 111, 0.08);
    }

    .cmtc-nav-link:hover i {
        transform: translateY(-2px);
    }

    .cmtc-nav-link.active {
        color: var(--cmtc-gold) !important;
        background: rgba(230, 185, 111, 0.14);
        font-weight: 700;
    }

    .cmtc-nav-link.active::after {
        content: "";
        position: absolute;
        bottom: 3px;
        left: 15px;
        right: 15px;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--cmtc-gold), transparent);
        border-radius: 2px;
    }

    /* Nút Đặt Lịch Nổi Bật Trên Menu */
    .btn-nav-booking {
        background: linear-gradient(135deg, #e6b96f 0%, #c09e6c 100%);
        color: #111 !important;
        font-weight: 700;
        font-size: 0.88rem;
        padding: 8px 18px;
        border-radius: 50px;
        border: none;
        box-shadow: 0 4px 14px rgba(230, 185, 111, 0.35);
        transition: all 0.25s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-nav-booking:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(230, 185, 111, 0.55);
        background: linear-gradient(135deg, #f3ca82 0%, #CFA86E 100%);
        color: #000 !important;
    }

    /* Nút Tài Khoản Khách Hàng */
    .btn-nav-user {
        background: rgba(255, 255, 255, 0.06);
        color: var(--cmtc-gold) !important;
        border: 1px solid rgba(230, 185, 111, 0.45);
        font-weight: 600;
        font-size: 0.86rem;
        padding: 7px 14px;
        border-radius: 50px;
        transition: all 0.25s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-nav-user:hover {
        background: var(--cmtc-gold);
        color: #111 !important;
    }

    /* Nút Hamburger trên Điện Thoại */
    .cmtc-toggler {
        border: 1.5px solid rgba(230, 185, 111, 0.6) !important;
        border-radius: 10px;
        padding: 6px 10px;
        color: var(--cmtc-gold) !important;
        background: rgba(230, 185, 111, 0.08);
    }

    .cmtc-toggler:focus {
        box-shadow: 0 0 0 3px rgba(230, 185, 111, 0.25) !important;
    }

    /* --- 2. MENU TRƯỢT OFFCANVAS TRÊN ĐIỆN THOẠI --- */
    .cmtc-offcanvas {
        background: linear-gradient(180deg, #0f1216 0%, #181c22 100%) !important;
        color: #fff;
        border-left: 1px solid rgba(230, 185, 111, 0.25) !important;
        width: 300px !important;
    }

    .cmtc-mobile-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        color: rgba(255, 255, 255, 0.88);
        text-decoration: none;
        font-weight: 600;
        border-radius: 12px;
        margin-bottom: 6px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.2s ease;
    }

    .cmtc-mobile-link:hover,
    .cmtc-mobile-link.active {
        background: rgba(230, 185, 111, 0.15);
        border-color: var(--cmtc-gold);
        color: var(--cmtc-gold);
    }

    /* --- 3. THANH NAVIGATION ĐÁY MÀN HÌNH DÀNH RIÊNG CHO ĐIỆN THOẠI (< 992px) --- */
    .cmtc-mobile-bottom-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 64px;
        background: rgba(15, 18, 22, 0.96);
        backdrop-filter: blur(12px);
        border-top: 1px solid rgba(230, 185, 111, 0.3);
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 1035;
        padding: 0 6px;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.25);
    }

    .cmtc-bottom-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.68);
        text-decoration: none;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 0;
        transition: all 0.2s ease;
    }

    .cmtc-bottom-item i {
        font-size: 1.2rem;
        margin-bottom: 2px;
    }

    .cmtc-bottom-item.active,
    .cmtc-bottom-item:hover {
        color: var(--cmtc-gold);
    }

    .cmtc-bottom-booking-center {
        background: linear-gradient(135deg, #e6b96f 0%, #c09e6c 100%);
        color: #111 !important;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: -22px;
        border: 3px solid #0f1216;
        box-shadow: 0 4px 12px rgba(230, 185, 111, 0.45);
    }

    @media (max-width: 991.98px) {
        body {
            padding-bottom: 66px;
            /* Tránh thanh bottom bar che nội dung dưới cùng trên điện thoại */
        }
    }
    </style>

    <!-- THANH NAVBAR CHÍNH -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top cmtc-navbar">
        <div class="container">
            <!-- Logo & Tên Thương Hiệu -->
            <a class="cmtc-brand-wrap" href="../TrangChu/index.php">
                <div class="cmtc-brand-logo-circle">
                    <img src="../images/logo.png"
                        onerror="this.src='../images/Gemini_Generated_Image_1wira21wira21wir.png'" alt="CMTC Salon">
                </div>
                <div class="cmtc-brand-text">
                    <span class="cmtc-brand-title">CMTC SALON</span>
                    <span class="cmtc-brand-sub">Gentleman Hair Studio</span>
                </div>
            </a>

            <!-- Nút Mở Menu Trên Điện Thoại (Mở Offcanvas mượt mà) -->
            <div class="d-flex align-items-center gap-2 d-lg-none">
                <?php if (isset($_SESSION['user_id'])): ?>
                <a class="btn-nav-user py-1 px-2" href="#" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan">
                    <i class="bi bi-person-circle"></i>
                    <span class="text-truncate"
                        style="max-width: 85px;"><?= htmlspecialchars($_SESSION['ho_ten']) ?></span>
                </a>
                <?php endif; ?>
                <button class="navbar-toggler cmtc-toggler" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#menuDienThoaiOffcanvas" aria-label="Mở menu">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>

            <!-- Menu Trên Máy Tính (Desktop >= 992px) -->
            <div class="collapse navbar-collapse d-none d-lg-flex" id="menuChinh">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link active" href="../TrangChu/index.php">
                            <i class="bi bi-house-door"></i>Trang Chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link " href="../DichVu/DichVu.php">
                            <i class="bi bi-scissors"></i>Dịch Vụ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link " href="../BangGia/BangGia.php">
                            <i class="bi bi-tags"></i>Bảng Giá
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link " href="../ThongTin/ThongTin.php">
                            <i class="bi bi-info-circle"></i>Thông Tin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link " href="../DatLich/DatLich.php">
                            <i class="bi bi-calendar2-check"></i>Đặt Lịch
                        </a>
                    </li>
                </ul>

                <!-- Cụm Nút Phải Trên Desktop -->
                <div class="d-flex align-items-center gap-2">
                    <a href="../DatLich/DatLich.php" class="btn-nav-booking">
                        <i class="bi bi-calendar-plus-fill"></i>Đặt Lịch Ngay
                    </a>

                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="btn-nav-user" href="#" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan">
                        <i class="bi bi-person-circle"></i><?= htmlspecialchars($_SESSION['ho_ten']) ?> (Tài khoản)
                    </a>
                    <?php if (isset($_SESSION['vai_tro']) && $_SESSION['vai_tro'] == 'admin'): ?>
                    <a class="btn btn-info btn-sm fw-bold rounded-pill px-3" href="../Login/admin.php">
                        <i class="bi bi-speedometer2 me-1"></i>Admin
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- MENU TRƯỢT OFFCANVAS DÀNH CHO ĐIỆN THOẠI & TABLET -->
    <div class="offcanvas offcanvas-end cmtc-offcanvas" tabindex="-1" id="menuDienThoaiOffcanvas">
        <div class="offcanvas-header border-bottom border-secondary border-opacity-25 py-3">
            <div class="cmtc-brand-wrap">
                <div class="cmtc-brand-logo-circle" style="width: 40px; height: 40px;">
                    <img src="../images/logo.png"
                        onerror="this.src='../images/Gemini_Generated_Image_1wira21wira21wir.png'" alt="Logo">
                </div>
                <div class="cmtc-brand-text">
                    <span class="cmtc-brand-title" style="font-size: 1rem;">CMTC SALON</span>
                    <span class="cmtc-brand-sub">Menu Điều Hướng</span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column justify-content-between p-3">
            <div>
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="p-3 rounded-3 mb-3 border border-warning border-opacity-50"
                    style="background: rgba(230, 185, 111, 0.1);">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-bold text-warning"><i
                                class="bi bi-person-check-fill me-1"></i><?= htmlspecialchars($_SESSION['ho_ten']) ?></span>
                        <button type="button" class="btn btn-warning btn-sm py-0 px-2 fw-bold"
                            data-bs-dismiss="offcanvas" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan">Sửa
                            tên</button>
                    </div>
                    <div class="small text-light opacity-75"><?= htmlspecialchars($_SESSION['so_dien_thoai'] ?? '') ?> •
                        <?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <a href="../TrangChu/index.php" class="cmtc-mobile-link active">
                        <span><i class="bi bi-house-door-fill me-2 text-warning"></i>Trang Chủ</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../DichVu/DichVu.php" class="cmtc-mobile-link ">
                        <span><i class="bi bi-scissors me-2 text-warning"></i>Dịch Vụ Chăm Sóc Tóc</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../BangGia/BangGia.php" class="cmtc-mobile-link ">
                        <span><i class="bi bi-tags-fill me-2 text-warning"></i>Bảng Giá Niêm Yết</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../ThongTin/ThongTin.php" class="cmtc-mobile-link ">
                        <span><i class="bi bi-info-circle-fill me-2 text-warning"></i>Thông Tin & Chi Nhánh</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../DatLich/DatLich.php" class="cmtc-mobile-link ">
                        <span><i class="bi bi-calendar2-check-fill me-2 text-warning"></i>Đặt Lịch Cắt Tóc</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <?php if (isset($_SESSION['vai_tro']) && $_SESSION['vai_tro'] == 'admin'): ?>
                    <a href="../Login/admin.php" class="cmtc-mobile-link text-info border-info">
                        <span><i class="bi bi-speedometer2 me-2"></i>Bảng Điều Khiển Admin</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="border-top border-secondary border-opacity-25 pt-3">
                <a href="../DatLich/DatLich.php" class="btn-nav-booking w-100 justify-content-center py-2 mb-2">
                    <i class="bi bi-calendar2-heart-fill"></i>ĐẶT LỊCH HẸN NGAY
                </a>
                <a href="tel:0971714681" class="btn btn-outline-light btn-sm w-100 rounded-pill py-2">
                    <i class="bi bi-telephone-outbound-fill text-success me-1"></i>Hotline: 0971.714.681
                </a>
            </div>
        </div>
    </div>

    <!-- THANH ĐIỀU HƯỚNG CỐ ĐỊNH DƯỚI ĐÁY MÀN HÌNH ĐIỆN THOẠI (MOBILE BOTTOM BAR) -->
    <div class="cmtc-mobile-bottom-bar d-lg-none">
        <a href="../TrangChu/index.php" class="cmtc-bottom-item active">
            <i class="bi bi-house-door-fill"></i>
            <span>Trang Chủ</span>
        </a>
        <a href="../DichVu/DichVu.php" class="cmtc-bottom-item ">
            <i class="bi bi-scissors"></i>
            <span>Dịch Vụ</span>
        </a>
        <a href="../DatLich/DatLich.php" class="cmtc-bottom-item ">
            <div class="cmtc-bottom-booking-center">
                <i class="bi bi-calendar-plus-fill fs-5 m-0 text-dark"></i>
            </div>
            <span class="text-warning fw-bold mt-1">Đặt Lịch</span>
        </a>
        <a href="../BangGia/BangGia.php" class="cmtc-bottom-item ">
            <i class="bi bi-tags-fill"></i>
            <span>Bảng Giá</span>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan" class="cmtc-bottom-item">
            <i class="bi bi-person-Check-fill bi-person-circle text-warning"></i>
            <span class="text-warning text-truncate"
                style="max-width: 60px;"><?= htmlspecialchars($_SESSION['ho_ten']) ?></span>
        </a>
        <?php else: ?>
        <a href="../ThongTin/ThongTin.php" class="cmtc-bottom-item ">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Thông Tin</span>
        </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- MODAL THÔNG TIN TÀI KHOẢN CÁ NHÂN (CHỈ CHO SỬA TÊN, KHÓA EMAIL & SĐT IN ĐẬM) -->
    <div class="modal fade" id="modalThongTinCaNhan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-warning border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-badge me-2"></i>Thông Tin Tài Khoản Cá Nhân
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark"><i class="bi bi-person me-1"></i>HỌ VÀ TÊN
                                (Được phép chỉnh sửa)</label>
                            <input type="text" name="ho_ten_moi" class="form-control fw-semibold border-warning"
                                value="<?= htmlspecialchars($_SESSION['ho_ten'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted"><i
                                    class="bi bi-envelope-lock me-1"></i>ĐỊA CHỈ EMAIL (Cố định - Không thể thay
                                đổi)</label>
                            <input type="email" class="form-control bg-secondary bg-opacity-10 text-dark fw-bold"
                                value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" readonly disabled
                                style="cursor: not-allowed;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted"><i
                                    class="bi bi-telephone-fill me-1"></i>SỐ ĐIỆN THOẠI (Cố định - Không thể thay
                                đổi)</label>
                            <input type="text" class="form-control bg-secondary bg-opacity-10 text-dark fw-bold"
                                value="<?= htmlspecialchars($_SESSION['so_dien_thoai'] ?? '') ?>" readonly disabled
                                style="cursor: not-allowed;">
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3">
                            <a href="../DatLich/DatLich.php#bangLichSuGanDaySQL"
                                class="btn btn-outline-dark btn-sm fw-semibold">
                                <i class="bi bi-clock-history me-1"></i>Xem Lịch Sử Đặt Lịch
                            </a>
                            <a href="../Login/login.php?logout=1" class="btn btn-outline-danger btn-sm fw-semibold">
                                <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" name="btn_cap_nhat_ten_khach" class="btn btn-warning btn-sm fw-bold px-3">
                            <i class="bi bi-check2-circle me-1"></i>Lưu Thay Đổi Tên
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <header class="banner-trang-chu position-relative d-flex align-items-end justify-content-center text-center pb-5">
        <img src="../images/Gemini_Generated_Image_vn6539vn6539vn65.png" alt="Hair Salon Banner"
            class="anh-nen-banner" />
        <div class="container noi-dung-banner position-relative z-1 text-white" data-aos="zoom-in"
            data-aos-duration="1200">
            <h1 class="display-3 fw-bold mb-4 text-uppercase">Định Hình Phong Cách</h1>
            <a href="../DatLich/DatLich.php" class="btn px-5 py-3 fs-5 nut-dat-lich">ĐẶT LỊCH NGAY</a>
        </div>
    </header>

    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold text-uppercase text-gradient" data-aos="fade-up">Dịch Vụ Nổi Bật</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 shadow-sm the-dich-vu">
                        <img src="../images/cắt tóc.jpg" class="card-img-top" alt="Cắt Tóc Nam" />
                        <div class="card-body text-center mt-3">
                            <h3 class="card-title fw-bold">Cắt Tóc Nam</h3>
                            <p class="card-text text-muted mt-3">Tư vấn kiểu tóc phù hợp khuôn mặt, cắt tạo kiểu chuyên
                                nghiệp từ những thợ lành nghề nhất.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 shadow-sm border-primary the-dich-vu">
                        <img src="../images/cat-toc-goi-dau-3.jpg" class="card-img-top" alt="Combo Cắt + Gội" />
                        <div class="badge bg-primary position-absolute top-0 end-0 m-3 fs-6">HOT</div>
                        <div class="card-body text-center mt-3">
                            <h3 class="card-title text-primary fw-bold">Combo Cắt + Gội</h3>
                            <p class="card-text text-muted mt-3">Trải nghiệm thư giãn tuyệt đối với dịch vụ gội đầu
                                massage sau khi cắt tạo kiểu.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="card h-100 shadow-sm the-dich-vu">
                        <img src="../images/uon-nhuom.avif" class="card-img-top" alt="Uốn / Nhuộm" />
                        <div class="card-body text-center mt-3">
                            <h3 class="card-title fw-bold">Uốn / Nhuộm</h3>
                            <p class="card-text text-muted mt-3">Cập nhật những xu hướng màu tóc và các kiểu uốn thời
                                trang hot nhất hiện nay.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5" data-aos="fade-up">
                <a href="../DichVu/DichVu.php" class="btn btn-outline-dark px-5 py-3 fs-5 rounded-pill shadow-sm">Xem
                    Tất Cả Dịch Vụ</a>
            </div>
        </div>
    </section>

    <!-- KHU VỰC SỰ KIỆN & BÀI ĐĂNG (MỚI) -->
    <section class="py-5 bg-white">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold text-uppercase text-gradient" data-aos="fade-up">Sự Kiện & Hoạt Động
            </h2>

            <div class="row g-4">
                <?php
                if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $conn = new mysqli("localhost", "root", "", "cmtc_salon");
                } else {
                    $conn = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
                }
                $conn->set_charset("utf8mb4");
                
                // Bỏ LIMIT để lấy TOÀN BỘ dữ liệu từ database
                $sql_posts = "SELECT * FROM bai_dang ORDER BY ngay_dang DESC";
                $result_posts = $conn->query($sql_posts);
                
                $delay_post = 100;
                $dem = 0; // Biến đếm số lượng bài
                $tong_bai = $result_posts->num_rows;

                if ($result_posts && $tong_bai > 0) {
                    while($row = $result_posts->fetch_assoc()) {
                        $ngay_dang = date('d/m/Y', strtotime($row['ngay_dang']));
                        
                        // Nếu là bài thứ 4, đóng row hiện tại và mở khung collapse ẩn
                        if ($dem == 3) {
                            echo '</div><div class="collapse" id="khungSuKienAn"><div class="row g-4 mt-1">';
                        }

                        echo '
                        <div class="col-md-4" data-aos="zoom-in-up" data-aos-delay="'.$delay_post.'">
                            <div class="card h-100 shadow-sm the-su-kien border-0">
                                <div class="position-relative overflow-hidden anh-su-kien-wrap rounded-top-4">
                                    <img src="'.$row['hinh_anh'].'" class="card-img-top img-su-kien" alt="'.$row['tieu_de'].'">
                                    <div class="ngay-dang-badge">'.$ngay_dang.'</div>
                                </div>
                                <div class="card-body p-4 bg-light rounded-bottom-4 d-flex flex-column">
                                    <h5 class="card-title fw-bold text-dark mb-3">'.$row['tieu_de'].'</h5>
                                    <p class="card-text text-muted mb-4 flex-grow-1">'.$row['tom_tat'].'</p>
                                    <a href="../TrangChu/ChiTietBaiDang.php?id='.$row['id'].'" class="text-primary fw-bold text-decoration-none xem-them-link">Đọc tiếp <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>';
                        
                        $delay_post += 150;
                        if ($delay_post > 400) $delay_post = 100; // Reset hiệu ứng cho hàng dưới
                        $dem++;
                    }
                    
                    // Nếu có nhiều hơn 3 bài, đóng thẻ collapse ẩn
                    if ($tong_bai > 3) {
                        echo '</div></div>';
                    } else {
                        echo '</div>'; // Đóng row bình thường nếu <= 3 bài
                    }
                } else {
                    echo '<p class="text-center text-muted w-100">Chưa có bài đăng nào. Vui lòng thêm bài trong phpMyAdmin!</p></div>';
                }
                $conn->close();
                ?>

                <!-- Nút cuộn Đóng/Mở (Chỉ hiện khi có trên 3 bài) -->
                <?php if (isset($tong_bai) && $tong_bai > 3): ?>
                <div class="text-center mt-5" data-aos="fade-up">
                    <button class="btn btn-outline-primary px-4 py-2 fs-5 rounded-pill shadow-sm nut-xem-them"
                        type="button" data-bs-toggle="collapse" data-bs-target="#khungSuKienAn" aria-expanded="false"
                        aria-controls="khungSuKienAn" onclick="doiChuNut(this)">
                        Xem Tất Cả Hoạt Động <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                </div>
                <script>
                // Hàm đổi chữ khi khách hàng bấm nút Đóng/Mở
                function doiChuNut(btn) {
                    setTimeout(() => {
                        if (btn.getAttribute('aria-expanded') === 'true') {
                            btn.innerHTML = 'Thu Gọn Hoạt Động <i class="bi bi-chevron-up ms-1"></i>';
                        } else {
                            btn.innerHTML = 'Xem Tất Cả Hoạt Động <i class="bi bi-chevron-down ms-1"></i>';
                        }
                    }, 50); // Độ trễ nhẹ để chờ Bootstrap cập nhật trạng thái
                }
                </script>
                <?php endif; ?>

            </div>
    </section>
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-2 fw-bold text-uppercase text-gradient" data-aos="zoom-in">Đội Ngũ Chuyên Nghiệp
            </h2>
            <h5 class="text-center mb-5 text-muted" data-aos="zoom-in">Là những người thợ có Tâm & được đào tạo bài bản.
            </h5>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="100">
                    <div class="card h-100 shadow-sm border-0 the-doi-ngu">
                        <div class="card-body text-center p-4">
                            <div class="img-wrap-doi-ngu mb-4"><img src="../images/anh-cong1.jpg" alt="Vừ Xuân Công"
                                    class="img-doi-ngu" /></div>
                            <h4 class="fw-bold text-dark">Vừ Xuân Công</h4>
                            <p class="text-primary fw-semibold mb-0">Hair Artist</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="200">
                    <div class="card h-100 shadow-sm border-0 the-doi-ngu">
                        <div class="card-body text-center p-4">
                            <div class="img-wrap-doi-ngu mb-4"><img src="../images/anh-minh.jpg" alt="Moong Văn Minh"
                                    class="img-doi-ngu" /></div>
                            <h4 class="fw-bold text-dark">Moong Văn Minh</h4>
                            <p class="text-primary fw-semibold mb-0">Master</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="300">
                    <div class="card h-100 shadow-sm border-0 the-doi-ngu">
                        <div class="card-body text-center p-4">
                            <div class="img-wrap-doi-ngu mb-4"><img src="../images/anh-ca-nha.jpg" alt="Lầu Bá Trừ"
                                    class="img-doi-ngu" /></div>
                            <h4 class="fw-bold text-dark">Lầu Bá Trừ</h4>
                            <p class="text-primary fw-semibold mb-0">Stylist</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="400">
                    <div class="card h-100 shadow-sm border-0 the-doi-ngu">
                        <div class="card-body text-center p-4">
                            <div class="img-wrap-doi-ngu mb-4"><img src="../images/anh-chinh1.jpg" alt="Hờ Bá Chính"
                                    class="img-doi-ngu" /></div>
                            <h4 class="fw-bold text-dark">Hờ Bá Chính</h4>
                            <p class="text-primary fw-semibold mb-0">Stylist</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- FOOTER CHÂN TRANG CHUYÊN NGHIỆP ĐA THIẾT BỊ (PC 4 CỘT & MOBILE TỐI ƯU)    -->
    <!-- ========================================================================= -->
    <style>
    .cmtc-footer {
        background: linear-gradient(180deg, #14181d 0%, #0b0d10 100%);
        color: rgba(255, 255, 255, 0.82);
        border-top: 2px solid rgba(230, 185, 111, 0.3);
        position: relative;
        font-size: 0.92rem;
    }

    .cmtc-footer-title {
        color: #e6b96f;
        font-weight: 800;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 18px;
        position: relative;
        padding-bottom: 10px;
    }

    .cmtc-footer-title::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 42px;
        height: 2.5px;
        background: #e6b96f;
        border-radius: 2px;
    }

    @media (max-width: 767.98px) {
        .cmtc-footer-title::after {
            left: 50%;
            transform: translateX(-50%);
        }
    }

    .cmtc-footer-link {
        color: rgba(255, 255, 255, 0.75);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        transition: all 0.25s ease;
    }

    .cmtc-footer-link:hover {
        color: #e6b96f;
        transform: translateX(5px);
    }

    .cmtc-social-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(230, 185, 111, 0.3);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .cmtc-social-btn:hover {
        background: #e6b96f;
        color: #111 !important;
        transform: translateY(-4px);
        box-shadow: 0 5px 15px rgba(230, 185, 111, 0.4);
    }

    .cmtc-branch-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 10px;
    }
    </style>

    <footer class="cmtc-footer pt-5 pb-4 mt-auto">
        <div class="container">
            <div class="row g-4 text-center text-md-start">
                <!-- CỘT 1: THƯƠNG HIỆU CMTC SALON -->
                <div class="col-lg-3 col-md-6">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-3">
                        <div class="cmtc-brand-logo-circle" style="width: 48px; height: 48px;">
                            <img src="../images/logo.png"
                                onerror="this.src='../images/Gemini_Generated_Image_1wira21wira21wir.png'"
                                alt="CMTC Salon">
                        </div>
                        <div class="text-start">
                            <h5 class="fw-bold text-warning m-0">CMTC SALON</h5>
                            <small class="text-white-50">Định Hình Phong Cách Phái Mạnh</small>
                        </div>
                    </div>
                    <p class="text-white-50 small lh-lg mb-3">
                        Hệ thống tạo mẫu tóc nam, uốn nhuộm thời trang và gội đầu dưỡng sinh thư giãn hàng đầu. Cam kết
                        phục vụ tận tâm bởi đội ngũ Master & Hair Artist chuyên nghiệp.
                    </p>
                    <a href="../DatLich/DatLich.php" class="btn-nav-booking py-2 px-4 small">
                        <i class="bi bi-calendar2-check-fill"></i>Đặt Lịch Cắt Ngay
                    </a>
                </div>

                <!-- CỘT 2: HỆ THỐNG CHI NHÁNH & LIÊN HỆ -->
                <div class="col-lg-4 col-md-6">
                    <h6 class="cmtc-footer-title">Hệ Thống Chi Nhánh</h6>
                    <div class="cmtc-branch-box text-start">
                        <div class="fw-bold text-warning small"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Cơ sở
                            1 (TP. Vinh):</div>
                        <div class="text-light small">Số 51 Lý Tự Trọng, Phường Vinh Phú, TP. Vinh, Nghệ An</div>
                    </div>
                    <div class="cmtc-branch-box text-start">
                        <div class="fw-bold text-warning small"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Cơ sở
                            2 (Kỳ Sơn):</div>
                        <div class="text-light small">Mường Lống, Huyện Kỳ Sơn, Tỉnh Nghệ An</div>
                    </div>
                    <div class="mt-2 small">
                        <span class="d-block mb-1"><i class="bi bi-telephone-fill text-success me-2"></i>Hotline: <a
                                href="tel:0971714681"
                                class="text-warning fw-bold text-decoration-none">0971.714.681</a></span>
                        <span class="d-block"><i class="bi bi-envelope-fill text-info me-2"></i>Email: <a
                                href="mailto:xuancongsupport@gmail.com"
                                class="text-light text-decoration-none">xuancongsupport@gmail.com</a></span>
                    </div>
                </div>

                <!-- CỘT 3: LIÊN KẾT NHANH & DỊCH VỤ -->
                <div class="col-lg-2 col-md-6">
                    <h6 class="cmtc-footer-title">Menu Nhanh</h6>
                    <ul class="list-unstyled mb-0 d-flex flex-column align-items-center align-items-md-start">
                        <li><a href="../TrangChu/index.php" class="cmtc-footer-link"><i
                                    class="bi bi-chevron-right small text-warning"></i>Trang Chủ</a></li>
                        <li><a href="../DichVu/DichVu.php" class="cmtc-footer-link"><i
                                    class="bi bi-chevron-right small text-warning"></i>Dịch Vụ Tóc</a></li>
                        <li><a href="../BangGia/BangGia.php" class="cmtc-footer-link"><i
                                    class="bi bi-chevron-right small text-warning"></i>Bảng Giá Combo</a></li>
                        <li><a href="../ThongTin/ThongTin.php" class="cmtc-footer-link"><i
                                    class="bi bi-chevron-right small text-warning"></i>Về CMTC Salon</a></li>
                        <li><a href="../DatLich/DatLich.php" class="cmtc-footer-link"><i
                                    class="bi bi-chevron-right small text-warning"></i>Đặt Lịch Hẹn</a></li>
                    </ul>
                </div>

                <!-- CỘT 4: GIỜ MỞ CỬA & KẾT NỐI MẠNG XÃ HỘI -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="cmtc-footer-title">Giờ Làm Việc</h6>
                    <div class="cmtc-branch-box text-start small mb-3">
                        <div
                            class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span class="text-white-50"><i class="bi bi-clock me-1 text-warning"></i>Thứ 2:</span>
                            <span class="fw-bold text-white">07:00 – 18:00</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 pt-2">
                            <span class="text-white-50"><i class="bi bi-clock-fill me-1 text-success"></i>Thứ 3 – Chủ
                                Nhật:</span>
                            <span class="fw-bold text-warning">07:00 – 20:00</span>
                        </div>
                    </div>

                    <h6 class="text-uppercase fw-bold small text-white-50 mb-2">Kết Nối Với Chúng Tôi</h6>
                    <div class="d-flex justify-content-center justify-content-md-start gap-2">
                        <a href="https://www.facebook.com/share/1MpxU1LGQT/?mibextid=wwXIfr" target="_blank"
                            class="cmtc-social-btn" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.tiktok.com/@batru2005" target="_blank" class="cmtc-social-btn"
                            title="TikTok"><i class="bi bi-tiktok"></i></a>
                        <a href="#" target="_blank" class="cmtc-social-btn" title="YouTube"><i
                                class="bi bi-youtube"></i></a>
                        <a href="#" target="_blank" class="cmtc-social-btn" title="Instagram"><i
                                class="bi bi-instagram"></i></a>
                        <a href="https://maps.app.goo.gl/fHiCFvcEkEmtZiiT8" target="_blank" class="cmtc-social-btn"
                            title="Bản đồ Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                    </div>
                </div>
            </div>

            <!-- Dòng Bản Quyền Dưới Cùng -->
            <div class="row mt-4 pt-3 border-top border-secondary border-opacity-25 align-items-center">
                <div class="col-md-8 text-center text-md-start small text-white-50">
                    © 2026 <strong class="text-warning">CMTC Salon</strong>. Hệ thống Đặt Lịch & Quản Lý Salon Tóc
                    Chuyên Nghiệp.
                </div>
                <div class="col-md-4 text-center text-md-end mt-2 mt-md-0">
                    <a href="#" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;"
                        class="btn btn-outline-warning btn-sm rounded-pill px-3 py-1 small">
                        <i class="bi bi-arrow-up-short"></i>Lên đầu trang
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
    AOS.init({
        duration: 1000,
        once: true,
        offset: 100
    });
    </script>
    <script src="TrangChu.js"></script>
</body>

</html>
<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $conn = new mysqli("localhost", "root", "", "cmtc_salon");
} else {
    $conn = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
}

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Cấu hình Gmail gửi xác nhận ngầm qua Google Apps Script Webhook của bạn
$GMAIL_CUA_BAN = "xuancongsupport@gmail.com";
$GOOGLE_SCRIPT_WEBHOOK_URL = "https://script.google.com/macros/s/AKfycbyHJ2O3wird81Rq1gzYSJniL4QyDrubNjWe4lgrax8aFlEsprvhnODZWh-dzJNkBYtlPA/exec";
$GMAIL_APP_PASSWORD = "";

// Đảm bảo bảng bang_dat_lich có cột email, cột thoi_gian_dat và cột dich_vu lưu được chuỗi nhiều dịch vụ
$conn->query("ALTER TABLE `bang_dat_lich` ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL;");
$conn->query("ALTER TABLE `bang_dat_lich` ADD COLUMN IF NOT EXISTS `thoi_gian_dat` timestamp DEFAULT CURRENT_TIMESTAMP;");
$conn->query("ALTER TABLE `bang_dat_lich` MODIFY COLUMN `dich_vu` TEXT NOT NULL;");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['user_id'])) {
        echo "<script>
                alert('⚠️ Vui lòng đăng nhập tài khoản trước khi đặt lịch cắt tóc!');
                window.location.href = '../Login/login.php';
              </script>";
        exit();
    }

    $tinh_thanh    = $conn->real_escape_string($_POST['tinh_thanh'] ?? '');
    $quan_huyen    = $conn->real_escape_string($_POST['quan_huyen'] ?? '');
    $salon_id      = $conn->real_escape_string($_POST['salon_id'] ?? '');
    $ky_thuat_vien = $conn->real_escape_string($_POST['ky_thuat_vien'] ?? '');
    $ngay_gio      = $conn->real_escape_string($_POST['ngay_gio'] ?? '');
    $ho_ten        = $conn->real_escape_string(trim($_POST['ho_ten'] ?? ($_SESSION['ho_ten'] ?? '')));
    $so_dien_thoai = $conn->real_escape_string($_SESSION['so_dien_thoai'] ?? ($_POST['so_dien_thoai'] ?? ''));
    $email_khach   = $conn->real_escape_string($_SESSION['email'] ?? ($_POST['email'] ?? ''));

    // Xử lý nhận NHIỀU DỊCH VỤ (dich_vu[] hoặc chuỗi dich_vu)
    $ds_dich_vu_chon = [];
    if (isset($_POST['dich_vu'])) {
        if (is_array($_POST['dich_vu'])) {
            foreach ($_POST['dich_vu'] as $dv_item) {
                $dv_trim = trim($dv_item);
                if ($dv_trim !== '') {
                    $ds_dich_vu_chon[] = $dv_trim;
                }
            }
        } else {
            $dv_trim = trim($_POST['dich_vu']);
            if ($dv_trim !== '') {
                $ds_dich_vu_chon[] = $dv_trim;
            }
        }
    }

    if (empty($ds_dich_vu_chon)) {
        echo "<script>
                alert('⚠️ Vui lòng chọn ít nhất 1 dịch vụ trước khi đặt lịch!');
                window.history.back();
              </script>";
        exit();
    }

    $chuoi_dich_vu_hien_thi = implode(", ", $ds_dich_vu_chon);
    $dich_vu = $conn->real_escape_string($chuoi_dich_vu_hien_thi);

    // Tính tổng tiền của tất cả các dịch vụ đã chọn từ bảng dich_vu
    $tong_tien_dv = 0;
    foreach ($ds_dich_vu_chon as $ten_dv_item) {
        $ten_dv_esc = $conn->real_escape_string($ten_dv_item);
        $q_gia = $conn->query("SELECT gia_1 FROM dich_vu WHERE ten_dich_vu = '$ten_dv_esc' LIMIT 1");
        if ($q_gia && $r_gia = $q_gia->fetch_assoc()) {
            $tong_tien_dv += (float)$r_gia['gia_1'] * 1000;
        }
    }

    // Đồng bộ tên mới nếu khách có chỉnh sửa trên form
    if ($ho_ten !== '') {
        $uid_curr = (int)$_SESSION['user_id'];
        $conn->query("UPDATE tai_khoan SET ho_ten = '$ho_ten' WHERE id = $uid_curr");
        $_SESSION['ho_ten'] = trim($_POST['ho_ten']);
    }
    // =========================================================================
    // KIỂM TRA 1: VÔ HIỆU HÓA ĐẶT LỊCH VÀO NGÀY GIỜ TRƯỚC THỜI ĐIỂM HIỆN TẠI
    // =========================================================================
    $timestamp_chon = strtotime($ngay_gio);
    if ($timestamp_chon === false || $timestamp_chon < (time() - 60)) {
        echo "<script>
                alert('❌ Không thể đặt lịch vào ngày giờ đã qua! Quý khách vui lòng chọn ngày giờ từ thời điểm hiện tại trở đi.');
                window.history.back();
              </script>";
        exit();
    }

    // =========================================================================
    // KIỂM TRA 2: ĐẢM BẢO NHÂN VIÊN CẮT THUỘC ĐÚNG CƠ SỞ SALON ĐÃ CHỌN (2 THỢ / SALON)
    // =========================================================================
    $tho_co_so_vinh   = ['Vừ Xuân Công', 'Moong Văn Minh'];
    $tho_co_so_ky_son = ['Lầu Bá Trừ', 'Hờ Bá Chính'];

    if (strpos($salon_id, 'Cơ sở Vinh') !== false && !in_array($ky_thuat_vien, $tho_co_so_vinh)) {
        echo "<script>
                alert('⚠️ Cơ sở TP. Vinh chỉ bao gồm 2 Kỹ thuật viên: Vừ Xuân Công và Moong Văn Minh. Vui lòng chọn đúng thợ của cơ sở!');
                window.history.back();
              </script>";
        exit();
    }
    if (strpos($salon_id, 'Cơ sở Kỳ Sơn') !== false && !in_array($ky_thuat_vien, $tho_co_so_ky_son)) {
        echo "<script>
                alert('⚠️ Cơ sở Kỳ Sơn chỉ bao gồm 2 Kỹ thuật viên: Lầu Bá Trừ và Hờ Bá Chính. Vui lòng chọn đúng thợ của cơ sở!');
                window.history.back();
              </script>";
        exit();
    }
    // 1.1. Tự động cố định đặt lùi 30 phút với khách đặt trước nếu trùng giờ
    $thong_bao_lui_30p = "";
    $ngay_dat = date('Y-m-d', strtotime($ngay_gio));

    if ($ky_thuat_vien !== 'Chọn thợ bất kỳ') {
        $vong_lap = 0;
        while ($vong_lap < 20) {
            $sql_trung_tho = "SELECT id, ho_ten, ngay_gio, 
                                     DATE_FORMAT(ngay_gio, '%H:%i') AS gio_khach_truoc,
                                     DATE_FORMAT(DATE_ADD(ngay_gio, INTERVAL 30 MINUTE), '%Y-%m-%d %H:%i:00') AS ngay_gio_moi_30p,
                                     DATE_FORMAT(DATE_ADD(ngay_gio, INTERVAL 30 MINUTE), '%H:%i') AS gio_moi_dep
                              FROM bang_dat_lich 
                              WHERE ky_thuat_vien = '$ky_thuat_vien' 
                                AND DATE(ngay_gio) = '$ngay_dat'
                                AND ABS(TIMESTAMPDIFF(MINUTE, ngay_gio, '$ngay_gio')) < 30
                              ORDER BY ngay_gio DESC
                              LIMIT 1";
            $res_trung = $conn->query($sql_trung_tho);
            if ($res_trung && $res_trung->num_rows > 0) {
                $r_trung = $res_trung->fetch_assoc();
                $ngay_gio = $r_trung['ngay_gio_moi_30p'];
                $ngay_dat = date('Y-m-d', strtotime($ngay_gio));
                $thong_bao_lui_30p = "Giờ hẹn đã được tự động xếp lùi 30 phút (sang " . $r_trung['gio_moi_dep'] . ") để không trùng với khách đặt trước.";
                $vong_lap++;
            } else {
                break;
            }
        }
    }

    // 1.2. Kiểm tra chặn khung giờ bận của thợ
    $ngay_dat = date('Y-m-d', strtotime($ngay_gio));
    $gio_dat  = date('H:i:s', strtotime($ngay_gio));

    $sql_check_ban = "SELECT * FROM lich_ban_tho 
                      WHERE ky_thuat_vien = '$ky_thuat_vien' 
                        AND ngay_ban = '$ngay_dat' 
                        AND '$gio_dat' BETWEEN gio_bat_dau AND gio_ket_thuc";
    $res_ban = $conn->query($sql_check_ban);

    if ($res_ban && $res_ban->num_rows > 0) {
        $row_ban = $res_ban->fetch_assoc();
        $tu_gio = substr($row_ban['gio_bat_dau'], 0, 5);
        $den_gio = substr($row_ban['gio_ket_thuc'], 0, 5);
        $ly_do = $row_ban['ly_do'];
        echo "<script>
                alert('❌ Rất tiếc! Thợ $ky_thuat_vien đang có lịch bận từ $tu_gio đến $den_gio ngày $ngay_dat ($ly_do). Quý khách vui lòng chọn khung giờ khác!');
                window.history.back();
              </script>";
        exit();
    }

    // Thời điểm khách vừa bấm đặt lịch (Thời gian đặt)
    $thoi_gian_dat_sql = date('Y-m-d H:i:s');
    $thoi_gian_dat_dep = date('H:i:s - d/m/Y');

    // 2. Lưu đơn đặt lịch vào bảng bang_dat_lich
    $sql = "INSERT INTO bang_dat_lich (tinh_thanh, quan_huyen, salon_id, dich_vu, ky_thuat_vien, ngay_gio, ho_ten, so_dien_thoai, email, thoi_gian_dat) 
            VALUES ('$tinh_thanh', '$quan_huyen', '$salon_id', '$dich_vu', '$ky_thuat_vien', '$ngay_gio', '$ho_ten', '$so_dien_thoai', '$email_khach', '$thoi_gian_dat_sql')";

    if ($conn->query($sql) === TRUE) {
        $ma_don = $conn->insert_id;
        $thoi_gian_dep = date('H:i - d/m/Y', strtotime($ngay_gio));
        $tong_tien_dep = ($tong_tien_dv > 0) ? number_format($tong_tien_dv, 0, ',', '.') . ' VNĐ' : 'Theo bảng giá Salon';

        // Lời cảm ơn chân thành, dài và chuyên nghiệp ở cuối thư & phiếu hẹn
        $loi_cam_on_dai = "Đội ngũ <strong>CMTC Salon</strong> xin gửi lời cảm ơn chân thành và sâu sắc nhất đến Quý khách <strong>$ho_ten</strong> vì đã tin tưởng lựa chọn dịch vụ của chúng tôi. Mỗi mái tóc đẹp và sự hài lòng, thư giãn của Quý khách chính là niềm tự hào lớn nhất của tập thể Kỹ thuật viên tại Salon. Quý khách vui lòng đến đúng khung giờ đã hẹn để Kỹ thuật viên <strong>$ky_thuat_vien</strong> có thể chuẩn bị chu đáo và phục vụ Quý khách một cách trọn vẹn nhất. Trong trường hợp cần thay đổi giờ hẹn hoặc hỗ trợ thêm thông tin, Quý khách đừng ngần ngại liên hệ với CMTC Salon nhé. Rất hân hạnh được đón tiếp và phục vụ Quý khách!";

        // Chuẩn bị nội dung email gửi ngầm về Gmail khách hàng (Cơ sở Salon chỉ lấy đúng $salon_id khách đã chọn, có thêm dòng Thời gian đặt và lời cảm ơn dài)
        $tieu_de_email = "✅ [CMTC SALON] Xác nhận đặt lịch thành công #$ma_don ($thoi_gian_dep)";
        $noi_dung_html = "
        <div style='font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
            <div style='background-color: #111; color: #e6b96f; padding: 24px; text-align: center; border-bottom: 3px solid #c09e6c;'>
                <h2 style='margin: 0; text-transform: uppercase; letter-spacing: 1px;'>CMTC SALON - PHIẾU HẸN CẮT TÓC</h2>
                <p style='color: #fff; margin: 6px 0 0;'>Mã phiếu hẹn: <strong>#$ma_don</strong></p>
            </div>
            <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65;'>
                <p>Xin chào <strong>$ho_ten</strong>,</p>
                <p>Cảm ơn bạn đã tin tưởng đặt lịch tại <strong>CMTC Salon</strong>! Lịch hẹn của bạn đã được hệ thống ghi nhận thành công với thông tin chi tiết dưới đây:</p>
                <table style='width: 100%; border-collapse: collapse; margin: 18px 0;'>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa; width: 38%;'><strong>Khách hàng:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd;'>$ho_ten ($so_dien_thoai)</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Cơ sở Salon đã chọn:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; font-weight: bold; color: #0b4a4d;'>$salon_id</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Dịch vụ đăng ký:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; color: #198754; font-weight: bold;'>$chuoi_dich_vu_hien_thi</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Tổng chi phí dự kiến:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; color: #d63384; font-weight: bold;'>$tong_tien_dep</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Kỹ thuật viên phục vụ:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; font-weight: bold;'>$ky_thuat_vien</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Thời gian đặt đơn:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; color: #555;'>$thoi_gian_dat_dep</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Thời gian hẹn cắt:</strong></td>
                        <td style='padding: 10px 12px; border: 1px solid #ddd; color: #dc3545; font-weight: bold; font-size: 16px;'>$thoi_gian_dep</td>
                    </tr>
                </table>
                <div style='background-color: #fdfaf3; border-left: 4px solid #c09e6c; padding: 14px 16px; border-radius: 6px; margin-top: 18px; color: #444; font-size: 14px; text-align: justify;'>
                    $loi_cam_on_dai
                </div>
            </div>
            <div style='background-color: #111; color: #e6b96f; padding: 14px; text-align: center; font-size: 12px;'>
                CMTC Salon — Hệ thống đặt lịch chăm sóc tóc nam chuyên nghiệp & tận tâm.
            </div>
        </div>";

        $du_lieu_email_json = json_encode([
            'to_email'      => $email_khach,
            'admin_email'   => $GMAIL_CUA_BAN,
            'subject'       => $tieu_de_email,
            'html_body'     => $noi_dung_html,
            'ma_don'        => $ma_don,
            'ho_ten'        => $ho_ten,
            'so_dien_thoai' => $so_dien_thoai,
            'salon_id'      => $salon_id,
            'dich_vu'       => $chuoi_dich_vu_hien_thi,
            'ky_thuat_vien' => $ky_thuat_vien,
            'thoi_gian_dat' => $thoi_gian_dat_dep,
            'thoi_gian'     => $thoi_gian_dep
        ], JSON_UNESCAPED_UNICODE);
        ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Lịch Thành Công - CMTC Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
    body {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .booking-ticket {
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        background: #fff;
    }

    .ticket-header {
        background: linear-gradient(135deg, #111111 0%, #242c25 100%);
        color: #e6b96f;
        padding: 32px 24px;
        text-align: center;
        border-bottom: 3px solid #c09e6c;
    }

    .ticket-circle-logo {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e6b96f;
        background-color: #111;
        margin: 0 auto 12px;
        display: block;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .ticket-badge {
        background: rgba(230, 185, 111, 0.15);
        color: #e6b96f;
        border: 1px solid rgba(230, 185, 111, 0.4);
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.9rem;
        display: inline-block;
        margin-top: 8px;
    }

    .service-tag {
        display: inline-block;
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 4px 10px;
        font-weight: 600;
        font-size: 0.85rem;
        margin: 2px 4px 2px 0;
    }

    .thank-you-box {
        background-color: #fdfaf3;
        border-left: 4px solid #c09e6c;
        border-radius: 8px;
        padding: 15px 18px;
        color: #444;
        line-height: 1.65;
        text-align: justify;
    }

    .btn-gold {
        background-color: #111;
        color: #e6b96f;
        border: 1px solid #c09e6c;
        font-weight: 600;
        transition: all 0.25s ease;
    }

    .btn-gold:hover {
        background-color: #c09e6c;
        color: #111;
    }
    </style>
</head>

<body class="d-flex align-items-center min-vh-100 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="card booking-ticket">
                    <div class="ticket-header">
                        <!-- LOGO HÌNH TRÒN CỦA SALON (BẠN TỰ CHÈN ẢNH TẠI src BÊN DƯỚI) -->
                        <img src="../images/logo.png"
                            onerror="this.src='../images/Gemini_Generated_Image_1wira21wira21wir.png'"
                            alt="CMTC Salon Logo" class="ticket-circle-logo">
                        <h3 class="fw-bold mb-1 text-uppercase" style="letter-spacing: 1px;">Đặt Lịch Hẹn Thành Công
                        </h3>
                        <div class="ticket-badge">Mã phiếu hẹn: <strong>#<?= $ma_don ?></strong></div>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <?php if ($thong_bao_lui_30p !== ""): ?>
                        <div class="alert alert-warning border-warning small mb-4">
                            <i class="bi bi-clock-history me-1"></i><?= $thong_bao_lui_30p ?>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center p-3 rounded-3 mb-4"
                            style="background-color: #f0fdf4; border: 1px solid #bbf7d0;">
                            <i class="bi bi-envelope-check-fill fs-3 text-success me-3"></i>
                            <div class="small text-dark">
                                <strong>Phiếu hẹn điện tử đã được gửi tự động tới Gmail của bạn!</strong><br>
                                <span class="text-muted">Hộp thư nhận:
                                    <strong><?= htmlspecialchars($email_khach) ?></strong></span>
                            </div>
                        </div>

                        <h6 class="fw-bold text-uppercase text-secondary border-bottom pb-2 mb-3">
                            <i class="bi bi-receipt me-2"></i>Chi Tiết Phiếu Đặt Lịch & Thời Gian Đặt
                        </h6>

                        <table class="table table-bordered align-middle small mb-4">
                            <tbody>
                                <tr>
                                    <th class="bg-light text-muted py-2" style="width: 38%;">Khách hàng:</th>
                                    <td class="fw-bold text-dark py-2 fs-6"><?= htmlspecialchars($ho_ten) ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Thông tin liên hệ:</th>
                                    <td class="fw-semibold text-primary py-2"><?= htmlspecialchars($so_dien_thoai) ?> •
                                        <?= htmlspecialchars($email_khach) ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Cơ sở Salon đã chọn:</th>
                                    <td class="fw-bold text-dark py-2"><i
                                            class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($salon_id) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Dịch vụ đăng ký
                                        (<?= count($ds_dich_vu_chon) ?>):</th>
                                    <td class="py-2">
                                        <?php foreach ($ds_dich_vu_chon as $dv_ten): ?>
                                        <span class="service-tag"><i
                                                class="bi bi-check2-circle me-1"></i><?= htmlspecialchars($dv_ten) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Tổng chi phí dự kiến:</th>
                                    <td class="fw-bold text-success py-2 fs-6"><?= $tong_tien_dep ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Kỹ thuật viên phục vụ:</th>
                                    <td class="fw-bold text-dark py-2"><i
                                            class="bi bi-scissors me-1 text-warning"></i><?= htmlspecialchars($ky_thuat_vien) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Thời gian đặt đơn:</th>
                                    <td class="fw-semibold text-secondary py-2"><i
                                            class="bi bi-clock me-1"></i><?= $thoi_gian_dat_dep ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted py-2">Thời gian hẹn cắt:</th>
                                    <td class="fw-bold text-danger py-2 fs-5"><i
                                            class="bi bi-calendar-check me-1"></i><?= $thoi_gian_dep ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- LỜI CẢM ƠN DÀI VÀ CHÂN THÀNH TỪ CMTC SALON -->
                        <div class="thank-you-box small mb-4">
                            <div class="fw-bold text-dark mb-1"><i class="bi bi-heart-fill text-danger me-1"></i>Lời cảm
                                ơn từ CMTC Salon:</div>
                            <?= $loi_cam_on_dai ?>
                        </div>

                        <div class="row g-2 pt-1">
                            <div class="col-sm-6">
                                <a href="DatLich.php#bangLichSuGanDaySQL" class="btn btn-gold w-100 py-2 rounded-3">
                                    <i class="bi bi-calendar2-check me-1"></i>Xem Lịch Sử Đặt Lịch
                                </a>
                            </div>
                            <div class="col-sm-6">
                                <a href="../TrangChu/index.php"
                                    class="btn btn-outline-dark w-100 py-2 fw-semibold rounded-3">
                                    <i class="bi bi-house-door me-1"></i>Về Trang Chủ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const webhookUrl = <?= json_encode($GOOGLE_SCRIPT_WEBHOOK_URL) ?>;
    const emailPayload = <?= $du_lieu_email_json ?>;

    // Gửi thư xác nhận ngầm ngay       lập tức qua Google Apps Script Webhook
    if (webhookUrl && webhookUrl.trim() !== "") {
        fetch(webhookUrl, {
            method: "POST",
            mode: "no-cors",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(emailPayload)
        }).catch(err => console.log("Webhook:", err));
    }
    </script>
</body>

</html>
<?php
    } else {
        echo "Lỗi: " . $sql . "<br>" . $conn->error;
    }
}
$conn->close();
?>
<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. KẾT NỐI CSDL TỰ ĐỘNG NHẬN DIỆN XAMPP & INFINITYFREE
if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $conn = new mysqli("localhost", "root", "", "cmtc_salon");
} else {
    $conn = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
}
$conn->set_charset("utf8mb4");

// 2. CẤU HÌNH GMAIL HỖ TRỢ & WEBHOOK GOOGLE APPS SCRIPT
$GMAIL_SUPPORT_SALON       = "xuancongsupport@gmail.com";
$GOOGLE_SCRIPT_WEBHOOK_URL = "https://script.google.com/macros/s/AKfycbyHJ2O3wird81Rq1gzYSJniL4QyDrubNjWe4lgrax8aFlEsprvhnODZWh-dzJNkBYtlPA/exec";

// Tự động đồng bộ link Webhook mới nhất từ file ../DatLich/xu_ly_dat_lich.php (nếu bạn có cập nhật ở đó)
$file_xldl = __DIR__ . "/../DatLich/xu_ly_dat_lich.php";
if (file_exists($file_xldl)) {
    $xldl_content = file_get_contents($file_xldl);
    if (preg_match('/\$GOOGLE_SCRIPT_WEBHOOK_URL\s*=\s*"([^"]+)"/', $xldl_content, $m_url)) {
        if (!empty(trim($m_url[1]))) {
            $GOOGLE_SCRIPT_WEBHOOK_URL = trim($m_url[1]);
        }
    }
}

// Kiểm tra định dạng số điện thoại chính chủ Việt Nam (10 số: 03, 05, 07, 08, 09)
function laSoDienThoaiHopLe($sdt) {
    return (bool)preg_match('/^0(3|5|7|8|9)[0-9]{8}$/', trim($sdt));
}

// Kiểm tra định dạng địa chỉ Gmail hợp lệ
function laGmailHopLe($email) {
    $email = strtolower(trim($email));
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@') !== false;
}

// Hàm gửi payload tới Google Apps Script Webhook (Hỗ trợ cả cURL và Stream)
function goiGoogleScriptWebhook($webhook_url, $payload_arr) {
    if (empty($webhook_url)) {
        return false;
    }
    $json_data = json_encode($payload_arr, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = @curl_init($webhook_url);
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = @curl_exec($ch);
        $http_code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($http_code >= 200 && $http_code < 400) {
            return true;
        }
    }

    // Dự phòng bằng HTTP Stream nếu cURL bị tắt
    $opts = array(
        'http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $json_data,
            'timeout'       => 10,
            'ignore_errors' => true
        ),
        'ssl' => array(
            'verify_peer'      => false,
            'verify_peer_name' => false
        )
    );
    $ctx = @stream_context_create($opts);
    $res = @file_get_contents($webhook_url, false, $ctx);
    return ($res !== false);
}

// Hàm gửi mã xác thực OTP 6 số về chính Gmail của khách hàng (Tuyệt đối không hiện lên màn hình web)
function guiMaOTPVeGmailChinhChu($sdt, $email_nhan, $ma_otp, $webhook_url, $gmail_support) {
    $thoi_gian = date('H:i:s - d/m/Y');
    $tieu_de = "🔐 [CMCT SALON] Mã xác thực OTP đăng ký tài khoản của bạn là: $ma_otp";
    $noi_dung_html = "
    <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
        <div style='background-color: #0b4a4d; color: #e6b96f; padding: 22px; text-align: center; border-bottom: 3px solid #c09e6c;'>
            <h2 style='margin: 0; text-transform: uppercase; font-size: 20px;'>MÃ XÁC THỰC ĐĂNG KÝ TÀI KHOẢN</h2>
            <p style='color: #fff; margin: 5px 0 0; font-size: 13px;'>CMCT Salon - Hệ Thống Đặt Lịch</p>
        </div>
        <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65; text-align: center;'>
            <p style='margin-top: 0;'>Xin chào Quý khách,</p>
            <p>Hệ thống <strong>CMCT Salon</strong> nhận được yêu cầu xác thực đăng ký tài khoản cho số điện thoại <strong>$sdt</strong> và địa chỉ Gmail <strong>$email_nhan</strong>.</p>
            <p style='margin-bottom: 8px; font-weight: bold; color: #0b4a4d;'>Mã xác thực OTP gồm 6 chữ số của bạn là:</p>
            <div style='display: inline-block; background: #f0fdf4; border: 2px dashed #198754; color: #166534; font-size: 30px; font-weight: 800; letter-spacing: 8px; padding: 12px 30px; border-radius: 12px; margin: 10px 0;'>
                $ma_otp
            </div>
            <p style='color: #dc3545; font-size: 13px; font-weight: bold; margin-top: 12px;'>
                ⏳ Mã OTP này có hiệu lực trong vòng 5 phút (발행 lúc: $thoi_gian).
            </p>
            <p style='color: #666; font-size: 13px; margin-bottom: 0;'>
                Vui lòng nhập chính xác 6 chữ số này vào ô xác thực trên trang Đăng ký CMCT Salon để hoàn tất tạo tài khoản. Tuyệt đối không chia sẻ mã này cho người khác.
            </p>
        </div>
        <div style='background-color: #111; color: #e6b96f; padding: 12px; text-align: center; font-size: 12px;'>
            Email được gửi tự động từ hệ thống bảo mật CMCT Salon ($gmail_support).
        </div>
    </div>";

    $payload_arr = array(
        'to_email'    => $email_nhan,
        'admin_email' => "",
        'subject'     => $tieu_de,
        'html_body'   => $noi_dung_html
    );

    $da_gui = goiGoogleScriptWebhook($webhook_url, $payload_arr);
    return array('sent_by_php' => $da_gui, 'payload' => $payload_arr);
}

// Hàm gửi thư chúc mừng sau khi khách nhập đúng OTP và tạo tài khoản thành công
function taoVaGuiMailDangKyTaiKhoan($ho_ten, $email_khach, $so_dien_thoai, $webhook_url, $gmail_support) {
    if (empty($email_khach) || strpos($email_khach, '@') === false || empty($webhook_url)) {
        return null;
    }

    $thoi_gian_dk = date('H:i:s - d/m/Y');
    $tieu_de = "🎉 [CMCT SALON] Xác nhận đăng ký tài khoản thành công!";
    $noi_dung_html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden;'>
        <div style='background-color: #0b4a4d; color: #e6b96f; padding: 24px; text-align: center; border-bottom: 3px solid #c09e6c;'>
            <h2 style='margin: 0; text-transform: uppercase;'>CMCT SALON - ĐĂNG KÝ THÀNH CÔNG</h2>
        </div>
        <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65;'>
            <p>Xin chào <strong>$ho_ten</strong>,</p>
            <p>Chúc mừng bạn! Địa chỉ Gmail <strong>$email_khach</strong> và Số điện thoại chính chủ <strong>$so_dien_thoai</strong> đã được xác thực mã OTP 6 số thành công trên hệ thống <strong>CMCT Salon</strong>.</p>
            <p>Thời gian đăng ký: <strong>$thoi_gian_dk</strong></p>
        </div>
        <div style='background-color: #111; color: #e6b96f; padding: 14px; text-align: center; font-size: 12px;'>
            Email được gửi tự động từ bộ phận hỗ trợ CMCT Salon ($gmail_support).
        </div>
    </div>";

    $payload_arr = array(
        'to_email'      => $email_khach,
        'admin_email'   => "",
        'subject'       => $tieu_de,
        'html_body'     => $noi_dung_html,
        'ma_don'        => "TK_MOI",
        'ho_ten'        => $ho_ten,
        'so_dien_thoai' => $so_dien_thoai
    );

    goiGoogleScriptWebhook($webhook_url, $payload_arr);
    return $payload_arr;
}

$conn->query("CREATE TABLE IF NOT EXISTS `tai_khoan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ho_ten` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `so_dien_thoai` varchar(20) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `vai_tro` varchar(20) DEFAULT 'khach',
  `trang_thai_duyet` tinyint(1) DEFAULT 1,
  `ngay_tao` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("INSERT IGNORE INTO `tai_khoan` (`id`, `ho_ten`, `email`, `so_dien_thoai`, `mat_khau`, `vai_tro`, `trang_thai_duyet`) VALUES
(1, 'Vừ Xuân Công (Admin)', 'xuanconggaming@gmail.com', '0971714681', '123456', 'admin', 1);");

$thong_bao = "";
$loai_tb = "danger";
$mo_tab_dang_ky = false;

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// =========================================================================================
// 3. XỬ LÝ AJAX: GỬI MÃ OTP 6 SỐ VỀ CHÍNH GMAIL CHÍNH CHỦ (KHÔNG HIỆN MÃ LÊN MÀN HÌNH)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_real_sms') {
    @header('Content-Type: application/json; charset=utf-8');

    $sdt_raw   = trim($_POST['so_dien_thoai'] ?? '');
    $email_raw = trim($_POST['email'] ?? '');

    if (!laGmailHopLe($email_raw)) {
        echo json_encode(array(
            'status' => 'error',
            'msg'    => 'Vui lòng nhập đúng địa chỉ Gmail chính chủ của bạn trước khi bấm Lấy OTP!'
        ));
        exit();
    }

    if (!laSoDienThoaiHopLe($sdt_raw)) {
        echo json_encode(array(
            'status' => 'error',
            'msg'    => 'Số điện thoại không đúng định dạng chính chủ (phải đủ 10 số, bắt đầu bằng 03, 05, 07, 08, 09)!'
        ));
        exit();
    }

    $sdt_check   = $conn->real_escape_string($sdt_raw);
    $email_check = $conn->real_escape_string($email_raw);

    // Kiểm tra trùng SĐT hoặc Gmail trong CSDL
    $check_sql = "SELECT so_dien_thoai, email FROM tai_khoan WHERE so_dien_thoai = '$sdt_check' OR email = '$email_check'";
    $res = $conn->query($check_sql);

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (strtolower($row['email']) === strtolower($email_raw)) {
            echo json_encode(array('status' => 'error', 'msg' => 'Địa chỉ Gmail này đã được đăng ký tài khoản trên hệ thống!'));
        } else {
            echo json_encode(array('status' => 'error', 'msg' => 'Số điện thoại này đã được đăng ký tài khoản trên hệ thống!'));
        }
        exit();
    }

    // Sinh mã OTP 6 số ngẫu nhiên lưu kín trong SESSION (Không gửi otp_code ra màn hình)
    $ma_otp = str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['real_otp_code']   = $ma_otp;
    $_SESSION['real_otp_phone']  = $sdt_raw;
    $_SESSION['real_otp_email']  = strtolower($email_raw);
    $_SESSION['real_otp_expire'] = time() + 300; // Hiệu lực 5 phút

    // Gửi mã OTP 6 số về chính địa chỉ Gmail khách vừa nhập
    $kq_mail = guiMaOTPVeGmailChinhChu($sdt_raw, $email_raw, $ma_otp, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);

    $response_arr = array(
        'status' => 'success',
        'msg'    => "Đã gửi mã xác thực OTP gồm 6 chữ số về hộp thư Gmail ($email_raw)! Vui lòng mở Gmail của bạn để lấy mã nhập vào ô xác thực."
    );

    // Nếu hosting chặn cURL phía server thì gửi ngầm qua kết nối trình duyệt (vẫn không hiển thị mã lên màn hình)
    if (!$kq_mail['sent_by_php']) {
        $response_arr['webhook_url']     = $GOOGLE_SCRIPT_WEBHOOK_URL;
        $response_arr['webhook_payload'] = base64_encode(json_encode($kq_mail['payload'], JSON_UNESCAPED_UNICODE));
    }

    echo json_encode($response_arr);
    exit();
}

// 4. XỬ LÝ CẬP NHẬT TÊN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_cap_nhat_ten_khach']) && isset($_SESSION['user_id'])) {
    $ten_moi = $conn->real_escape_string(trim($_POST['ho_ten_moi']));
    if ($ten_moi !== "") {
        $uid_up = (int)$_SESSION['user_id'];
        $sdt_kh = $conn->real_escape_string($_SESSION['so_dien_thoai'] ?? '');
        $conn->query("UPDATE tai_khoan SET ho_ten = '$ten_moi' WHERE id = $uid_up");
        if ($sdt_kh !== '') {
            $conn->query("UPDATE bang_dat_lich SET ho_ten = '$ten_moi' WHERE so_dien_thoai = '$sdt_kh'");
        }
        $_SESSION['ho_ten'] = trim($_POST['ho_ten_moi']);
        $loai_tb = "success";
        $thong_bao = "✅ Đã cập nhật Họ và Tên thành công!";
    }
}

// =========================================================================================
// 5. XỬ LÝ ĐĂNG KÝ (BẮT BUỘC NHẬP ĐÚNG MÃ OTP 6 SỐ NHẬN TRONG GMAIL MỚI CHO ĐĂNG KÝ)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_dang_ky'])) {
    $mo_tab_dang_ky = true;
    $ho_ten        = $conn->real_escape_string(trim($_POST['ho_ten'] ?? ''));
    $email_raw     = trim($_POST['email'] ?? '');
    $email         = $conn->real_escape_string($email_raw);
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $mat_khau      = $conn->real_escape_string(trim($_POST['mat_khau'] ?? ''));
    $ma_otp_nhap   = trim($_POST['ma_otp_sms'] ?? '');

    if (!laGmailHopLe($email_raw)) {
        $thong_bao = "❌ Địa chỉ Gmail không hợp lệ!";
    } elseif (!laSoDienThoaiHopLe($so_dien_thoai)) {
        $thong_bao = "❌ Số điện thoại không đúng định dạng chính chủ (10 số, đầu 03, 05, 07, 08, 09)!";
    } elseif (!isset($_SESSION['real_otp_code']) || !isset($_SESSION['real_otp_phone']) || !isset($_SESSION['real_otp_email'])) {
        $thong_bao = "❌ Bạn chưa bấm nút 'Lấy OTP'! Vui lòng lấy mã xác thực trước.";
    } elseif ($_SESSION['real_otp_phone'] !== $so_dien_thoai || $_SESSION['real_otp_email'] !== strtolower($email_raw)) {
        $thong_bao = "❌ Gmail hoặc Số điện thoại không khớp với thông tin đã nhận mã OTP!";
    } elseif ($_SESSION['real_otp_code'] !== $ma_otp_nhap) {
        $thong_bao = "❌ Mã xác thực OTP 6 số không chính xác! Vui lòng kiểm tra lại hộp thư Gmail của bạn.";
    } elseif (time() > $_SESSION['real_otp_expire']) {
        $thong_bao = "❌ Mã OTP đã hết hạn (quá 5 phút). Vui lòng bấm Lấy lại mã OTP mới!";
    } else {
        $sdt_esc = $conn->real_escape_string($so_dien_thoai);
        $check = $conn->query("SELECT id FROM tai_khoan WHERE email = '$email' OR so_dien_thoai = '$sdt_esc'");
        if ($check && $check->num_rows > 0) {
            $thong_bao = "❌ Email hoặc Số điện thoại này đã được đăng ký trên hệ thống!";
        } else {
            $sql_ins = "INSERT INTO tai_khoan (ho_ten, email, so_dien_thoai, mat_khau, vai_tro, trang_thai_duyet) 
                        VALUES ('$ho_ten', '$email', '$sdt_esc', '$mat_khau', 'khach', 1)";
            if ($conn->query($sql_ins)) {
                unset($_SESSION['real_otp_code'], $_SESSION['real_otp_phone'], $_SESSION['real_otp_email'], $_SESSION['real_otp_expire']);

                $new_uid = $conn->insert_id;
                $_SESSION['user_id']          = $new_uid;
                $_SESSION['ho_ten']           = trim($_POST['ho_ten']);
                $_SESSION['email']            = $email_raw;
                $_SESSION['so_dien_thoai']    = $so_dien_thoai;
                $_SESSION['vai_tro']          = 'khach';
                $_SESSION['trang_thai_duyet'] = 1;

                taoVaGuiMailDangKyTaiKhoan($_POST['ho_ten'], $email_raw, $so_dien_thoai, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);
                header("Location: ../DatLich/DatLich.php");
                exit();
            } else {
                $thong_bao = "❌ Lỗi đăng ký: " . $conn->error;
            }
        }
    }
}

// 6. XỬ LÝ ĐĂNG NHẬP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_dang_nhap'])) {
    $tai_khoan_input = $conn->real_escape_string(trim($_POST['email']));
    $mat_khau        = $conn->real_escape_string(trim($_POST['password']));

    $sql_login = "SELECT * FROM tai_khoan WHERE (email = '$tai_khoan_input' OR so_dien_thoai = '$tai_khoan_input') AND mat_khau = '$mat_khau'";
    $res = $conn->query($sql_login);

    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id']          = $user['id'];
        $_SESSION['ho_ten']           = $user['ho_ten'];
        $_SESSION['email']            = $user['email'];
        $_SESSION['so_dien_thoai']    = $user['so_dien_thoai'];
        $_SESSION['vai_tro']          = $user['vai_tro'];
        $_SESSION['trang_thai_duyet'] = 1;

        if ($user['vai_tro'] == 'admin') {
            header("Location: admin.php");
            exit();
        } else {
            header("Location: ../DatLich/DatLich.php");
            exit();
        }
    } else {
        $thong_bao = "❌ Email/Số điện thoại hoặc Mật khẩu không chính xác!";
    }
}
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Đăng Nhập & Xác Thực OTP - CMTC Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Inter', sans-serif;
    }

    .login-wrapper {
        display: flex;
        align-items: center;
        min-height: 100vh;
    }

    .login-card {
        background: #fff;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        width: 100%;
        max-width: 500px;
        margin: auto;
    }

    .btn-dark-teal {
        background-color: #0b4a4d;
        color: #fff;
        font-weight: 600;
        padding: 11px;
        border-radius: 10px;
    }

    .btn-dark-teal:hover {
        background-color: #083739;
        color: #fff;
    }

    .nav-pills .nav-link.active {
        background-color: #0b4a4d;
    }

    .nav-pills .nav-link {
        color: #0b4a4d;
        font-weight: 600;
    }

    .otp-input-box {
        letter-spacing: 6px;
        font-weight: 800;
        font-size: 1.2rem;
        text-align: center;
        color: #0b4a4d;
        border: 2px solid #0b4a4d;
    }
    </style>
</head>

<body>

    <main class="login-wrapper py-5">
        <div class="container">
            <div class="login-card mx-auto">
                <div class="text-center mb-2">
                    <img src="../images/logo.png"
                        onerror="this.src='https://cdn-icons-png.flaticon.com/512/3504/3504018.png'"
                        alt="CMTC Salon Logo" class="img-fluid rounded-circle shadow-sm"
                        style="width: 85px; height: 85px; border: 3px solid #0b4a4d; object-fit: cover; padding: 2px; background: #fff;" />
                </div>

                <?php if (!isset($_SESSION['user_id'])): ?>
                <h2 class="text-center fw-bold text-dark mb-2">Hệ Thống CMTC Salon</h2>
                <p class="text-center text-muted mb-4 small">Vui lòng xác thực SĐT & Gmail chính chủ bằng mã OTP 6 số
                    trước khi đăng ký</p>

                <?php if ($thong_bao !== ""): ?>
                <div class="alert alert-<?= $loai_tb ?> text-center fw-semibold small"><?= $thong_bao ?></div>
                <?php endif; ?>

                <ul class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-3" id="authTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link <?= !$mo_tab_dang_ky ? 'active' : '' ?> rounded-3" id="login-tab"
                            data-bs-toggle="pill" data-bs-target="#tabLogin" type="button">Đăng Nhập</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?= $mo_tab_dang_ky ? 'active' : '' ?> rounded-3" id="register-tab"
                            data-bs-toggle="pill" data-bs-target="#tabRegister" type="button">Đăng Ký Mới</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- TAB ĐĂNG NHẬP -->
                    <div class="tab-pane fade <?= !$mo_tab_dang_ky ? 'show active' : '' ?>" id="tabLogin">
                        <form method="POST" action="login.php">
                            <input type="hidden" name="btn_dang_nhap" value="1" />
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Email hoặc Số điện thoại</label>
                                <input type="text" name="email" class="form-control"
                                    placeholder="Nhập Gmail hoặc SĐT..." required />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small m-0">Mật khẩu</label>
                                <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu"
                                    required />
                            </div>
                            <button type="submit" class="btn btn-dark-teal w-100 mb-3">Đăng Nhập <i
                                    class="fa-solid fa-arrow-right ms-2 small"></i></button>
                        </form>
                    </div>

                    <!-- TAB ĐĂNG KÝ -->
                    <div class="tab-pane fade <?= $mo_tab_dang_ky ? 'show active' : '' ?>" id="tabRegister">
                        <form method="POST" action="login.php" onsubmit="return validateForm();">
                            <input type="hidden" name="btn_dang_ky" value="1" />
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Họ và Tên khách hàng</label>
                                <input type="text" name="ho_ten" class="form-control"
                                    value="<?= htmlspecialchars($_POST['ho_ten'] ?? '') ?>"
                                    placeholder="VD: Nguyễn Văn A" required />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Địa chỉ Gmail chính chủ (Nhận mã
                                    OTP 6 số)</label>
                                <input type="email" name="email" id="regEmail" class="form-control"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    placeholder="VD: khachhang@gmail.com" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Số điện thoại chính chủ (10
                                    số)</label>
                                <div class="input-group">
                                    <input type="tel" name="so_dien_thoai" id="regSoDienThoai"
                                        class="form-control fw-bold"
                                        value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>"
                                        placeholder="VD: 0398796035" maxlength="10"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');" required />
                                    <button type="button" id="btnGuiMaOTP" class="btn btn-warning fw-bold px-3"
                                        onclick="guiMaOTPVeGmail()">
                                        <i class="bi bi-send-check-fill me-1"></i>Lấy OTP
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3 p-3 rounded-3 border bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-bold text-dark small mb-0"><i
                                            class="bi bi-shield-lock-fill text-success me-1"></i>NHẬP MÃ OTP 6 SỐ TỪ
                                        GMAIL</label>
                                    <span id="boDemNguocOTP" class="badge bg-secondary" style="font-size: 11px;">Chưa
                                        lấy mã</span>
                                </div>
                                <input type="text" name="ma_otp_sms" id="regMaOTP" class="form-control otp-input-box"
                                    placeholder="••••••" maxlength="6"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Mật khẩu</label>
                                <input type="password" name="mat_khau" class="form-control"
                                    placeholder="Tạo mật khẩu..." minlength="6" required />
                            </div>
                            <button type="submit" class="btn btn-dark-teal w-100">Đăng Ký Tài Khoản</button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <!-- ĐÃ ĐĂNG NHẬP -->
                <h4 class="text-center fw-bold text-dark mb-3">Tài Khoản Của Bạn</h4>
                <a href="../DatLich/DatLich.php" class="btn btn-dark-teal w-100 mb-3 py-3 fs-6">ĐẾN TRANG ĐẶT LỊCH
                    NGAY</a>
                <?php if ($_SESSION['vai_tro'] == 'admin'): ?>
                <a href="admin.php" class="btn btn-warning w-100 fw-bold mb-3">VÀO TRANG ADMIN</a>
                <?php endif; ?>
                <a href="login.php?logout=1" class="btn btn-outline-danger w-100 btn-sm">Đăng xuất</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const regexSDTChinhChu = /^0(3|5|7|8|9)[0-9]{8}$/;

    function validateForm() {
        const email = document.getElementById('regEmail').value.trim();
        const sdt = document.getElementById('regSoDienThoai').value.trim();
        const otp = document.getElementById('regMaOTP').value.trim();
        if (!email || !email.includes('@')) {
            alert('Vui lòng nhập đúng địa chỉ Gmail chính chủ!');
            return false;
        }
        if (!regexSDTChinhChu.test(sdt)) {
            alert('Số điện thoại không đúng định dạng chính chủ (10 số, bắt đầu bằng 03, 05, 07, 08, 09)!');
            return false;
        }
        if (otp.length !== 6) {
            alert('Vui lòng nhập đủ mã OTP 6 số đã gửi về Gmail của bạn!');
            return false;
        }
        return true;
    }

    let countdownTimer = null;

    function batDauDemNguoc() {
        let btn = document.getElementById('btnGuiMaOTP');
        let badge = document.getElementById('boDemNguocOTP');
        let thoiGian = 60;
        btn.disabled = true;

        if (countdownTimer) clearInterval(countdownTimer);
        countdownTimer = setInterval(function() {
            thoiGian--;
            btn.innerText = `Đợi (${thoiGian}s)`;
            badge.className = 'badge bg-success';
            badge.innerText = 'Đã gửi về Gmail';
            if (thoiGian <= 0) {
                clearInterval(countdownTimer);
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-arrow-repeat me-1"></i>Gửi lại`;
            }
        }, 1000);
    }

    function guiMaOTPVeGmail() {
        let email = document.getElementById('regEmail').value.trim();
        let sdt = document.getElementById('regSoDienThoai').value.trim();

        if (!email || !email.includes('@')) {
            alert('Vui lòng nhập địa chỉ Gmail chính chủ của bạn trước khi bấm Lấy OTP!');
            document.getElementById('regEmail').focus();
            return;
        }

        if (!regexSDTChinhChu.test(sdt)) {
            alert('Vui lòng nhập đúng số điện thoại chính chủ (10 số, bắt đầu bằng 03, 05, 07, 08, 09).');
            document.getElementById('regSoDienThoai').focus();
            return;
        }

        let btn = document.getElementById('btnGuiMaOTP');
        btn.disabled = true;
        btn.innerText = 'Đang gửi...';

        const formData = new FormData();
        formData.append('action', 'send_real_sms');
        formData.append('so_dien_thoai', sdt);
        formData.append('email', email);

        fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'error') {
                    alert('❌ ' + data.msg);
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-send-check-fill me-1"></i>Lấy OTP`;
                } else {
                    // Nếu server chặn cURL, tự động bắn ngầm qua trình duyệt tới Google Script (không hiện mã lên màn hình)
                    if (data.webhook_url && data.webhook_payload) {
                        try {
                            const rawJson = decodeURIComponent(escape(window.atob(data.webhook_payload)));
                            fetch(data.webhook_url, {
                                method: 'POST',
                                mode: 'no-cors',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: rawJson
                            });
                        } catch (e) {}
                    }
                    alert('✅ ' + data.msg);
                    batDauDemNguoc();
                    document.getElementById('regMaOTP').focus();
                }
            })
            .catch(err => {
                alert('❌ Lỗi kết nối Server!');
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-send-check-fill me-1"></i>Lấy OTP`;
            });
    }
    </script>
</body>

</html>
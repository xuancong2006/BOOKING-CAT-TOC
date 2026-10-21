<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. KẾT NỐI CSDL (Dùng từ khóa OR thay cho ký tự sổ thẳng để không bao giờ lỗi khi Copy)
if ($_SERVER['HTTP_HOST'] == 'localhost' OR strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $conn = new mysqli("localhost", "root", "", "cmtc_salon");
} else {
    $conn = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
}
$conn->set_charset("utf8mb4");

// =========================================================================================
// 2. CẤU HÌNH TÍCH HỢP SMS GATEWAY & WEBHOOK GMAIL
// =========================================================================================
$GMAIL_SUPPORT_SALON = "xuancongsupport@gmail.com";
$GOOGLE_SCRIPT_WEBHOOK_URL = "https://script.google.com/macros/s/AKfycbyHJ2O3wird81Rq1gzYSJniL4QyDrubNjWe4lgrax8aFlEsprvhnODZWh-dzJNkBYtlPA/exec";

// CÁCH A: Gửi SMS miễn phí từ SIM điện thoại Android qua TextBee (https://textbee.dev)
$TEXTBEE_API_KEY   = ""; // Dán API Key từ app TextBee vào đây (nếu dùng)
$TEXTBEE_DEVICE_ID = ""; // Dán Device ID từ app TextBee vào đây (nếu dùng)

// CÁCH B: Gửi SMS qua cổng SpeedSMS Việt Nam (https://speedsms.vn)
$SPEEDSMS_ACCESS_TOKEN = ""; // Dán Access Token SpeedSMS vào đây (nếu dùng)

// =========================================================================================
// 3. CÁC HÀM KIỂM TRA SĐT CHÍNH CHỦ & GỬI MÃ OTP 6 SỐ
// =========================================================================================
function kiemTraDinhDangSDT($sdt) {
    $sdt = trim($sdt);
    if (strlen($sdt) !== 10) {
        return false;
    }
    if (!ctype_digit($sdt)) { return false; }$dau_so = substr($sdt, 0, 2);$cac_dau_so_vn = array('03', '05', '07', '08', '09');
    return in_array($dau_so,$cac_dau_so_vn, true);
}

function guiTinNhanSMSOTP($so_dien_thoai,$ma_otp, $textbee_key,$textbee_device, $speedsms_token) {$noi_dung = "[CMTC SALON] Ma OTP xac thuc SĐT dang ky tai khoan cua ban la: " . $ma_otp . ". Hieu luc trong 5 phut.";

    // Ưu tiên 1: Gửi qua TextBee SMS Gateway (SIM điện thoại cá nhân)
    if (!empty($textbee_key) AND !empty($textbee_device) AND function_exists('curl_init')) {
        $sdt_qt = '+84' . substr($so_dien_thoai, 1);
        $url_tb = "https://api.textbee.dev/api/v1/gateway/devices/" . trim($textbee_device) . "/send-sms";
        $payload_tb = json_encode(array(
            "recipients" => array($sdt_qt),
            "message"    => $noi_dung
        ));
        $ch = @curl_init($url_tb);
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS,$payload_tb);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'x-api-key: ' . trim($textbee_key)
        ));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $kq = @curl_exec($ch);
        $http_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        if ($http_code >= 200 AND$http_code < 300) {
            return array('da_gui_sms_that' => true, 'kenh' => 'TextBee SMS');
        }
    }

    // Ưu tiên 2: Gửi qua cổng SpeedSMS.vn
    if (!empty($speedsms_token) AND function_exists('curl_init')) {$sdt_qt = '84' . substr($so_dien_thoai, 1);$payload_sp = json_encode(array(
            "to"       => array($sdt_qt),
            "content"  => $noi_dung,
            "sms_type" => 2,
            "sender"   => ""
        ));
        $ch = @curl_init("https://api.speedsms.vn/index.php/sms/send");
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_USERPWD, trim($speedsms_token) . ":x");
        @curl_setopt($ch, CURLOPT_POSTFIELDS,$payload_sp);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $kq = @curl_exec($ch);
        @curl_close($ch);
        return array('da_gui_sms_that' => true, 'kenh' => 'SpeedSMS');
    }

    // Chế độ mặc định (Sandbox trên Web)
    return array('da_gui_sms_that' => false, 'kenh' => 'SMS Simulator');
}

function taoVaGuiMailDangKyTaiKhoan($ho_ten, $email_khach,$so_dien_thoai, $webhook_url,$gmail_support) {
    if (empty($email_khach) OR strpos($email_khach, '@') === false OR empty($webhook_url)) {
        return null;
    }

    $thoi_gian_dk = date('H:i:s - d/m/Y');$tieu_de = "🎉 [CMTC SALON] Xác nhận đăng ký tài khoản thành công!";
    $noi_dung_html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden;'>
        <div style='background-color: #0b4a4d; color: #e6b96f; padding: 24px; text-align: center; border-bottom: 3px solid #c09e6c;'>
            <h2 style='margin: 0; text-transform: uppercase;'>CMTC SALON - ĐĂNG KÝ THÀNH CÔNG</h2>
        </div>
        <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65;'>
            <p>Xin chào <strong>$ho_ten</strong>,</p>
            <p>Số điện thoại <strong>$so_dien_thoai</strong> và Gmail <strong>$email_khach</strong> của bạn đã được xác thực OTP thành công lúc $thoi_gian_dk.</p>
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

    $json_data = json_encode($payload_arr, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = @curl_init($webhook_url);
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS,$json_data);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        @curl_exec($ch);
        @curl_close($ch);
    }

    return $payload_arr;
}

// Khởi tạo bảng tai_khoan nếu chưa có
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
(1, 'Vừ Xuân Công (Admin)', 'xuanconggaming@gmail.com', '0971714681', '123456', 'admin', 1),
(2, 'Thầy Nguyễn Văn Dũng', 'nguyenvandung@nau.edu.vn', '0912345678', '123456', 'khach', 1),
(3, 'Lầu Bá Trừ', 'laubatru@gmail.com', '0354001735', '123456', 'khach', 1);");

$thong_bao = "";
$loai_tb = "danger";
$mo_tab_dang_ky = false;

// =========================================================================================
// 4. XỬ LÝ AJAX GỬI MÃ OTP 6 SỐ VỀ SMS SỐ ĐIỆN THOẠI
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" AND isset($_POST['action']) AND $_POST['action'] == 'send_sms_otp') {
    header('Content-Type: application/json; charset=utf-8');
    $sdt_nhan = str_replace(' ', '', trim($_POST['so_dien_thoai'] ?? ''));
    $email_dk = trim($_POST['email'] ?? '');

    if (!kiemTraDinhDangSDT($sdt_nhan)) {
        echo json_encode(array(
            'status'  => 'error',
            'message' => '❌ SĐT không đúng định dạng! Vui lòng nhập đúng 10 chữ số di động Việt Nam (bắt đầu bằng 03, 05, 07, 08, 09).'
        ));
        exit();
    }

    $sdt_esc = $conn->real_escape_string($sdt_nhan);
    $email_esc = $conn->real_escape_string($email_dk);

    $sql_check = "SELECT id, so_dien_thoai, email FROM tai_khoan WHERE so_dien_thoai = '$sdt_esc'";
    if ($email_esc !== '') {
        $sql_check .= " OR email = '$email_esc'";
    }
    $res_exist = $conn->query($sql_check);
    if ($res_exist AND $res_exist->num_rows > 0) {
        $row_ex = $res_exist->fetch_assoc();
        if ($row_ex['so_dien_thoai'] === $sdt_nhan) {
            echo json_encode(array(
                'status'  => 'error',
                'message' => '❌ Số điện thoại ' . $sdt_nhan . ' đã được đăng ký tài khoản trước đó!'
            ));
            exit();
        } else {
            echo json_encode(array(
                'status'  => 'error',
                'message' => '❌ Địa chỉ Email này đã được sử dụng!'
            ));
            exit();
        }
    }

    // Tạo mã OTP ngẫu nhiên gồm đúng 6 chữ số
    $ma_otp = str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_sms_code']   = $ma_otp;
    $_SESSION['otp_sms_phone']  = $sdt_nhan;
    $_SESSION['otp_sms_expire'] = time() + 300; // Hết hạn sau 5 phút

    $kq_sms = guiTinNhanSMSOTP($sdt_nhan, $ma_otp, $TEXTBEE_API_KEY, $TEXTBEE_DEVICE_ID, $SPEEDSMS_ACCESS_TOKEN);

    echo json_encode(array(
        'status'      => 'success',
        'message'     => "✅ Đã gửi mã OTP 6 số về SMS số điện thoại $sdt_nhan (Hiệu lực 5 phút).",
        'is_sandbox'  => !$kq_sms['da_gui_sms_that'],
        'sandbox_otp' => !$kq_sms['da_gui_sms_that'] ? $ma_otp : null
    ));
    exit();
}

// 5. XỬ LÝ ĐĂNG XUẤT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 6. XỬ LÝ ĐỒNG BỘ GOOGLE LOGIN
if (isset($_POST['action']) AND $_POST['action'] == 'google_sync') {
    header('Content-Type: application/json');
    $email = $conn->real_escape_string(trim($_POST['email']));
    $ho_ten = $conn->real_escape_string(trim($_POST['ho_ten']));
    $sdt = !empty($_POST['so_dien_thoai']) ? $conn->real_escape_string(trim($_POST['so_dien_thoai'])) : '0971714681';

    $check = $conn->query("SELECT * FROM tai_khoan WHERE email = '$email'");
    if ($check AND $check->num_rows > 0) {
        $user = $check->fetch_assoc();
    } else {
        $vai_tro = ($email == 'xuanconggaming@gmail.com') ? 'admin' : 'khach';
        $duyet = 1;
        $conn->query("INSERT INTO tai_khoan (ho_ten, email, so_dien_thoai, mat_khau, vai_tro, trang_thai_duyet) 
                      VALUES ('$ho_ten', '$email', '$sdt', 'GOOGLE_AUTH', '$vai_tro', $duyet)");
        $id_moi = $conn->insert_id;
        $user = array(
            'id'               => $id_moi,
            'ho_ten'           => $ho_ten,
            'email'            => $email,
            'so_dien_thoai'    => $sdt,
            'vai_tro'          => $vai_tro,
            'trang_thai_duyet' => $duyet
        );
        taoVaGuiMailDangKyTaiKhoan(trim($_POST['ho_ten']), trim($_POST['email']), $sdt, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);
    }

    $_SESSION['user_id']          = $user['id'];
    $_SESSION['ho_ten']           = $user['ho_ten'];
    $_SESSION['email']            = $user['email'];
    $_SESSION['so_dien_thoai']    = $user['so_dien_thoai'];
    $_SESSION['vai_tro']          = $user['vai_tro'];
    $_SESSION['trang_thai_duyet'] = 1;

    echo json_encode(array(
        'status'           => 'success',
        'vai_tro'          => $user['vai_tro'],
        'trang_thai_duyet' => 1,
        'redirect'         => ($user['vai_tro'] == 'admin') ? 'admin.php' : '../DatLich/DatLich.php'
    ));
    exit();
}

// 7. CẬP NHẬT HỌ VÀ TÊN
if ($_SERVER["REQUEST_METHOD"] == "POST" AND isset($_POST['btn_cap_nhat_ten_khach']) AND isset($_SESSION['user_id'])) {
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
// 8. XỬ LÝ ĐĂNG KÝ TÀI KHOẢN (CHỈ CHO ĐĂNG KÝ KHI NHẬP ĐÚNG MÃ OTP 6 SỐ)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" AND isset($_POST['btn_dang_ky'])) {
    $mo_tab_dang_ky = true;
    $ho_ten_raw   = trim($_POST['ho_ten'] ?? '');
    $email_raw    = trim($_POST['email'] ?? '');
    $sdt_raw      = str_replace(' ', '', trim($_POST['so_dien_thoai'] ?? ''));
    $mat_khau_raw = trim($_POST['mat_khau'] ?? '');
    $ma_otp_nhap  = trim($_POST['ma_otp_sms'] ?? '');

    $ho_ten        = $conn->real_escape_string($ho_ten_raw);
    $email         = $conn->real_escape_string($email_raw);
    $so_dien_thoai = $conn->real_escape_string($sdt_raw);
    $mat_khau      = $conn->real_escape_string($mat_khau_raw);

    if (!kiemTraDinhDangSDT($sdt_raw)) {
        $thong_bao = "❌ Số điện thoại không đúng định dạng 10 số Việt Nam (03, 05, 07, 08, 09)!";
    } elseif (!isset($_SESSION['otp_sms_code']) OR !isset($_SESSION['otp_sms_phone']) OR !isset($_SESSION['otp_sms_expire'])) {
        $thong_bao = "❌ Vui lòng bấm nút 'Gửi mã OTP' để lấy mã 6 số xác thực SĐT trước khi đăng ký!";
    } elseif (time() > $_SESSION['otp_sms_expire']) {
        unset($_SESSION['otp_sms_code'], $_SESSION['otp_sms_phone'], $_SESSION['otp_sms_expire']);
        $thong_bao = "❌ Mã OTP đã hết hạn (quá 5 phút)! Vui lòng bấm gửi lại mã mới.";
    } elseif ($_SESSION['otp_sms_phone'] !== $sdt_raw) {
        $thong_bao = "❌ Số điện thoại bạn nhập không khớp với số điện thoại đã nhận mã OTP!";
    } elseif (strlen($ma_otp_nhap) !== 6 OR !ctype_digit($ma_otp_nhap) OR $ma_otp_nhap !== $_SESSION['otp_sms_code']) {
        $thong_bao = "❌ Mã OTP 6 số không chính xác! Không thể đăng ký tài khoản khi nhập sai mã OTP.";
    } else {
        $check = $conn->query("SELECT id FROM tai_khoan WHERE email = '$email' OR so_dien_thoai = '$so_dien_thoai'");
        if ($check AND $check->num_rows > 0) {
            $thong_bao = "❌ Email hoặc Số điện thoại này đã được đăng ký trên hệ thống!";
        } else {
            $sql_ins = "INSERT INTO tai_khoan (ho_ten, email, so_dien_thoai, mat_khau, vai_tro, trang_thai_duyet) 
                        VALUES ('$ho_ten', '$email', '$so_dien_thoai', '$mat_khau', 'khach', 1)";
            if ($conn->query($sql_ins)) {
                unset($_SESSION['otp_sms_code'], $_SESSION['otp_sms_phone'], $_SESSION['otp_sms_expire']);

                $new_uid = $conn->insert_id;
                $_SESSION['user_id']          = $new_uid;
                $_SESSION['ho_ten']           = $ho_ten_raw;
                $_SESSION['email']            = $email_raw;
                $_SESSION['so_dien_thoai']    = $sdt_raw;
                $_SESSION['vai_tro']          = 'khach';
                $_SESSION['trang_thai_duyet'] = 1;

                taoVaGuiMailDangKyTaiKhoan($ho_ten_raw, $email_raw, $sdt_raw, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);
                header("Location: ../DatLich/DatLich.php");
                exit();
            } else {
                $thong_bao = "❌ Lỗi đăng ký: " . $conn->error;
            }
        }
    }
}

// 9. XỬ LÝ ĐĂNG NHẬP
if ($_SERVER["REQUEST_METHOD"] == "POST" AND isset($_POST['btn_dang_nhap'])) {
    $tai_khoan_input = $conn->real_escape_string(trim($_POST['email']));
    $mat_khau        = $conn->real_escape_string(trim($_POST['password']));

    $sql_login = "SELECT * FROM tai_khoan WHERE (email = '$tai_khoan_input' OR so_dien_thoai = '$tai_khoan_input') AND mat_khau = '$mat_khau'";
    $res = $conn->query($sql_login);

    if ($res AND $res->num_rows > 0) {
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
    <title>Đăng Nhập & Xác Thực SĐT Chính Chủ - CMTC Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
    <style>
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

    .sms-toast-simulator {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 370px;
        background: #111;
        color: #fff;
        border-left: 5px solid #e6b96f;
        border-radius: 12px;
        padding: 15px 18px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
    }
    </style>
</head>

<body class="bg-cream">

    <!-- Hộp thông báo SMS đến (hỗ trợ lấy mã OTP trực tiếp nếu chưa nạp tiền SMS API) -->
    <div id="smsSimulatorToast" class="sms-toast-simulator d-none">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-chat-dots-fill me-1"></i>TIN NHẮN SMS
                MỚI</span>
            <button type="button" class="btn-close btn-close-white btn-sm"
                onclick="document.getElementById('smsSimulatorToast').classList.add('d-none')"></button>
        </div>
        <div id="smsSimulatorContent" class="small text-light"></div>
    </div>

    <main class="login-wrapper py-5">
        <div class="container">
            <div class="row justify-content-center g-4">
                <div class="col-lg-6 col-xl-5">
                    <div class="login-card mx-auto">
                        <div class="text-center mb-2">
                            <img src="../images/logo.png"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/3504/3504018.png'"
                                alt="CMTC Salon Logo" class="img-fluid rounded-circle shadow-sm"
                                style="width: 85px; height: 85px; border: 3px solid #0b4a4d; object-fit: cover; padding: 2px; background: #fff;" />
                        </div>

                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <h2 class="text-center fw-bold text-dark mb-2">Hệ Thống CMTC Salon</h2>
                        <p class="text-center text-muted mb-4 small">
                            Vui lòng xác thực SĐT chính chủ bằng mã OTP 6 số gửi qua SMS khi đăng ký
                        </p>

                        <?php if ($thong_bao !== ""): ?>
                        <div class="alert alert-<?= $loai_tb ?> text-center fw-semibold small"><?= $thong_bao ?></div>
                        <?php endif; ?>
                        <div id="alertBox" class="alert d-none small"></div>

                        <ul class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-3" id="authTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !$mo_tab_dang_ky ? 'active' : '' ?> rounded-3"
                                    id="login-tab" data-bs-toggle="pill" data-bs-target="#tabLogin" type="button">Đăng
                                    Nhập</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $mo_tab_dang_ky ? 'active' : '' ?> rounded-3"
                                    id="register-tab" data-bs-toggle="pill" data-bs-target="#tabRegister"
                                    type="button">Đăng Ký Mới</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- TAB 1: ĐĂNG NHẬP -->
                            <div class="tab-pane fade <?= !$mo_tab_dang_ky ? 'show active' : '' ?>" id="tabLogin">
                                <form id="loginForm" method="POST" action="login.php">
                                    <input type="hidden" name="btn_dang_nhap" value="1" />
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-muted small">Email hoặc Số điện
                                            thoại</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i
                                                    class="fa-regular fa-envelope text-muted"></i></span>
                                            <input type="text" id="email" name="email"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="Nhập Gmail hoặc SĐT..." required />
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label fw-semibold text-muted small m-0">Mật khẩu</label>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal"
                                                class="text-decoration-none small text-dark-teal fw-semibold">Quên mật
                                                khẩu?</a>
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i
                                                    class="fa-solid fa-lock text-muted"></i></span>
                                            <input type="password" id="password" name="password"
                                                class="form-control border-start-0 border-end-0 ps-0"
                                                placeholder="Nhập mật khẩu" required />
                                            <span class="input-group-text bg-white border-start-0" id="togglePassword"
                                                style="cursor: pointer">
                                                <i class="fa-regular fa-eye-slash text-muted"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <button type="submit"
                                        class="btn btn-dark-teal w-100 mb-3 d-flex justify-content-center align-items-center"
                                        id="loginBtn">
                                        Đăng Nhập & Đến Trang Đặt Lịch <i
                                            class="fa-solid fa-arrow-right ms-2 small"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- TAB 2: ĐĂNG KÝ TÀI KHOẢN (CÓ XÁC THỰC OTP 6 SỐ) -->
                            <div class="tab-pane fade <?= $mo_tab_dang_ky ? 'show active' : '' ?>" id="tabRegister">
                                <form id="formDangKyOTP" method="POST" action="login.php"
                                    onsubmit="return kiemTraFormDangKyOTP()">
                                    <input type="hidden" name="btn_dang_ky" value="1" />
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-muted small">Họ và Tên khách
                                            hàng</label>
                                        <input type="text" name="ho_ten" id="regHoTen" class="form-control"
                                            value="<?= htmlspecialchars($_POST['ho_ten'] ?? '') ?>"
                                            placeholder="VD: Nguyễn Văn A" required />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-muted small">Địa chỉ Gmail (Cố
                                            định)</label>
                                        <input type="email" name="email" id="regEmail" class="form-control"
                                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                            placeholder="VD: khachhang@gmail.com" required />
                                    </div>

                                    <!-- Ô NHẬP SỐ ĐIỆN THOẠI + NÚT GỬI MÃ OTP -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-muted small">Số điện thoại chính chủ
                                            (10 số)</label>
                                        <div class="input-group">
                                            <input type="tel" name="so_dien_thoai" id="regSoDienThoai"
                                                class="form-control fw-bold"
                                                value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>"
                                                placeholder="VD: 0971714681" maxlength="10"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, ''); kiemTraHienThiSDT();"
                                                required />
                                            <button type="button" id="btnGuiMaOTP" class="btn btn-warning fw-bold px-3"
                                                onclick="guiMaOTPSMS()">
                                                <i class="bi bi-send-check-fill me-1"></i>Gửi mã OTP
                                            </button>
                                        </div>
                                        <small id="trangThaiSDT" class="d-block mt-1 text-muted"
                                            style="font-size: 12px;">
                                            Định dạng chuẩn: 10 số bắt đầu bằng 03, 05, 07, 08, 09.
                                        </small>
                                    </div>

                                    <!-- Ô NHẬP MÃ OTP 6 SỐ -->
                                    <div class="mb-3 p-3 rounded-3 border bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label fw-bold text-dark small mb-0">
                                                <i class="bi bi-shield-lock-fill text-success me-1"></i>MÃ XÁC THỰC SMS
                                                OTP (6 SỐ)
                                            </label>
                                            <span id="boDemNguocOTP" class="badge bg-secondary"
                                                style="font-size: 11px;">Chưa lấy mã</span>
                                        </div>
                                        <input type="text" name="ma_otp_sms" id="regMaOTP"
                                            class="form-control otp-input-box" placeholder="Nhập 6 số OTP" maxlength="6"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
                                        <small id="thongBaoKetQuaOTP" class="d-block mt-1 small text-muted">
                                            Nhập đúng 6 số gửi về SĐT mới được phép tạo tài khoản.
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-muted small">Mật khẩu</label>
                                        <input type="password" name="mat_khau" class="form-control"
                                            placeholder="Tạo mật khẩu (tối thiểu 6 ký tự)..." minlength="6" required />
                                    </div>
                                    <button type="submit" class="btn btn-dark-teal w-100 mb-3">
                                        <i class="fa-solid fa-user-check me-2"></i>Xác Thực OTP & Đăng Ký Ngay
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="divider text-center my-3">
                            <span class="bg-white px-3 small text-muted fw-semibold">HOẶC ĐĂNG NHẬP BẰNG GMAIL</span>
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-12">
                                <button type="button" id="btnGoogleLogin"
                                    class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center text-dark py-2 rounded-3">
                                    <img src="https://img.icons8.com/color/22/google-logo.png" class="me-2"
                                        alt="Google" />
                                    <span class="small fw-bold">Đăng nhập nhanh bằng tài khoản Google (Gmail)</span>
                                </button>
                            </div>
                        </div>

                        <?php else: ?>
                        <!-- GIAO DIỆN KHI ĐÃ ĐĂNG NHẬP -->
                        <h4 class="text-center fw-bold text-dark mb-3">Tài Khoản Của Quý Khách</h4>
                        <?php if ($thong_bao !== ""): ?>
                        <div class="alert alert-<?= $loai_tb ?> text-center fw-semibold small py-2"><?= $thong_bao ?>
                        </div>
                        <?php endif; ?>
                        <form method="POST" action="login.php" class="mb-3 p-3 bg-light rounded-3 border">
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-dark mb-1">Họ và Tên (Có thể chỉnh
                                    sửa):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="ho_ten_moi" class="form-control fw-bold"
                                        value="<?= htmlspecialchars($_SESSION['ho_ten']) ?>" required>
                                    <button type="submit" name="btn_cap_nhat_ten_khach"
                                        class="btn btn-warning fw-bold">Lưu tên</button>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-muted mb-1">Email (Cố định):</label>
                                <input type="email"
                                    class="form-control form-control-sm bg-secondary bg-opacity-10 text-dark fw-bold"
                                    value="<?= htmlspecialchars($_SESSION['email']) ?>" readonly disabled>
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-semibold text-muted mb-1">Số điện thoại (Cố
                                    định):</label>
                                <input type="text"
                                    class="form-control form-control-sm bg-secondary bg-opacity-10 text-dark fw-bold"
                                    value="<?= htmlspecialchars($_SESSION['so_dien_thoai']) ?>" readonly disabled>
                            </div>
                        </form>

                        <a href="../DatLich/DatLich.php" class="btn btn-dark-teal w-100 mb-3 py-3 fs-6">
                            <i class="bi bi-calendar2-check me-2"></i>ĐẾN TRANG ĐẶT LỊCH CẮT TÓC NGAY
                        </a>

                        <?php if ($_SESSION['vai_tro'] == 'admin'): ?>
                        <a href="admin.php" class="btn btn-warning w-100 fw-bold mb-3">
                            <i class="bi bi-shield-lock-fill me-2"></i>VÀO BẢNG ĐIỀN KHIỂN ADMIN
                        </a>
                        <?php endif; ?>

                        <a href="login.php?logout=1" class="btn btn-outline-danger w-100 btn-sm">Đăng xuất tài khoản</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL QUÊN MẬT KHẨU -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Khôi phục mật khẩu qua Gmail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Nhập địa chỉ Gmail bạn đã đăng ký. Hệ thống sẽ gửi link đặt lại mật
                        khẩu mới.</p>
                    <div id="resetAlert" class="alert d-none small py-2"></div>
                    <input type="email" id="resetEmailInput" class="form-control mb-3"
                        placeholder="Nhập Gmail của bạn..." />
                    <button id="btnSendReset" class="btn btn-dark-teal w-100 fw-semibold">Gửi Email Khôi Phục</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let otpTimerInterval = null;

    function laSoDienThoaiVN(sdt) {
        if (sdt.length !== 10) return false;
        if (!/^[0-9]{10}$/.test(sdt)) return false;
        const dauSo = sdt.substring(0, 2);
        const dsDauSo = ['03', '05', '07', '08', '09'];
        return dsDauSo.includes(dauSo);
    }

    function kiemTraHienThiSDT() {
        const sdtInput = document.getElementById('regSoDienThoai');
        const trangThaiEl = document.getElementById('trangThaiSDT');
        if (!sdtInput) return false;
        if (!trangThaiEl) return false;

        const val = sdtInput.value.trim();
        if (val.length === 0) {
            trangThaiEl.className = 'd-block mt-1 text-muted';
            trangThaiEl.innerText = 'Định dạng chuẩn: 10 số bắt đầu bằng 03, 05, 07, 08, 09.';
            return false;
        }
        if (laSoDienThoaiVN(val)) {
            trangThaiEl.className = 'd-block mt-1 text-success fw-semibold';
            trangThaiEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>SĐT hợp lệ! Hãy bấm nút "Gửi mã OTP".';
            return true;
        } else {
            trangThaiEl.className = 'd-block mt-1 text-danger fw-semibold';
            trangThaiEl.innerHTML =
                '<i class="bi bi-exclamation-triangle-fill me-1"></i>SĐT phải gồm 10 chữ số (đầu 03, 05, 07, 08, 09).';
            return false;
        }
    }

    function batDauDemNguocOTP(giay) {
        const btn = document.getElementById('btnGuiMaOTP');
        const badge = document.getElementById('boDemNguocOTP');
        let conLai = giay;

        btn.disabled = true;
        if (otpTimerInterval) clearInterval(otpTimerInterval);

        otpTimerInterval = setInterval(function() {
            conLai--;
            btn.innerHTML = 'Gửi lại (' + conLai + 's)';
            badge.className = 'badge bg-success';
            badge.innerText = 'Đã gửi mã';

            if (conLai <= 0) {
                clearInterval(otpTimerInterval);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Gửi lại OTP';
            }
        }, 1000);
    }

    function tuDongDienMaOTP(ma6So) {
        const otpInput = document.getElementById('regMaOTP');
        if (otpInput) {
            otpInput.value = ma6So;
        }
    }

    function guiMaOTPSMS() {
        const sdtInput = document.getElementById('regSoDienThoai');
        const emailInput = document.getElementById('regEmail');
        const thongBaoOTP = document.getElementById('thongBaoKetQuaOTP');
        const btnGui = document.getElementById('btnGuiMaOTP');

        const sdt = sdtInput.value.trim();
        const email = emailInput ? emailInput.value.trim() : '';

        if (!laSoDienThoaiVN(sdt)) {
            alert(
                '⚠️ Số điện thoại không đúng định dạng! Vui lòng nhập SĐT chính chủ gồm 10 số (đầu 03, 05, 07, 08, 09).');
            sdtInput.focus();
            return;
        }

        btnGui.disabled = true;
        btnGui.innerHTML = 'Đang gửi...';

        const formData = new FormData();
        formData.append('action', 'send_sms_otp');
        formData.append('so_dien_thoai', sdt);
        formData.append('email', email);

        fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                if (data.status === 'success') {
                    thongBaoOTP.className = 'd-block mt-1 small text-success fw-bold';
                    thongBaoOTP.innerHTML = data.message;
                    batDauDemNguocOTP(60);
                    document.getElementById('regMaOTP').focus();

                    if (data.is_sandbox) {
                        const toastEl = document.getElementById('smsSimulatorToast');
                        const contentEl = document.getElementById('smsSimulatorContent');
                        contentEl.innerHTML = 'Tới SĐT: <strong>' + sdt + '</strong><br>' +
                            '[CMTC SALON] Mã xác thực OTP của bạn là: ' +
                            '<span class="badge bg-warning text-dark fs-6 fw-bold mx-1">' + data.sandbox_otp +
                            '</span>' +
                            '<button type="button" class="btn btn-sm btn-outline-warning py-0 px-2 ms-1" onclick="tuDongDienMaOTP(\'' +
                            data.sandbox_otp + '\')">Điền nhanh</button>';
                        toastEl.classList.remove('d-none');
                    }
                } else {
                    btnGui.disabled = false;
                    btnGui.innerHTML = '<i class="bi bi-send-check-fill me-1"></i>Gửi mã OTP';
                    thongBaoOTP.className = 'd-block mt-1 small text-danger fw-bold';
                    thongBaoOTP.innerHTML = data.message;
                    alert(data.message);
                }
            })
            .catch(function() {
                btnGui.disabled = false;
                btnGui.innerHTML = '<i class="bi bi-send-check-fill me-1"></i>Gửi mã OTP';
                alert('❌ Không thể kết nối máy chủ khi gửi OTP!');
            });
    }

    function kiemTraFormDangKyOTP() {
        const sdt = document.getElementById('regSoDienThoai').value.trim();
        const otp = document.getElementById('regMaOTP').value.trim();

        if (!laSoDienThoaiVN(sdt)) {
            alert('❌ Số điện thoại không đúng định dạng 10 số Việt Nam!');
            return false;
        }
        if (!/^[0-9]{6}$/.test(otp)) {
            alert('❌ Vui lòng nhập đúng mã OTP gồm 6 chữ số!');
            document.getElementById('regMaOTP').focus();
            return false;
        }
        return true;
    }
    </script>
    <script type="module" src="auth.js"></script>
</body>

</html>
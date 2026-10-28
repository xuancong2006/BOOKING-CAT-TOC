<?php
ob_start();
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. KẾT NỐI CSDL TỰ ĐỘNG NHẬN DIỆN XAMPP & INFINITYFREE
if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $conn = new mysqli("localhost", "root", "", "cmtc_salon");
} else {
    $conn = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
}
if ($conn && !$conn->connect_error) {
    $conn->set_charset("utf8mb4");
}

// 2. CẤU HÌNH GMAIL HỖ TRỢ & WEBHOOK GOOGLE APPS SCRIPT
$GMAIL_SUPPORT_SALON       = "xuancongsupport@gmail.com";
$GOOGLE_SCRIPT_WEBHOOK_URL = "https://script.google.com/macros/s/AKfycbyHJ2O3wird81Rq1gzYSJniL4QyDrubNjWe4lgrax8aFlEsprvhnODZWh-dzJNkBYtlPA/exec";

$file_xldl = __DIR__ . "/../DatLich/xu_ly_dat_lich.php";
if (file_exists($file_xldl)) {
    $xldl_content = @file_get_contents($file_xldl);
    if ($xldl_content && preg_match('/\$GOOGLE_SCRIPT_WEBHOOK_URL\s*=\s*"([^"]+)"/', $xldl_content, $m_url)) {
        if (!empty(trim($m_url[1]))) {
            $GOOGLE_SCRIPT_WEBHOOK_URL = trim($m_url[1]);
        }
    }
}

// Hàm trả về JSON sạch tuyệt đối
function traVeJsonSach($arr) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit();
}

// Hàm kiểm tra mật khẩu mạnh chuẩn 5 tiêu chí như trong Video (8 Chars, A-Z, a-z, 123, @#$)
function kiemTraMatKhauManh($mk) {
    $loi = array();
    if (strlen($mk) < 8) {
        $loi[] = "tối thiểu 8 ký tự (8 Chars)";
    }
    if (!preg_match('/[A-Z]/', $mk)) {
        $loi[] = "chữ cái IN HOA (A-Z)";
    }
    if (!preg_match('/[a-z]/', $mk)) {
        $loi[] = "chữ cái thường (a-z)";
    }
    if (!preg_match('/[0-9]/', $mk)) {
        $loi[] = "chữ số (123)";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $mk)) {
        $loi[] = "ký tự đặc biệt (@#$...)";
    }
    if (!empty($loi)) {
        return array(
            'hop_le'    => false,
            'thong_bao' => 'Mật khẩu chưa đủ mạnh! Bắt buộc phải có thêm: ' . implode(', ', $loi) . '.'
        );
    }
    return array('hop_le' => true, 'thong_bao' => 'Mật khẩu mạnh hợp lệ.');
}

// Hàm tạo ngẫu nhiên 1 mật khẩu đạt chuẩn Mạnh (đầy đủ Hoa, thường, số, ký tự đặc biệt, >= 8 ký tự)
function taoMatKhauManhNgauNhien() {
    $hoa  = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    $thg  = "abcdefghijkmnopqrstuvwxyz";
    $so   = "23456789";
    $db   = "@#$%&*!";
    $mk   = "Cmtc"
          . $db[mt_rand(0, strlen($db) - 1)]
          . $so[mt_rand(0, strlen($so) - 1)]
          . $so[mt_rand(0, strlen($so) - 1)]
          . $so[mt_rand(0, strlen($so) - 1)]
          . $hoa[mt_rand(0, strlen($hoa) - 1)]
          . $thg[mt_rand(0, strlen($thg) - 1)];
    return $mk;
}

// Hàm tự động lấy URL chuẩn của trang hiện tại (Chạy đúng 100% trên cả Localhost XAMPP & Hosting InfinityFree/Cloudflare)
function layUrlHienTaiChuan() {
    $is_https = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $is_https = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $is_https = true;
    } elseif (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) {
        $is_https = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $is_https = true;
    }
    $protocol = $is_https ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_path = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : strtok($_SERVER['REQUEST_URI'], '?');
    return $protocol . "://" . $host . $script_path;
}

// Kiểm tra và chuẩn hóa Số điện thoại quốc tế & Việt Nam
function chuanHoaSoDienThoai($ma_quoc_gia, $sdt_nhap) {
    $ma_quoc_gia = trim($ma_quoc_gia);
    $chi_so = preg_replace('/[^0-9]/', '', trim($sdt_nhap));
    if ($chi_so === '') {
        return array('hop_le' => false, 'sdt_luu' => '', 'thong_bao' => 'Vui lòng nhập số điện thoại!');
    }

    if ($ma_quoc_gia === '+84' || $ma_quoc_gia === '84') {
        if (strpos($chi_so, '84') === 0 && strlen($chi_so) === 11) {
            $chi_so = '0' . substr($chi_so, 2);
        } elseif (strpos($chi_so, '0') !== 0 && strlen($chi_so) === 9) {
            $chi_so = '0' . $chi_so;
        }
        if (!preg_match('/^0(3|5|7|8|9)[0-9]{8}$/', $chi_so)) {
            return array(
                'hop_le' => false,
                'sdt_luu' => '',
                'thong_bao' => 'Số điện thoại Việt Nam (+84) phải gồm 10 số (bắt đầu bằng 03, 05, 07, 08, 09 hoặc bỏ số 0 đầu)!'
            );
        }
        return array('hop_le' => true, 'sdt_luu' => $chi_so, 'sdt_hien_thi' => '+84 ' . substr($chi_so, 1));
    }

    $chi_so_khong_0 = ltrim($chi_so, '0');
    if (strlen($chi_so_khong_0) < 6 || strlen($chi_so_khong_0) > 14) {
        return array(
            'hop_le' => false,
            'sdt_luu' => '',
            'thong_bao' => "Số điện thoại quốc tế ($ma_quoc_gia) không hợp lệ (cần từ 6 đến 14 chữ số)!"
        );
    }
    $sdt_quoc_te = $ma_quoc_gia . $chi_so_khong_0;
    return array('hop_le' => true, 'sdt_luu' => $sdt_quoc_te, 'sdt_hien_thi' => $ma_quoc_gia . ' ' . $chi_so_khong_0);
}

function laGmailHopLe($email) {
    $email = strtolower(trim($email));
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@') !== false;
}

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
        @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = @curl_exec($ch);
        $http_code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($http_code >= 200 && $http_code < 400) {
            return true;
        }
    }
    return false;
}

function guiMaOTPVeGmailChinhChu($ho_ten, $sdt, $email_nhan, $ma_otp, $webhook_url, $gmail_support) {
    $thoi_gian = date('H:i:s - d/m/Y');
    $tieu_de = "🔐 [CMCT SALON] Mã xác thực OTP đăng ký tài khoản của bạn là: $ma_otp";
    $noi_dung_html = "
    <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
        <div style='background-color: #0b4a4d; color: #e6b96f; padding: 22px; text-align: center; border-bottom: 3px solid #c09e6c;'>
            <h2 style='margin: 0; text-transform: uppercase; font-size: 20px;'>MÃ XÁC THỰC ĐĂNG KÝ TÀI KHOẢN</h2>
            <p style='color: #fff; margin: 5px 0 0; font-size: 13px;'>CMCT Salon - Hệ Thống Đặt Lịch</p>
        </div>
        <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65; text-align: center;'>
            <p style='margin-top: 0;'>Xin chào <strong>$ho_ten</strong>,</p>
            <p>Hệ thống <strong>CMCT Salon</strong> nhận được yêu cầu đăng ký tài khoản cho số điện thoại <strong>$sdt</strong> và địa chỉ Gmail <strong>$email_nhan</strong>.</p>
            <p style='margin-bottom: 8px; font-weight: bold; color: #0b4a4d;'>Mã xác thực OTP gồm 6 chữ số của bạn là:</p>
            <div style='display: inline-block; background: #f0fdf4; border: 2px dashed #198754; color: #166534; font-size: 30px; font-weight: 800; letter-spacing: 8px; padding: 12px 30px; border-radius: 12px; margin: 10px 0;'>
                $ma_otp
            </div>
            <p style='color: #dc3545; font-size: 13px; font-weight: bold; margin-top: 12px;'>
                ⏳ Mã OTP này có hiệu lực trong vòng 5 phút (Phát hành lúc: $thoi_gian).
            </p>
            <p style='color: #666; font-size: 13px; margin-bottom: 0;'>
                Vui lòng nhập chính xác 6 chữ số này vào màn hình xác thực trên trang Đăng ký CMCT Salon để hoàn tất tạo tài khoản.
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

// Hàm gửi mật khẩu mạnh mới + Đường link đổi mật khẩu về Gmail khi khách bấm "Quên mật khẩu"
function taoVaGuiMailQuenMatKhau($ho_ten, $email_khach, $mat_khau_moi, $link_doi_mk, $webhook_url, $gmail_support) {
    $thoi_gian = date('H:i:s - d/m/Y');
    $tieu_de = "🔑 [CMCT SALON] Cấp mật khẩu mới & Đường dẫn đổi mật khẩu bảo mật";
    $noi_dung_html = "
    <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
        <div style='background-color: #0b4a4d; color: #e6b96f; padding: 22px; text-align: center; border-bottom: 3px solid #c09e6c;'>
            <h2 style='margin: 0; text-transform: uppercase; font-size: 20px;'>KHÔI PHỤC MẬT KHẨU TÀI KHOẢN</h2>
            <p style='color: #fff; margin: 5px 0 0; font-size: 13px;'>CMCT Salon - Hỗ Trợ Bảo Mật Khách Hàng</p>
        </div>
        <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65;'>
            <p style='margin-top: 0;'>Xin chào <strong>$ho_ten</strong>,</p>
            <p>Hệ thống <strong>CMCT Salon</strong> vừa nhận được yêu cầu khôi phục mật khẩu cho tài khoản Gmail <strong>$email_khach</strong> lúc $thoi_gian.</p>
            <div style='background: #f0fdf4; border: 2px dashed #198754; border-radius: 12px; padding: 16px; text-align: center; margin: 18px 0;'>
                <div style='font-size: 13px; color: #555; margin-bottom: 6px;'>Mật khẩu mạnh mới hệ thống cấp tự động cho bạn là:</div>
                <div style='font-size: 26px; font-weight: 800; color: #166534; letter-spacing: 2px;'>$mat_khau_moi</div>
            </div>
            <p>Bạn có thể dùng ngay mật khẩu mới ở trên để đăng nhập, hoặc nhấn vào nút bên dưới để tự đặt lại mật khẩu mạnh theo ý muốn (Link có hiệu lực trong 30 phút):</p>
            <div style='text-align: center; margin: 22px 0;'>
                <a href='$link_doi_mk' style='background-color: #0b4a4d; color: #e6b96f; text-decoration: none; padding: 13px 28px; border-radius: 10px; font-weight: bold; display: inline-block;'>
                    🔗 BẤM VÀO ĐÂY ĐỂ ĐỔI MẬT KHẨU MỚI
                </a>
            </div>
            <p style='font-size: 12px; color: #777; margin-bottom: 0; word-break: break-all;'>Đường dẫn trực tiếp: <a href='$link_doi_mk'>$link_doi_mk</a></p>
        </div>
        <div style='background-color: #111; color: #e6b96f; padding: 12px; text-align: center; font-size: 12px;'>
            Email được gửi tự động từ bộ phận hỗ trợ CMCT Salon ($gmail_support).
        </div>
    </div>";

    $payload_arr = array(
        'to_email'    => $email_khach,
        'admin_email' => "",
        'subject'     => $tieu_de,
        'html_body'   => $noi_dung_html
    );

    $da_gui = goiGoogleScriptWebhook($webhook_url, $payload_arr);
    return array('sent_by_php' => $da_gui, 'payload' => $payload_arr);
}

// Khởi tạo và nâng cấp bảng tai_khoan tự động
if ($conn && !$conn->connect_error) {
    $conn->query("CREATE TABLE IF NOT EXISTS `tai_khoan` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ho_ten` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL UNIQUE,
      `so_dien_thoai` varchar(25) NOT NULL,
      `mat_khau` varchar(255) NOT NULL,
      `gioi_tinh` varchar(20) DEFAULT 'Nam',
      `ngay_sinh` date DEFAULT NULL,
      `dia_chi` varchar(255) DEFAULT NULL,
      `avatar_icon` varchar(60) DEFAULT 'bi-person-bounding-box',
      `vai_tro` varchar(20) DEFAULT 'khach',
      `trang_thai_duyet` tinyint(1) DEFAULT 1,
      `cap_nhat_mk_luc` datetime DEFAULT NULL,
      `ngay_tao` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `gioi_tinh` varchar(20) DEFAULT 'Nam';");
    $conn->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `ngay_sinh` date DEFAULT NULL;");
    $conn->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `dia_chi` varchar(255) DEFAULT NULL;");
    $conn->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `avatar_icon` varchar(60) DEFAULT 'bi-person-bounding-box';");
    $conn->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `cap_nhat_mk_luc` datetime DEFAULT NULL;");

    $conn->query("INSERT IGNORE INTO `tai_khoan` (`id`, `ho_ten`, `email`, `so_dien_thoai`, `mat_khau`, `vai_tro`, `trang_thai_duyet`) VALUES
    (1, 'Vừ Xuân Công (Admin)', 'xuanconggaming@gmail.com', '0971714681', '123456', 'admin', 1);");
}

$thong_bao = "";
$loai_tb = "danger";
$mo_tab_dang_ky = false;

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// =========================================================================================
// 3. XỬ LÝ QUÊN MẬT KHẨU QUA AJAX (CẤP MẬT KHẨU MẠNH MỚI + GỬI LINK ĐỔI MẬT KHẨU VỀ GMAIL)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'forgot_password') {
    $email_fp = strtolower(trim($_POST['email_quen_mk'] ?? ''));
    if (!laGmailHopLe($email_fp)) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Vui lòng nhập đúng định dạng địa chỉ Gmail!'));
    }

    $email_esc = $conn->real_escape_string($email_fp);
    $res_u = $conn->query("SELECT id, ho_ten, email FROM tai_khoan WHERE LOWER(email) = '$email_esc' LIMIT 1");
    if (!$res_u || $res_u->num_rows == 0) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Địa chỉ Gmail này chưa được đăng ký trên hệ thống CMTC Salon!'));
    }

    $user_row = $res_u->fetch_assoc();
    $uid_fp   = (int)$user_row['id'];
    $ten_fp   = $user_row['ho_ten'];

    // Cấp 1 mật khẩu mới ngẫu nhiên chuẩn MẠNH (đủ chữ Hoa, chữ thường, số, ký tự đặc biệt) và cập nhật vào CSDL
    $mat_khau_moi = taoMatKhauManhNgauNhien();
    $mk_esc = $conn->real_escape_string($mat_khau_moi);
    $now_sql = date('Y-m-d H:i:s');
    $conn->query("UPDATE tai_khoan SET mat_khau = '$mk_esc', cap_nhat_mk_luc = '$now_sql' WHERE id = $uid_fp");

    // Tạo đường dẫn đổi mật khẩu trực tiếp có chữ ký bảo mật (tự động nhận diện cả Localhost & Hosting)
    $exp_time = time() + 1800; // Hiệu lực 30 phút
    $sig = substr(hash_hmac('sha256', $email_fp . '|' . $exp_time, 'CMTC_SALON_SECRET_KEY_2026'), 0, 20);
    $base_login_url = layUrlHienTaiChuan();
    $link_doi_mk = $base_login_url . "?reset_email=" . urlencode($email_fp) . "&exp=" . $exp_time . "&sig=" . $sig;

    $kq_fp = taoVaGuiMailQuenMatKhau($ten_fp, $email_fp, $mat_khau_moi, $link_doi_mk, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);

    traVeJsonSach(array(
        'status'          => 'success',
        'msg'             => "✅ Đã gửi mật khẩu mới và đường link đổi mật khẩu về hộp thư Gmail ($email_fp)! Vui lòng kiểm tra hộp thư đến hoặc thư rác.",
        'webhook_url'     => $GOOGLE_SCRIPT_WEBHOOK_URL,
        'webhook_payload' => base64_encode(json_encode($kq_fp['payload'], JSON_UNESCAPED_UNICODE))
    ));
}

// Xử lý khi khách bấm vào đường link đổi mật khẩu từ trong Gmail
$hien_form_dat_lai_mk = false;
$email_dang_reset = "";
$exp_dang_reset   = 0;
$sig_dang_reset   = "";

if (isset($_GET['reset_email'], $_GET['exp'], $_GET['sig'])) {
    $em_r  = strtolower(trim($_GET['reset_email']));
    $exp_r = (int)$_GET['exp'];
    $sig_r = trim($_GET['sig']);
    $expected_sig = substr(hash_hmac('sha256', $em_r . '|' . $exp_r, 'CMTC_SALON_SECRET_KEY_2026'), 0, 20);
    if (time() <= $exp_r && hash_equals($expected_sig, $sig_r)) {
        $hien_form_dat_lai_mk = true;
        $email_dang_reset = $em_r;
        $exp_dang_reset   = $exp_r;
        $sig_dang_reset   = $sig_r;
    } else {
        $thong_bao = "❌ Đường dẫn đổi mật khẩu không hợp lệ hoặc đã hết hạn (quá 30 phút)!";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_luu_mk_moi_reset'])) {
    $em_r     = strtolower(trim($_POST['reset_email'] ?? ''));
    $exp_r    = (int)($_POST['exp'] ?? 0);
    $sig_r    = trim($_POST['sig'] ?? '');
    $mk_m     = trim($_POST['mat_khau_moi_reset'] ?? '');
    $mk_m_cf  = trim($_POST['xac_nhan_mk_reset'] ?? '');
    $expected_sig = substr(hash_hmac('sha256', $em_r . '|' . $exp_r, 'CMTC_SALON_SECRET_KEY_2026'), 0, 20);

    if (time() > $exp_r || !hash_equals($expected_sig, $sig_r)) {
        $thong_bao = "❌ Đường dẫn đổi mật khẩu đã hết hạn (quá 30 phút)! Vui lòng bấm Quên mật khẩu để lấy link mới.";
        $hien_form_dat_lai_mk = false;
    } else {
        $hien_form_dat_lai_mk = true;
        $email_dang_reset = $em_r;
        $exp_dang_reset   = $exp_r;
        $sig_dang_reset   = $sig_r;

        $check_mk = kiemTraMatKhauManh($mk_m);
        if (!$check_mk['hop_le']) {
            $thong_bao = "❌ " . $check_mk['thong_bao'];
        } elseif ($mk_m !== $mk_m_cf) {
            $thong_bao = "❌ Mật khẩu xác nhận nhập lại không trùng khớp!";
        } else {
            $em_esc  = $conn->real_escape_string($em_r);
            $mk_esc  = $conn->real_escape_string($mk_m);
            $now_sql = date('Y-m-d H:i:s');
            $conn->query("UPDATE tai_khoan SET mat_khau = '$mk_esc', cap_nhat_mk_luc = '$now_sql' WHERE LOWER(email) = '$em_esc'");
            $loai_tb = "success";
            $thong_bao = "✅ Đổi mật khẩu mạnh mới thành công! Bây giờ bạn có thể đăng nhập bằng mật khẩu vừa tạo.";
            $hien_form_dat_lai_mk = false;
        }
    }
}

// =========================================================================================
// 4. BƯỚC 1 ĐĂNG KÝ: KIỂM TRA MẬT KHẨU MẠNH 5 TIÊU CHÍ & BẬT MÀN HÌNH NHẬP OTP
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'init_register_otp') {
    $ho_ten_raw   = trim($_POST['ho_ten'] ?? '');
    $email_raw    = strtolower(trim($_POST['email'] ?? ''));
    $ma_quoc_gia  = trim($_POST['ma_quoc_gia'] ?? '+84');
    $sdt_raw      = trim($_POST['so_dien_thoai'] ?? '');
    $mat_khau_raw = trim($_POST['mat_khau'] ?? '');

    if ($ho_ten_raw === '') {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Vui lòng nhập Họ và Tên khách hàng!'));
    }
    if (!laGmailHopLe($email_raw)) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Vui lòng nhập đúng địa chỉ Gmail chính chủ!'));
    }

    // Kiểm tra bắt buộc mật khẩu mạnh (8 Chars, A-Z, a-z, 123, @#$)
    $check_mk_dk = kiemTraMatKhauManh($mat_khau_raw);
    if (!$check_mk_dk['hop_le']) {
        traVeJsonSach(array('status' => 'error', 'msg' => $check_mk_dk['thong_bao']));
    }

    $kq_sdt = chuanHoaSoDienThoai($ma_quoc_gia, $sdt_raw);
    if (!$kq_sdt['hop_le']) {
        traVeJsonSach(array('status' => 'error', 'msg' => $kq_sdt['thong_bao']));
    }
    $sdt_chuan = $kq_sdt['sdt_luu'];

    $sdt_esc   = $conn->real_escape_string($sdt_chuan);
    $email_esc = $conn->real_escape_string($email_raw);
    $check_sql = "SELECT so_dien_thoai, email FROM tai_khoan WHERE so_dien_thoai = '$sdt_esc' OR LOWER(email) = '$email_esc' LIMIT 1";
    $res = $conn->query($check_sql);

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (strtolower($row['email']) === $email_raw) {
            traVeJsonSach(array('status' => 'error', 'msg' => 'Địa chỉ Gmail này đã được đăng ký tài khoản trên hệ thống!'));
        } else {
            traVeJsonSach(array('status' => 'error', 'msg' => 'Số điện thoại này đã được đăng ký tài khoản trên hệ thống!'));
        }
    }

    $ma_otp = str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['pending_reg'] = array(
        'ho_ten'        => $ho_ten_raw,
        'email'         => $email_raw,
        'so_dien_thoai' => $sdt_chuan,
        'mat_khau'      => $mat_khau_raw,
        'otp_code'      => $ma_otp,
        'expire'        => time() + 300
    );

    $kq_mail = guiMaOTPVeGmailChinhChu($ho_ten_raw, $sdt_chuan, $email_raw, $ma_otp, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);

    traVeJsonSach(array(
        'status'          => 'success',
        'email'           => $email_raw,
        'so_dien_thoai'   => $sdt_chuan,
        'msg'             => "Mã xác thực OTP 6 số đã được gửi tới Gmail: $email_raw",
        'webhook_url'     => $GOOGLE_SCRIPT_WEBHOOK_URL,
        'webhook_payload' => base64_encode(json_encode($kq_mail['payload'], JSON_UNESCAPED_UNICODE))
    ));
}

// =========================================================================================
// 5. BƯỚC 2 ĐĂNG KÝ: XÁC THỰC MÃ OTP 6 SỐ & TẠO TÀI KHOẢN
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'verify_register_otp') {
    $otp_nhap = preg_replace('/[^0-9]/', '', trim($_POST['ma_otp'] ?? ''));

    if (!isset($_SESSION['pending_reg']) || !is_array($_SESSION['pending_reg'])) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Phiên đăng ký đã hết hạn. Vui lòng điền lại thông tin!'));
    }

    $pending = $_SESSION['pending_reg'];
    if (time() > $pending['expire']) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Mã OTP đã hết hạn (quá 5 phút). Vui lòng bấm Gửi lại mã!'));
    }

    if ($otp_nhap === '' || $otp_nhap !== $pending['otp_code']) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Mã xác thực OTP 6 số không chính xác! Vui lòng kiểm tra lại Gmail.'));
    }

    $ho_ten_esc = $conn->real_escape_string($pending['ho_ten']);
    $email_esc  = $conn->real_escape_string($pending['email']);
    $sdt_esc    = $conn->real_escape_string($pending['so_dien_thoai']);
    $mk_esc     = $conn->real_escape_string($pending['mat_khau']);
    $now_sql    = date('Y-m-d H:i:s');

    $sql_ins = "INSERT INTO tai_khoan (ho_ten, email, so_dien_thoai, mat_khau, vai_tro, trang_thai_duyet, cap_nhat_mk_luc) 
                VALUES ('$ho_ten_esc', '$email_esc', '$sdt_esc', '$mk_esc', 'khach', 1, '$now_sql')";

    if ($conn->query($sql_ins)) {
        $new_uid = $conn->insert_id;
        $_SESSION['user_id']          = $new_uid;
        $_SESSION['ho_ten']           = $pending['ho_ten'];
        $_SESSION['email']            = $pending['email'];
        $_SESSION['so_dien_thoai']    = $pending['so_dien_thoai'];
        $_SESSION['vai_tro']          = 'khach';
        $_SESSION['trang_thai_duyet'] = 1;

        $payload_welcome = taoVaGuiMailDangKyTaiKhoan(
            $pending['ho_ten'],
            $pending['email'],
            $pending['so_dien_thoai'],
            $GOOGLE_SCRIPT_WEBHOOK_URL,
            $GMAIL_SUPPORT_SALON
        );

        unset($_SESSION['pending_reg']);

        traVeJsonSach(array(
            'status'          => 'success',
            'msg'             => 'Xác thực thành công! Đang chuyển đến trang Đặt lịch...',
            'redirect'        => '../DatLich/DatLich.php',
            'webhook_url'     => $GOOGLE_SCRIPT_WEBHOOK_URL,
            'webhook_payload' => $payload_welcome ? base64_encode(json_encode($payload_welcome, JSON_UNESCAPED_UNICODE)) : ''
        ));
    } else {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Lỗi lưu tài khoản: ' . $conn->error));
    }
}

// 6. XỬ LÝ ĐĂNG NHẬP THỦ CÔNG
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
    <title>Đăng Nhập & Xác Thực Bảo Mật - CMTC Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
    body {
        background-color: #f4f6f9;
        font-family: 'Inter', sans-serif;
    }

    .login-wrapper {
        display: flex;
        align-items: center;
        min-height: 100vh;
    }

    .login-card {
        background: #fff;
        padding: 36px 32px;
        border-radius: 24px;
        box-shadow: 0 14px 40px rgba(0, 0, 0, 0.08);
        width: 100%;
        max-width: 520px;
        margin: auto;
        position: relative;
        overflow: visible;
    }

    .btn-dark-teal {
        background-color: #0b4a4d;
        color: #fff;
        font-weight: 600;
        padding: 12px;
        border-radius: 12px;
        transition: all 0.25s ease;
    }

    .btn-dark-teal:hover {
        background-color: #083739;
        color: #fff;
        transform: translateY(-1px);
    }

    .nav-pills .nav-link.active {
        background-color: #0b4a4d;
    }

    .nav-pills .nav-link {
        color: #0b4a4d;
        font-weight: 600;
    }

    /* ======================================================================= */
    /* BỘ KIỂM TRA ĐỘ MẠNH MẬT KHẨU GIỐNG HỆT VIDEO (CODE BY GAURAV STYLE)     */
    /* ======================================================================= */
    .pw-meter-card {
        background: #ffffff;
        border: 1px solid #eef2f6;
        border-radius: 18px;
        padding: 18px 18px 16px;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
    }

    .pw-input-box {
        position: relative;
        width: 100%;
    }

    .pw-input-box input {
        width: 100%;
        height: 54px;
        padding: 0 46px 0 18px;
        border: 2px solid #cbd5e1;
        border-radius: 14px;
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
        background: #fff;
        outline: none;
        transition: all 0.3s ease;
    }

    .pw-input-box label {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        background: #fff;
        padding: 0 6px;
        font-size: 14px;
        font-weight: 500;
        pointer-events: none;
        transition: 0.25s ease;
    }

    .pw-input-box input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .pw-input-box input:focus+label,
    .pw-input-box input:not(:placeholder-shown)+label {
        top: 0;
        font-size: 12px;
        color: #2563eb;
        font-weight: 700;
    }

    .pw-eye-btn {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 19px;
        color: #94a3b8;
        cursor: pointer;
        user-select: none;
        transition: color 0.2s ease;
    }

    .pw-eye-btn:hover {
        color: #2563eb;
    }

    .pw-strength-box {
        margin-top: 14px;
    }

    .pw-strength-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13.5px;
    }

    .pw-strength-top span:first-child {
        color: #4b5563;
        font-weight: 600;
    }

    .pw-strength-text {
        font-weight: 700;
        transition: color 0.3s ease;
    }

    .pw-strength-text.weak {
        color: #ef4444;
    }

    .pw-strength-text.medium {
        color: #f59e0b;
    }

    .pw-strength-text.strong {
        color: #22c55e;
    }

    .pw-progress {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 30px;
        overflow: hidden;
    }

    .pw-progress-bar {
        width: 0%;
        height: 100%;
        background: #ef4444;
        border-radius: 30px;
        transition: width 0.35s ease, background-color 0.35s ease;
    }

    .pw-rules {
        margin-top: 14px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
    }

    .pw-rule {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12.5px;
        font-weight: 600;
        color: #9ca3af;
        transition: color 0.25s ease;
    }

    .pw-rule .pw-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #d1d5db;
        display: inline-block;
        transition: all 0.25s ease;
    }

    .pw-rule.active {
        color: #2563eb;
    }

    .pw-rule.active .pw-dot {
        background: #2563eb;
        box-shadow: 0 0 8px rgba(37, 99, 235, 0.55);
        transform: scale(1.12);
    }

    /* ======================================================================= */
    /* DROPDOWN ĐẦU SỐ ĐIỆN THOẠI QUỐC TẾ KÈM Ô TÌM KIẾM NHANH                 */
    /* ======================================================================= */
    .country-code-wrapper {
        position: relative;
    }

    .btn-country-selector {
        background-color: #f8fafc;
        border: 1px solid #dee2e6;
        border-right: none;
        border-top-left-radius: 8px;
        border-bottom-left-radius: 8px;
        padding: 8px 12px;
        font-weight: 700;
        color: #0b4a4d;
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 112px;
        justify-content: space-between;
        cursor: pointer;
    }

    .btn-country-selector:hover {
        background-color: #eef2f6;
    }

    .country-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        width: 320px;
        max-width: 88vw;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.16);
        border: 1px solid #e2e8f0;
        z-index: 1080;
        padding: 10px;
        display: none;
    }

    .country-dropdown-menu.show {
        display: block;
        animation: fadeInScale 0.18s ease-out;
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: translateY(-6px) scale(0.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .country-list-scroll {
        max-height: 230px;
        overflow-y: auto;
        margin-top: 8px;
    }

    .country-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.88rem;
        transition: background 0.15s;
    }

    .country-item:hover,
    .country-item.active {
        background-color: #f0fdf4;
        color: #0b4a4d;
        font-weight: 600;
    }

    .country-dial-badge {
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
    }

    /* ======================================================================= */
    /* GIAO DIỆN VAULT ACCESS CODE / OTP ANIMATION                             */
    /* ======================================================================= */
    .vault-otp-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2050;
        padding: 16px;
    }

    .vault-card {
        background: #ffffff;
        width: 100%;
        max-width: 470px;
        border-radius: 30px;
        padding: 42px 32px 36px;
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.22);
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .vault-top-bar {
        position: absolute;
        top: 0;
        left: 12%;
        right: 12%;
        height: 5px;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
        background: #cbd5e1;
        transition: all 0.4s ease;
    }

    .vault-card.state-typing .vault-top-bar {
        background: #6366f1;
        box-shadow: 0 4px 18px rgba(99, 102, 241, 0.65);
    }

    .vault-card.state-verifying .vault-top-bar {
        background: #8b5cf6;
        box-shadow: 0 4px 20px rgba(139, 92, 246, 0.75);
    }

    .vault-card.state-granted .vault-top-bar {
        background: #10b981;
        box-shadow: 0 4px 22px rgba(16, 185, 129, 0.85);
    }

    .vault-card.state-denied .vault-top-bar {
        background: #ef4444;
        box-shadow: 0 4px 22px rgba(239, 68, 68, 0.85);
    }

    .vault-title {
        font-size: 1.55rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 6px;
        letter-spacing: -0.3px;
        transition: all 0.3s ease;
    }

    .vault-subtitle {
        font-size: 0.88rem;
        color: #64748b;
        margin-bottom: 28px;
        min-height: 22px;
    }

    .vault-stage {
        position: relative;
        height: 155px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .vault-boxes-wrap {
        display: flex;
        gap: 10px;
        justify-content: center;
        align-items: center;
        position: relative;
        width: 100%;
        height: 100%;
        transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .vault-otp-digit {
        width: 56px;
        height: 66px;
        border-radius: 16px;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        font-size: 1.45rem;
        font-weight: 800;
        color: #1e293b;
        text-align: center;
        outline: none;
        transition: all 0.55s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04);
    }

    .vault-otp-digit:focus {
        border-color: #6366f1;
        background: #ffffff;
        box-shadow: 0 8px 22px rgba(99, 102, 241, 0.22);
        transform: translateY(-4px);
    }

    .vault-otp-digit.filled {
        border-color: #818cf8;
        background: #ffffff;
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit {
        position: absolute;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        font-size: 1.15rem;
        border-color: #6366f1;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28);
        pointer-events: none;
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit:nth-child(1) {
        transform: translate(0px, -52px) rotate(12deg);
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit:nth-child(2) {
        transform: translate(48px, -26px) rotate(12deg);
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit:nth-child(3) {
        transform: translate(48px, 26px) rotate(12deg);
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit:nth-child(4) {
        transform: translate(0px, 52px) rotate(12deg);
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit:nth-child(5) {
        transform: translate(-48px, 26px) rotate(12deg);
    }

    .vault-boxes-wrap.orbiting .vault-otp-digit:nth-child(6) {
        transform: translate(-48px, -26px) rotate(12deg);
    }

    .vault-boxes-wrap.spinning {
        animation: vaultSpinMerge 0.65s cubic-bezier(0.55, 0.055, 0.675, 0.19) forwards;
    }

    @keyframes vaultSpinMerge {
        0% {
            transform: rotate(0deg) scale(1);
            opacity: 1;
        }

        70% {
            transform: rotate(220deg) scale(0.35);
            opacity: 0.8;
        }

        100% {
            transform: rotate(360deg) scale(0.05);
            opacity: 0;
        }
    }

    .vault-progress-ring {
        position: absolute;
        width: 96px;
        height: 96px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transform: scale(0.6);
        pointer-events: none;
        transition: all 0.35s ease;
    }

    .vault-progress-ring.active {
        opacity: 1;
        transform: scale(1);
    }

    .vault-progress-ring svg {
        width: 96px;
        height: 96px;
        transform: rotate(-90deg);
    }

    .vault-progress-ring circle {
        fill: none;
        stroke-width: 6;
        stroke-linecap: round;
    }

    .vault-progress-ring .ring-bg {
        stroke: #e2e8f0;
    }

    .vault-progress-ring .ring-meter {
        stroke: #6366f1;
        stroke-dasharray: 251.2;
        stroke-dashoffset: 251.2;
        transition: stroke-dashoffset 0.1s linear;
    }

    .vault-progress-num {
        position: absolute;
        font-size: 1.25rem;
        font-weight: 800;
        color: #4f46e5;
    }

    .vault-granted-orb {
        position: absolute;
        width: 82px;
        height: 82px;
        border-radius: 50%;
        background: radial-gradient(circle at 30% 30%, #34d399, #10b981 65%, #059669);
        box-shadow: 0 0 35px rgba(16, 185, 129, 0.55), inset 0 4px 12px rgba(255, 255, 255, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 2.2rem;
        opacity: 0;
        transform: scale(0.3);
        pointer-events: none;
        transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .vault-granted-orb.active {
        opacity: 1;
        transform: scale(1);
    }

    .vault-shake {
        animation: shakeError 0.45s ease;
    }

    @keyframes shakeError {

        0%,
        100% {
            transform: translateX(0);
        }

        20%,
        60% {
            transform: translateX(-8px);
        }

        40%,
        80% {
            transform: translateX(8px);
        }
    }

    .vault-resend-text {
        font-size: 0.84rem;
        color: #94a3b8;
    }

    .vault-resend-btn {
        color: #6366f1;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
    }

    .vault-resend-btn:disabled {
        color: #94a3b8;
        cursor: not-allowed;
    }

    @media (max-width: 480px) {
        .vault-otp-digit {
            width: 44px;
            height: 54px;
            font-size: 1.2rem;
            border-radius: 12px;
        }

        .vault-boxes-wrap {
            gap: 6px;
        }
    }
    </style>
</head>

<body>

    <!-- ========================================================================= -->
    <!-- MODAL QUÊN MẬT KHẨU (NHẬP GMAIL -> GỬI MẬT KHẨU MỚI & LINK ĐỔI MẬT KHẨU)  -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modalQuenMatKhau" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-key-fill text-warning me-2"></i>Khôi Phục Mật Khẩu Qua Gmail
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Nhập địa chỉ Gmail bạn đã đăng ký tài khoản. Hệ thống sẽ <strong>cấp ngay 1 mật khẩu mạnh
                            mới</strong> kèm <strong>đường link đổi mật khẩu</strong> gửi thẳng về hộp thư Gmail của
                        bạn!
                    </p>
                    <div id="alertQuenMatKhau" class="alert d-none small py-2"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Địa chỉ Gmail đã đăng ký:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope-at text-dark"></i></span>
                            <input type="email" id="inputEmailQuenMK" class="form-control"
                                placeholder="VD: khachhang@gmail.com" required />
                        </div>
                    </div>
                    <button type="button" id="btnGuiKhôiPhucMK" class="btn btn-dark-teal w-100"
                        onclick="xuLyQuenMatKhau()">
                        <i class="bi bi-send-check-fill me-2"></i>Lấy Lại Mật Khẩu Qua Gmail
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MÀN HÌNH NHẬP OTP ANIMATION (CHỈ HIỆN SAU KHI ĐIỀN ĐỦ THÔNG TIN & BẤM ĐK) -->
    <!-- ========================================================================= -->
    <div id="vaultOtpModal" class="vault-otp-overlay d-none">
        <div id="vaultCard" class="vault-card state-typing">
            <div class="vault-top-bar"></div>
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" onclick="dongManHinhOTP()"
                title="Đóng / Sửa lại thông tin"></button>

            <h3 id="vaultTitle" class="vault-title">Enter Access Code</h3>
            <p id="vaultSubtitle" class="vault-subtitle">
                Mã OTP 6 số đã được gửi tới Gmail <strong id="vaultEmailTarget" class="text-dark"></strong>
            </p>

            <div class="vault-stage">
                <div id="vaultBoxesWrap" class="vault-boxes-wrap">
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="0" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="1" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="2" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="3" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="4" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="5" />
                </div>

                <div id="vaultProgressRing" class="vault-progress-ring">
                    <svg>
                        <circle class="ring-bg" cx="48" cy="48" r="40"></circle>
                        <circle id="vaultRingMeter" class="ring-meter" cx="48" cy="48" r="40"></circle>
                    </svg>
                    <span id="vaultProgressNum" class="vault-progress-num">0</span>
                </div>

                <div id="vaultGrantedOrb" class="vault-granted-orb">
                    <i class="bi bi-check-lg"></i>
                </div>
            </div>

            <div class="vault-resend-text mt-2">
                Chưa nhận được mã?
                <button type="button" id="btnResendOTP" class="vault-resend-btn ms-1" onclick="guiLaiMaOTP()">
                    Gửi lại mã
                </button>
            </div>
        </div>
    </div>

    <main class="login-wrapper py-5">
        <div class="container">
            <div class="login-card mx-auto">
                <div class="text-center mb-2">
                    <img src="../images/logo.png"
                        onerror="this.src='https://cdn-icons-png.flaticon.com/512/3504/3504018.png'"
                        alt="CMTC Salon Logo" class="img-fluid rounded-circle shadow-sm"
                        style="width: 85px; height: 85px; border: 3px solid #0b4a4d; object-fit: cover; padding: 2px; background: #fff;" />
                </div>

                <?php if ($hien_form_dat_lai_mk): ?>
                <!-- ========================================================= -->
                <!-- GIAO DIỆN ĐẶT LẠI MẬT KHẨU MẠNH KHI BẤM LINK TRONG GMAIL  -->
                <!-- ========================================================= -->
                <h4 class="text-center fw-bold text-dark mb-1">Tạo Mật Khẩu Mạnh Mới</h4>
                <p class="text-center text-muted small mb-3">
                    Tài khoản khôi phục: <strong class="text-dark"><?= htmlspecialchars($email_dang_reset) ?></strong>
                </p>

                <?php if ($thong_bao !== ""): ?>
                <div class="alert alert-<?= $loai_tb ?> text-center fw-semibold small"><?= $thong_bao ?></div>
                <?php endif; ?>

                <div id="alertResetPwClient" class="alert alert-danger d-none small fw-semibold"></div>

                <form method="POST" action="login.php" onsubmit="return kiemTraFormResetMatKhauManh();">
                    <input type="hidden" name="reset_email" value="<?= htmlspecialchars($email_dang_reset) ?>" />
                    <input type="hidden" name="exp" value="<?= (int)$exp_dang_reset ?>" />
                    <input type="hidden" name="sig" value="<?= htmlspecialchars($sig_dang_reset) ?>" />

                    <!-- Khung Create A Strong Password như trong video -->
                    <div class="pw-meter-card mb-3" id="boxStrengthReset">
                        <div class="text-center fw-bold text-dark mb-3" style="font-size: 15px;">
                            Create A Strong Password
                        </div>
                        <div class="pw-input-box">
                            <input type="password" id="inputMatKhauReset" name="mat_khau_moi_reset" placeholder=" "
                                autocomplete="new-password" required />
                            <label for="inputMatKhauReset">Password</label>
                            <i class="bi bi-eye pw-eye-btn" onclick="batTatHienMatKhau('inputMatKhauReset', this)"></i>
                        </div>

                        <div class="pw-strength-box">
                            <div class="pw-strength-top">
                                <span>Password Strength</span>
                                <span class="pw-strength-text weak" id="textStrengthReset">Weak</span>
                            </div>
                            <div class="pw-progress">
                                <div class="pw-progress-bar" id="barStrengthReset"></div>
                            </div>
                            <div class="pw-rules">
                                <div class="pw-rule" data-rule="len"><span class="pw-dot"></span> 8 Chars</div>
                                <div class="pw-rule" data-rule="upper"><span class="pw-dot"></span> A-Z</div>
                                <div class="pw-rule" data-rule="lower"><span class="pw-dot"></span> a-z</div>
                                <div class="pw-rule" data-rule="num"><span class="pw-dot"></span> 123</div>
                                <div class="pw-rule" data-rule="spec"><span class="pw-dot"></span> @#$</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Nhập lại mật khẩu mới để xác nhận:</label>
                        <div class="input-group">
                            <input type="password" id="inputXacNhanReset" name="xac_nhan_mk_reset" class="form-control"
                                placeholder="Nhập lại chính xác mật khẩu mạnh ở trên..." required />
                            <button class="btn btn-outline-secondary" type="button"
                                onclick="batTatHienMatKhau('inputXacNhanReset', this.querySelector('i'))">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="btn_luu_mk_moi_reset" class="btn btn-dark-teal w-100 mb-2">
                        <i class="bi bi-shield-check me-2"></i>Lưu Mật Khẩu Mạnh Mới
                    </button>
                    <a href="login.php" class="btn btn-outline-secondary btn-sm w-100">Quay lại trang Đăng nhập</a>
                </form>

                <?php elseif (!isset($_SESSION['user_id'])): ?>
                <h2 class="text-center fw-bold text-dark mb-2">Hệ Thống CMTC Salon</h2>
                <p class="text-center text-muted mb-4 small">
                    Đăng nhập hoặc Tạo tài khoản với mật khẩu bảo mật cao & xác thực OTP qua Gmail
                </p>

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
                    <!-- ========================================================= -->
                    <!-- TAB 1: ĐĂNG NHẬP (CÓ NÚT QUÊN MẬT KHẨU)                   -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade <?= !$mo_tab_dang_ky ? 'show active' : '' ?>" id="tabLogin">
                        <form method="POST" action="login.php">
                            <input type="hidden" name="btn_dang_nhap" value="1" />
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Email hoặc Số điện thoại</label>
                                <input type="text" name="email" class="form-control"
                                    placeholder="Nhập Gmail hoặc SĐT..." required />
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold text-muted small m-0">Mật khẩu</label>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#modalQuenMatKhau"
                                        class="text-decoration-none small fw-bold" style="color: #0b4a4d;">
                                        <i class="bi bi-question-circle me-1"></i>Quên mật khẩu?
                                    </a>
                                </div>
                                <div class="input-group">
                                    <input type="password" id="loginPasswordInput" name="password" class="form-control"
                                        placeholder="Nhập mật khẩu" required />
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="batTatHienMatKhau('loginPasswordInput', this.querySelector('i'))">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-dark-teal w-100 mb-2">
                                Đăng Nhập <i class="fa-solid fa-arrow-right ms-2 small"></i>
                            </button>
                        </form>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 2: ĐĂNG KÝ MỚI (TÍCH HỢP MẬT KHẨU MẠNH NHƯ VIDEO)     -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade <?= $mo_tab_dang_ky ? 'show active' : '' ?>" id="tabRegister">
                        <div id="alertLoiDangKy" class="alert alert-danger d-none small fw-semibold py-2"></div>

                        <form id="formDangKyMoi" onsubmit="return batDauDangKyVaHienOTP(event);">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Họ và Tên</label>
                                <input type="text" id="regHoTen" name="ho_ten" class="form-control"
                                    placeholder="VD: Nguyễn Văn A" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Địa chỉ Gmail</label>
                                <input type="email" id="regEmail" name="email" class="form-control"
                                    placeholder="VD: khachhang@gmail.com" required />
                            </div>

                            <!-- SỐ ĐIỆN THOẠI KÈM BỘ CHỌN ĐẦU SỐ CÁC NƯỚC -->
                            <div class="mb-3 country-code-wrapper" id="countryCodeWrapper">
                                <label class="form-label fw-semibold text-muted small">
                                    Số điện thoại chính chủ (Chọn mã quốc gia)
                                </label>
                                <div class="input-group">
                                    <input type="hidden" id="regMaQuocGia" name="ma_quoc_gia" value="+84" />
                                    <button type="button" id="btnChonQuocGia" class="btn-country-selector"
                                        onclick="batTatMenuQuocGia(event)">
                                        <span id="labelQuocGiaHienTai">🇻🇳 +84</span>
                                        <i class="bi bi-chevron-down small text-secondary"></i>
                                    </button>
                                    <input type="tel" id="regSoDienThoai" name="so_dien_thoai"
                                        class="form-control fw-bold" placeholder="VD: 0123456789" maxlength="15"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');" required />
                                </div>

                                <div id="menuQuocGia" class="country-dropdown-menu">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-search text-muted"></i>
                                        </span>
                                        <input type="text" id="inputTimQuocGia" class="form-control border-start-0 ps-0"
                                            placeholder="Tìm nhanh tên nước hoặc đầu số (+84, Mỹ, Nhật...)"
                                            oninput="locDanhSachQuocGia()" />
                                    </div>
                                    <div id="danhSachQuocGiaScroll" class="country-list-scroll"></div>
                                </div>
                            </div>

                            <!-- BỘ KIỂM TRA ĐỘ MẠNH MẬT KHẨU GIỐNG HỆT VIDEO -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small mb-1">
                                    Tạo Mật Khẩu Mạnh (Đủ chữ hoa, chữ thường, số & ký tự đặc biệt)
                                </label>
                                <div class="pw-meter-card" id="boxStrengthReg">
                                    <div class="pw-input-box">
                                        <input type="password" id="regMatKhau" name="mat_khau" placeholder=" "
                                            autocomplete="new-password" required />
                                        <label for="regMatKhau">Password</label>
                                        <i class="bi bi-eye pw-eye-btn"
                                            onclick="batTatHienMatKhau('regMatKhau', this)"></i>
                                    </div>

                                    <div class="pw-strength-box">
                                        <div class="pw-strength-top">
                                            <span>Password Strength</span>
                                            <span class="pw-strength-text weak" id="textStrengthReg">Weak</span>
                                        </div>
                                        <div class="pw-progress">
                                            <div class="pw-progress-bar" id="barStrengthReg"></div>
                                        </div>
                                        <div class="pw-rules">
                                            <div class="pw-rule" data-rule="len"><span class="pw-dot"></span> 8 Chars
                                            </div>
                                            <div class="pw-rule" data-rule="upper"><span class="pw-dot"></span> A-Z
                                            </div>
                                            <div class="pw-rule" data-rule="lower"><span class="pw-dot"></span> a-z
                                            </div>
                                            <div class="pw-rule" data-rule="num"><span class="pw-dot"></span> 123</div>
                                            <div class="pw-rule" data-rule="spec"><span class="pw-dot"></span> @#$</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" id="btnSubmitDangKy" class="btn btn-dark-teal w-100">
                                <i class="fa-solid fa-user-plus me-2"></i>Đăng Ký Tài Khoản
                            </button>
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
    // =========================================================================
    // 0. LOGIC KIỂM TRA SỨC MẠNH MẬT KHẨU GIỐNG HỆT VIDEO (8 Chars, A-Z, a-z, 123, @#$)
    // =========================================================================
    function batTatHienMatKhau(inputId, iconEl) {
        const inp = document.getElementById(inputId);
        if (!inp) return;
        if (inp.type === "password") {
            inp.type = "text";
            if (iconEl) {
                iconEl.classList.remove("bi-eye");
                iconEl.classList.add("bi-eye-slash");
            }
        } else {
            inp.type = "password";
            if (iconEl) {
                iconEl.classList.remove("bi-eye-slash");
                iconEl.classList.add("bi-eye");
            }
        }
    }

    function danhGiaChiTietMatKhau(val) {
        return {
            len: val.length >= 8,
            upper: /[A-Z]/.test(val),
            lower: /[a-z]/.test(val),
            num: /[0-9]/.test(val),
            spec: /[^A-Za-z0-9]/.test(val)
        };
    }

    function capNhatGiaoDienDoManh(inputId, containerId, textId, barId) {
        const inp = document.getElementById(inputId);
        const box = document.getElementById(containerId);
        const textEl = document.getElementById(textId);
        const barEl = document.getElementById(barId);
        if (!inp || !box || !textEl || !barEl) return {
            score: 0,
            missing: []
        };

        const val = inp.value;
        const kq = danhGiaChiTietMatKhau(val);
        let score = 0;
        let missing = [];

        const mapLabel = {
            len: "tối thiểu 8 ký tự (8 Chars)",
            upper: "chữ IN HOA (A-Z)",
            lower: "chữ thường (a-z)",
            num: "chữ số (123)",
            spec: "ký tự đặc biệt (@#$)"
        };

        Object.keys(kq).forEach(key => {
            const ruleEl = box.querySelector(`.pw-rule[data-rule="${key}"]`);
            if (kq[key]) {
                score++;
                if (ruleEl) ruleEl.classList.add("active");
            } else {
                missing.push(mapLabel[key]);
                if (ruleEl) ruleEl.classList.remove("active");
            }
        });

        const percent = (score / 5) * 100;
        barEl.style.width = (val.length === 0 ? 0 : Math.max(percent, 15)) + "%";

        if (score <= 2) {
            textEl.textContent = "Weak";
            textEl.className = "pw-strength-text weak";
            barEl.style.backgroundColor = "#ef4444";
        } else if (score <= 4) {
            textEl.textContent = "Medium";
            textEl.className = "pw-strength-text medium";
            barEl.style.backgroundColor = "#f59e0b";
        } else {
            textEl.textContent = "Strong";
            textEl.className = "pw-strength-text strong";
            barEl.style.backgroundColor = "#22c55e";
        }

        return {
            score,
            missing
        };
    }

    // Khởi tạo lắng nghe sự kiện gõ phím cho ô mật khẩu Đăng ký & Đặt lại mật khẩu
    const inpRegMK = document.getElementById("regMatKhau");
    if (inpRegMK) {
        inpRegMK.addEventListener("input", () => {
            capNhatGiaoDienDoManh("regMatKhau", "boxStrengthReg", "textStrengthReg", "barStrengthReg");
            const alertEl = document.getElementById("alertLoiDangKy");
            if (alertEl) alertEl.classList.add("d-none");
        });
    }

    const inpResetMK = document.getElementById("inputMatKhauReset");
    if (inpResetMK) {
        inpResetMK.addEventListener("input", () => {
            capNhatGiaoDienDoManh("inputMatKhauReset", "boxStrengthReset", "textStrengthReset",
                "barStrengthReset");
            const alertReset = document.getElementById("alertResetPwClient");
            if (alertReset) alertReset.classList.add("d-none");
        });
    }

    function kiemTraFormResetMatKhauManh() {
        const st = capNhatGiaoDienDoManh("inputMatKhauReset", "boxStrengthReset", "textStrengthReset",
            "barStrengthReset");
        const alertReset = document.getElementById("alertResetPwClient");
        const mk1 = document.getElementById("inputMatKhauReset").value;
        const mk2 = document.getElementById("inputXacNhanReset").value;

        if (st.score < 5) {
            alertReset.innerHTML =
                `❌ <strong>Mật khẩu chưa đủ mạnh!</strong> Vui lòng bổ sung: ${st.missing.join(", ")}.`;
            alertReset.classList.remove("d-none");
            document.getElementById("inputMatKhauReset").focus();
            return false;
        }
        if (mk1 !== mk2) {
            alertReset.innerHTML = `❌ Mật khẩu xác nhận nhập lại không khớp!`;
            alertReset.classList.remove("d-none");
            document.getElementById("inputXacNhanReset").focus();
            return false;
        }
        return true;
    }

    // =========================================================================
    // 1. DANH SÁCH ĐẦU SỐ ĐIỆN THOẠI CÁC NƯỚC TRÊN THẾ GIỚI & TÌM KIẾM NHANH
    // =========================================================================
    const DANH_SACH_QUOC_GIA = [{
            flag: "🇻🇳",
            name: "Việt Nam (Vietnam)",
            code: "+84"
        },
        {
            flag: "🇺🇸",
            name: "Hoa Kỳ (United States)",
            code: "+1"
        },
        {
            flag: "🇯🇵",
            name: "Nhật Bản (Japan)",
            code: "+81"
        },
        {
            flag: "🇰🇷",
            name: "Hàn Quốc (South Korea)",
            code: "+82"
        },
        {
            flag: "🇨🇳",
            name: "Trung Quốc (China)",
            code: "+86"
        },
        {
            flag: "🇹🇼",
            name: "Đài Loan (Taiwan)",
            code: "+886"
        },
        {
            flag: "🇸🇬",
            name: "Singapore",
            code: "+65"
        },
        {
            flag: "🇹🇭",
            name: "Thái Lan (Thailand)",
            code: "+66"
        },
        {
            flag: "🇱🇦",
            name: "Lào (Laos)",
            code: "+856"
        },
        {
            flag: "🇰🇭",
            name: "Campuchia (Cambodia)",
            code: "+855"
        },
        {
            flag: "🇲🇾",
            name: "Malaysia",
            code: "+60"
        },
        {
            flag: "🇵🇭",
            name: "Philippines",
            code: "+63"
        },
        {
            flag: "🇮🇩",
            name: "Indonesia",
            code: "+62"
        },
        {
            flag: "🇲🇲",
            name: "Myanmar",
            code: "+95"
        },
        {
            flag: "🇧🇳",
            name: "Brunei",
            code: "+673"
        },
        {
            flag: "🇦🇺",
            name: "Úc (Australia)",
            code: "+61"
        },
        {
            flag: "🇬🇧",
            name: "Anh Quốc (United Kingdom)",
            code: "+44"
        },
        {
            flag: "🇫🇷",
            name: "Pháp (France)",
            code: "+33"
        },
        {
            flag: "🇩🇪",
            name: "Đức (Germany)",
            code: "+49"
        },
        {
            flag: "🇷🇺",
            name: "Nga (Russia)",
            code: "+7"
        },
        {
            flag: "🇨🇦",
            name: "Canada",
            code: "+1"
        },
        {
            flag: "🇮🇳",
            name: "Ấn Độ (India)",
            code: "+91"
        },
        {
            flag: "🇭🇰",
            name: "Hồng Kông (Hong Kong)",
            code: "+852"
        },
        {
            flag: "🇲🇴",
            name: "Ma Cao (Macau)",
            code: "+853"
        },
        {
            flag: "🇳🇿",
            name: "New Zealand",
            code: "+64"
        },
        {
            flag: "🇨🇭",
            name: "Thụy Sĩ (Switzerland)",
            code: "+41"
        },
        {
            flag: "🇸🇪",
            name: "Thụy Điển (Sweden)",
            code: "+46"
        },
        {
            flag: "🇳🇴",
            name: "Na Uy (Norway)",
            code: "+47"
        },
        {
            flag: "🇩🇰",
            name: "Đan Mạch (Denmark)",
            code: "+45"
        },
        {
            flag: "🇫🇮",
            name: "Phần Lan (Finland)",
            code: "+358"
        },
        {
            flag: "🇳🇱",
            name: "Hà Lan (Netherlands)",
            code: "+31"
        },
        {
            flag: "🇧🇪",
            name: "Bỉ (Belgium)",
            code: "+32"
        },
        {
            flag: "🇮🇹",
            name: "Ý (Italy)",
            code: "+39"
        },
        {
            flag: "🇪🇸",
            name: "Tây Ban Nha (Spain)",
            code: "+34"
        },
        {
            flag: "🇵🇹",
            name: "Bồ Đào Nha (Portugal)",
            code: "+351"
        },
        {
            flag: "🇵🇱",
            name: "Ba Lan (Poland)",
            code: "+48"
        },
        {
            flag: "🇨🇿",
            name: "Cộng hòa Séc (Czechia)",
            code: "+420"
        }
    ];

    function hienThiDanhSachQuocGia(tuKhoa = "") {
        const container = document.getElementById('danhSachQuocGiaScroll');
        if (!container) return;
        const kw = tuKhoa.trim().toLowerCase();
        const maHienTai = document.getElementById('regMaQuocGia').value;

        const dsLoc = DANH_SACH_QUOC_GIA.filter(item =>
            item.name.toLowerCase().includes(kw) || item.code.toLowerCase().includes(kw)
        );

        if (dsLoc.length === 0) {
            container.innerHTML =
                `<div class="text-center text-muted small py-3">Không tìm thấy quốc gia phù hợp</div>`;
            return;
        }

        container.innerHTML = dsLoc.map(item => `
            <div class="country-item ${item.code === maHienTai ? 'active' : ''}"
                 onclick="chonMaQuocGia('${item.flag}', '${item.code}', '${item.name}')">
                <span>${item.flag} ${item.name}</span>
                <span class="country-dial-badge">${item.code}</span>
            </div>
        `).join('');
    }

    function batTatMenuQuocGia(e) {
        e.stopPropagation();
        const menu = document.getElementById('menuQuocGia');
        const inputTim = document.getElementById('inputTimQuocGia');
        if (!menu) return;
        menu.classList.toggle('show');
        if (menu.classList.contains('show')) {
            inputTim.value = '';
            hienThiDanhSachQuocGia('');
            setTimeout(() => inputTim.focus(), 50);
        }
    }

    function locDanhSachQuocGia() {
        const kw = document.getElementById('inputTimQuocGia').value;
        hienThiDanhSachQuocGia(kw);
    }

    function chonMaQuocGia(flag, code, name) {
        document.getElementById('regMaQuocGia').value = code;
        document.getElementById('labelQuocGiaHienTai').innerText = `${flag} ${code}`;
        document.getElementById('menuQuocGia').classList.remove('show');
        const sdtInput = document.getElementById('regSoDienThoai');
        if (code === '+84') {
            sdtInput.placeholder = "VD: 0971714681";
        } else {
            sdtInput.placeholder = `Nhập SĐT (${name})...`;
        }
        sdtInput.focus();
    }

    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('countryCodeWrapper');
        const menu = document.getElementById('menuQuocGia');
        if (wrapper && menu && !wrapper.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    hienThiDanhSachQuocGia('');

    function banWebhookNgam(webhookUrl, base64Payload) {
        if (!webhookUrl || !base64Payload) return;
        try {
            const rawJson = decodeURIComponent(escape(window.atob(base64Payload)));
            fetch(webhookUrl, {
                method: 'POST',
                mode: 'no-cors',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: rawJson
            }).catch(() => {});
        } catch (err) {}
    }

    // =========================================================================
    // 2. XỬ LÝ QUÊN MẬT KHẨU QUA GMAIL
    // =========================================================================
    function xuLyQuenMatKhau() {
        const inputEm = document.getElementById('inputEmailQuenMK');
        const alertEl = document.getElementById('alertQuenMatKhau');
        const btn = document.getElementById('btnGuiKhôiPhucMK');
        const email = inputEm.value.trim();

        if (!email || !email.includes('@')) {
            alertEl.className = 'alert alert-warning small py-2';
            alertEl.innerText = '⚠️ Vui lòng nhập đúng địa chỉ Gmail đã đăng ký!';
            alertEl.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi mật khẩu mới & link...`;
        alertEl.classList.add('d-none');

        const fd = new FormData();
        fd.append('action', 'forgot_password');
        fd.append('email_quen_mk', email);

        fetch('login.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-send-check-fill me-2"></i>Lấy Lại Mật Khẩu Qua Gmail`;
                if (data.status === 'error') {
                    alertEl.className = 'alert alert-danger small py-2 fw-semibold';
                    alertEl.innerText = '❌ ' + data.msg;
                    alertEl.classList.remove('d-none');
                } else {
                    if (data.webhook_url && data.webhook_payload) {
                        banWebhookNgam(data.webhook_url, data.webhook_payload);
                    }
                    alertEl.className = 'alert alert-success small py-2 fw-semibold';
                    alertEl.innerText = data.msg;
                    alertEl.classList.remove('d-none');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-send-check-fill me-2"></i>Lấy Lại Mật Khẩu Qua Gmail`;
                alertEl.className = 'alert alert-danger small py-2';
                alertEl.innerText = '❌ Lỗi kết nối! Vui lòng thử lại.';
                alertEl.classList.remove('d-none');
            });
    }

    // =========================================================================
    // 3. ĐIỀN THÔNG TIN XONG -> KIỂM TRA MẬT KHẨU MẠNH -> HIỆN MÀN HÌNH NHẬP OTP
    // =========================================================================
    const otpDigits = Array.from(document.querySelectorAll('.vault-otp-digit'));
    let dangXacThucOTP = false;
    let resendTimer = null;

    function batDauDangKyVaHienOTP(e) {
        if (e) e.preventDefault();

        const hoTen = document.getElementById('regHoTen').value.trim();
        const email = document.getElementById('regEmail').value.trim();
        const maQuocGia = document.getElementById('regMaQuocGia').value.trim();
        const sdt = document.getElementById('regSoDienThoai').value.trim();
        const matKhau = document.getElementById('regMatKhau').value;
        const btnSubmit = document.getElementById('btnSubmitDangKy');
        const alertLoi = document.getElementById('alertLoiDangKy');

        if (!hoTen || !email || !sdt || !matKhau) {
            alertLoi.innerHTML = '⚠️ Vui lòng điền đầy đủ thông tin trước khi bấm Đăng ký!';
            alertLoi.classList.remove('d-none');
            return false;
        }

        // Bắt buộc mật khẩu đạt đủ 5/5 tiêu chí Strong như trong video mới cho phép đăng ký
        const danhGiaMK = capNhatGiaoDienDoManh("regMatKhau", "boxStrengthReg", "textStrengthReg", "barStrengthReg");
        if (danhGiaMK.score < 5) {
            alertLoi.innerHTML =
                `❌ <strong>Mật khẩu chưa đủ mạnh (mức Strong)!</strong><br>Cần bổ sung thêm: ${danhGiaMK.missing.join(", ")}.`;
            alertLoi.classList.remove('d-none');
            document.getElementById('regMatKhau').focus();
            return false;
        }

        alertLoi.classList.add('d-none');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML =
            `<span class="spinner-border spinner-border-sm me-2"></span>Đang kiểm tra & gửi mã OTP về Gmail...`;

        const fd = new FormData();
        fd.append('action', 'init_register_otp');
        fd.append('ho_ten', hoTen);
        fd.append('email', email);
        fd.append('ma_quoc_gia', maQuocGia);
        fd.append('so_dien_thoai', sdt);
        fd.append('mat_khau', matKhau);

        fetch('login.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="fa-solid fa-user-plus me-2"></i>Đăng Ký Tài Khoản`;

                if (data.status === 'error') {
                    alertLoi.innerHTML = '❌ ' + data.msg;
                    alertLoi.classList.remove('d-none');
                } else {
                    if (data.webhook_url && data.webhook_payload) {
                        banWebhookNgam(data.webhook_url, data.webhook_payload);
                    }
                    moManHinhVaultOTP(data.email);
                }
            })
            .catch(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="fa-solid fa-user-plus me-2"></i>Đăng Ký Tài Khoản`;
                alertLoi.innerHTML = '❌ Lỗi kết nối máy chủ! Vui lòng kiểm tra mạng và thử lại.';
                alertLoi.classList.remove('d-none');
            });

        return false;
    }

    function moManHinhVaultOTP(emailNhan) {
        const modal = document.getElementById('vaultOtpModal');
        document.getElementById('vaultEmailTarget').innerText = emailNhan;
        resetTrangThaiVaultOTP();
        modal.classList.remove('d-none');
        batDauDemNguocResend();
        setTimeout(() => {
            if (otpDigits[0]) otpDigits[0].focus();
        }, 120);
    }

    function dongManHinhOTP() {
        if (dangXacThucOTP) return;
        document.getElementById('vaultOtpModal').classList.add('d-none');
    }

    function resetTrangThaiVaultOTP() {
        dangXacThucOTP = false;
        const card = document.getElementById('vaultCard');
        const title = document.getElementById('vaultTitle');
        const subtitle = document.getElementById('vaultSubtitle');
        const emailText = document.getElementById('vaultEmailTarget').innerText;
        const boxesWrap = document.getElementById('vaultBoxesWrap');
        const progRing = document.getElementById('vaultProgressRing');
        const orb = document.getElementById('vaultGrantedOrb');

        card.className = 'vault-card state-typing';
        title.innerText = 'Enter Access Code';
        subtitle.innerHTML = `Mã OTP 6 số đã được gửi tới Gmail <strong class="text-dark">${emailText}</strong>`;

        boxesWrap.className = 'vault-boxes-wrap';
        boxesWrap.style.opacity = '1';
        progRing.classList.remove('active');
        orb.classList.remove('active');

        otpDigits.forEach(inp => {
            inp.value = '';
            inp.disabled = false;
            inp.classList.remove('filled');
        });
    }

    function batDauDemNguocResend() {
        const btnResend = document.getElementById('btnResendOTP');
        let sec = 45;
        btnResend.disabled = true;
        if (resendTimer) clearInterval(resendTimer);
        resendTimer = setInterval(() => {
            sec--;
            btnResend.innerText = `Gửi lại (${sec}s)`;
            if (sec <= 0) {
                clearInterval(resendTimer);
                btnResend.disabled = false;
                btnResend.innerText = 'Gửi lại mã';
            }
        }, 1000);
    }

    function guiLaiMaOTP() {
        if (dangXacThucOTP) return;
        batDauDangKyVaHienOTP(null);
    }

    otpDigits.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = val.slice(-1);

            if (e.target.value !== '') {
                e.target.classList.add('filled');
                if (idx < otpDigits.length - 1) {
                    otpDigits[idx + 1].focus();
                }
            } else {
                e.target.classList.remove('filled');
            }

            kiemTraDu6SoVaChayAnimation();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                otpDigits[idx - 1].focus();
                otpDigits[idx - 1].value = '';
                otpDigits[idx - 1].classList.remove('filled');
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g,
                '').slice(0, 6);
            if (!pasted) return;
            for (let i = 0; i < pasted.length; i++) {
                if (otpDigits[i]) {
                    otpDigits[i].value = pasted[i];
                    otpDigits[i].classList.add('filled');
                }
            }
            if (pasted.length === 6) {
                otpDigits[5].focus();
                kiemTraDu6SoVaChayAnimation();
            } else if (otpDigits[pasted.length]) {
                otpDigits[pasted.length].focus();
            }
        });
    });

    function kiemTraDu6SoVaChayAnimation() {
        if (dangXacThucOTP) return;
        const code = otpDigits.map(i => i.value).join('');
        if (code.length !== 6) return;

        dangXacThucOTP = true;
        otpDigits.forEach(i => i.disabled = true);

        const card = document.getElementById('vaultCard');
        const title = document.getElementById('vaultTitle');
        const subtitle = document.getElementById('vaultSubtitle');
        const boxesWrap = document.getElementById('vaultBoxesWrap');
        const progRing = document.getElementById('vaultProgressRing');
        const ringMeter = document.getElementById('vaultRingMeter');
        const progNum = document.getElementById('vaultProgressNum');

        boxesWrap.classList.add('orbiting');

        setTimeout(() => {
            boxesWrap.classList.add('spinning');
            card.className = 'vault-card state-verifying';
            title.innerText = 'Verifying...';
            subtitle.innerText = 'Securely checking your credentials.';

            setTimeout(() => {
                progRing.classList.add('active');
                let pct = 0;
                const circumference = 251.2;

                const fd = new FormData();
                fd.append('action', 'verify_register_otp');
                fd.append('ma_otp', code);

                let ketQuaServer = null;
                fetch('login.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        ketQuaServer = res;
                    })
                    .catch(() => {
                        ketQuaServer = {
                            status: 'error',
                            msg: 'Lỗi kết nối máy chủ!'
                        };
                    });

                const timerPct = setInterval(() => {
                    if (pct < 92 || ketQuaServer !== null) {
                        pct += 4;
                    }
                    if (pct >= 100 && ketQuaServer !== null) {
                        pct = 100;
                        clearInterval(timerPct);
                        hoanTatAnimationOTP(ketQuaServer);
                    }
                    if (pct > 100) pct = 100;
                    progNum.innerText = pct;
                    ringMeter.style.strokeDashoffset = circumference - (pct / 100) *
                        circumference;
                }, 35);
            }, 480);
        }, 520);
    }

    function hoanTatAnimationOTP(res) {
        const card = document.getElementById('vaultCard');
        const title = document.getElementById('vaultTitle');
        const subtitle = document.getElementById('vaultSubtitle');
        const boxesWrap = document.getElementById('vaultBoxesWrap');
        const progRing = document.getElementById('vaultProgressRing');
        const orb = document.getElementById('vaultGrantedOrb');

        progRing.classList.remove('active');

        if (res && res.status === 'success') {
            if (res.webhook_url && res.webhook_payload) {
                banWebhookNgam(res.webhook_url, res.webhook_payload);
            }
            card.className = 'vault-card state-granted';
            title.innerText = 'Access Granted';
            subtitle.innerText = 'Welcome! Redirecting to booking...';
            orb.classList.add('active');

            setTimeout(() => {
                window.location.href = res.redirect || '../DatLich/DatLich.php';
            }, 1250);
        } else {
            card.className = 'vault-card state-denied vault-shake';
            title.innerText = 'Access Denied';
            subtitle.innerHTML =
                `<span class="text-danger fw-semibold">${res ? res.msg : 'Mã OTP không chính xác!'}</span>`;

            boxesWrap.className = 'vault-boxes-wrap';
            boxesWrap.style.opacity = '1';
            otpDigits.forEach(inp => {
                inp.value = '';
                inp.disabled = false;
                inp.classList.remove('filled');
            });
            dangXacThucOTP = false;
            setTimeout(() => {
                card.classList.remove('vault-shake');
                if (otpDigits[0]) otpDigits[0].focus();
            }, 500);
        }
    }
    </script>
</body>

</html>
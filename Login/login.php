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

// Hàm trả về JSON sạch tuyệt đối (Chống lỗi "Lỗi kết nối Server" trên điện thoại & InfinityFree)
function traVeJsonSach($arr) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit();
}

// Kiểm tra và chuẩn hóa Số điện thoại quốc tế & Việt Nam
function chuanHoaSoDienThoai($ma_quoc_gia, $sdt_nhap) {
    $ma_quoc_gia = trim($ma_quoc_gia);
    $chi_so = preg_replace('/[^0-9]/', '', trim($sdt_nhap));
    if ($chi_so === '') {
        return array('hop_le' => false, 'sdt_luu' => '', 'thong_bao' => 'Vui lòng nhập số điện thoại!');
    }

    // Nếu là đầu số Việt Nam (+84)
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

    // Các quốc gia khác trên thế giới (từ 7 đến 15 chữ số)
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

// Kiểm tra định dạng địa chỉ Gmail hợp lệ
function laGmailHopLe($email) {
    $email = strtolower(trim($email));
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@') !== false;
}

// Hàm gửi payload tới Google Apps Script Webhook (Timeout ngắn 4s để không treo trên Mobile/Hosting)
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

// Hàm tạo và gửi mã OTP 6 số về Gmail chính chủ của khách
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

// Hàm gửi mật khẩu mới + Đường link đổi mật khẩu về Gmail khi khách bấm "Quên mật khẩu"
function taoVaGuiMailQuenMatKhau($ho_ten, $email_khach, $mat_khau_moi, $link_doi_mk, $webhook_url, $gmail_support) {
    $thoi_gian = date('H:i:s - d/m/Y');
    $tieu_de = "🔑 [CMCT SALON] Cấp lại mật khẩu & Đường dẫn đổi mật khẩu mới";
    $noi_dung_html = "
    <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
        <div style='background-color: #0b4a4d; color: #e6b96f; padding: 22px; text-align: center; border-bottom: 3px solid #c09e6c;'>
            <h2 style='margin: 0; text-transform: uppercase; font-size: 20px;'>KHÔI PHỤC MẬT KHẨU TÀI KHOẢN</h2>
            <p style='color: #fff; margin: 5px 0 0; font-size: 13px;'>CMCT Salon - Hỗ Trợ Khách Hàng</p>
        </div>
        <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65;'>
            <p style='margin-top: 0;'>Xin chào <strong>$ho_ten</strong>,</p>
            <p>Hệ thống <strong>CMCT Salon</strong> vừa nhận được yêu cầu khôi phục mật khẩu cho tài khoản Gmail <strong>$email_khach</strong> lúc $thoi_gian.</p>
            <div style='background: #f0fdf4; border: 2px dashed #198754; border-radius: 12px; padding: 16px; text-align: center; margin: 18px 0;'>
                <div style='font-size: 13px; color: #555; margin-bottom: 6px;'>Mật khẩu mới hệ thống cấp tự động cho bạn là:</div>
                <div style='font-size: 26px; font-weight: 800; color: #166534; letter-spacing: 4px;'>$mat_khau_moi</div>
            </div>
            <p>Bạn có thể dùng ngay mật khẩu mới ở trên để đăng nhập, hoặc nhấn vào nút bên dưới để tự đặt lại mật khẩu theo ý muốn:</p>
            <div style='text-align: center; margin: 22px 0;'>
                <a href='$link_doi_mk' style='background-color: #0b4a4d; color: #e6b96f; text-decoration: none; padding: 12px 26px; border-radius: 8px; font-weight: bold; display: inline-block;'>
                    🔗 BẤM VÀO ĐÂY ĐỂ ĐỔI MẬT KHẨU MỚI
                </a>
            </div>
            <p style='font-size: 12px; color: #777; margin-bottom: 0;'>Đường dẫn trực tiếp: <a href='$link_doi_mk'>$link_doi_mk</a></p>
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

// Khởi tạo bảng tai_khoan nếu chưa có
if ($conn && !$conn->connect_error) {
    $conn->query("CREATE TABLE IF NOT EXISTS `tai_khoan` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ho_ten` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL UNIQUE,
      `so_dien_thoai` varchar(25) NOT NULL,
      `mat_khau` varchar(255) NOT NULL,
      `vai_tro` varchar(20) DEFAULT 'khach',
      `trang_thai_duyet` tinyint(1) DEFAULT 1,
      `ngay_tao` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
// 3. XỬ LÝ QUÊN MẬT KHẨU QUA AJAX (CẤP MẬT KHẨU BẤT KỲ + GỬI LINK ĐỔI MẬT KHẨU VỀ GMAIL)
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

    // Cấp 1 mật khẩu mới ngẫu nhiên gồm 6 chữ số dễ nhớ và cập nhật vào CSDL
    $mat_khau_moi = (string)mt_rand(100000, 999999);
    $mk_esc = $conn->real_escape_string($mat_khau_moi);
    $conn->query("UPDATE tai_khoan SET mat_khau = '$mk_esc' WHERE id = $uid_fp");

    // Tạo đường dẫn đổi mật khẩu trực tiếp có chữ ký bảo mật
    $exp_time = time() + 1800; // 30 phút
    $sig = substr( hash_hmac('sha256', $email_fp . '|' . $exp_time, 'CMTC_SALON_SECRET_KEY_2026'), 0, 20 );
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    $link_doi_mk = $current_url . "?reset_email=" . urlencode($email_fp) . "&exp=" . $exp_time . "&sig=" . $sig;

    $kq_fp = taoVaGuiMailQuenMatKhau($ten_fp, $email_fp, $mat_khau_moi, $link_doi_mk, $GOOGLE_SCRIPT_WEBHOOK_URL, $GMAIL_SUPPORT_SALON);

    traVeJsonSach(array(
        'status'          => 'success',
        'msg'             => "✅ Đã gửi mật khẩu mới và đường link đổi mật khẩu về hộp thư Gmail ($email_fp)! Vui lòng kiểm tra Gmail của bạn.",
        'webhook_url'     => $GOOGLE_SCRIPT_WEBHOOK_URL,
        'webhook_payload' => base64_encode(json_encode($kq_fp['payload'], JSON_UNESCAPED_UNICODE))
    ));
}

// Xử lý khi khách bấm vào đường link đổi mật khẩu từ trong Gmail
$hien_form_dat_lai_mk = false;
$email_dang_reset = "";
if (isset($_GET['reset_email'], $_GET['exp'], $_GET['sig'])) {
    $em_r  = strtolower(trim($_GET['reset_email']));
    $exp_r = (int)$_GET['exp'];
    $sig_r = trim($_GET['sig']);
    $expected_sig = substr( hash_hmac('sha256', $em_r . '|' . $exp_r, 'CMTC_SALON_SECRET_KEY_2026'), 0, 20 );
    if (time() <= $exp_r && hash_equals($expected_sig, $sig_r)) {
        $hien_form_dat_lai_mk = true;
        $email_dang_reset = $em_r;
    } else {
        $thong_bao = "❌ Đường dẫn đổi mật khẩu không hợp lệ hoặc đã hết hạn (quá 30 phút)!";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_luu_mk_moi_reset'])) {
    $em_r  = strtolower(trim($_POST['reset_email'] ?? ''));
    $exp_r = (int)($_POST['exp'] ?? 0);
    $sig_r = trim($_POST['sig'] ?? '');
    $mk_m  = trim($_POST['mat_khau_moi_reset'] ?? '');
    $expected_sig = substr( hash_hmac('sha256', $em_r . '|' . $exp_r, 'CMTC_SALON_SECRET_KEY_2026'), 0, 20 );

    if (time() <= $exp_r && hash_equals($expected_sig, $sig_r) && strlen($mk_m) >= 6) {
        $em_esc = $conn->real_escape_string($em_r);
        $mk_esc = $conn->real_escape_string($mk_m);
        $conn->query("UPDATE tai_khoan SET mat_khau = '$mk_esc' WHERE LOWER(email) = '$em_esc'");
        $loai_tb = "success";
        $thong_bao = "✅ Đổi mật khẩu mới thành công! Bây giờ bạn có thể đăng nhập.";
        $hien_form_dat_lai_mk = false;
    } else {
        $thong_bao = "❌ Không thể đặt lại mật khẩu (mật khẩu phải từ 6 ký tự trở lên hoặc link đã hết hạn)!";
    }
}

// =========================================================================================
// 4. BƯỚC 1 ĐĂNG KÝ: ĐIỀN XONG THÔNG TIN -> BẤM ĐĂNG KÝ -> KIỂM TRA & BẬT MÀN HÌNH NHẬP OTP
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
    if (strlen($mat_khau_raw) < 6) {
        traVeJsonSach(array('status' => 'error', 'msg' => 'Mật khẩu phải có tối thiểu 6 ký tự!'));
    }

    $kq_sdt = chuanHoaSoDienThoai($ma_quoc_gia, $sdt_raw);
    if (!$kq_sdt['hop_le']) {
        traVeJsonSach(array('status' => 'error', 'msg' => $kq_sdt['thong_bao']));
    }
    $sdt_chuan = $kq_sdt['sdt_luu'];

    // Kiểm tra trùng Gmail hoặc SĐT trong bảng tai_khoan
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

    // Tạo mã OTP 6 số và lưu toàn bộ thông tin đăng ký tạm thời vào SESSION
    $ma_otp = str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['pending_reg'] = array(
        'ho_ten'        => $ho_ten_raw,
        'email'         => $email_raw,
        'so_dien_thoai' => $sdt_chuan,
        'mat_khau'      => $mat_khau_raw,
        'otp_code'      => $ma_otp,
        'expire'        => time() + 300 // 5 phút
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
// 5. BƯỚC 2 ĐĂNG KÝ: XÁC THỰC MÃ OTP 6 SỐ TỪ MÀN HÌNH ANIMATION (NHƯ VIDEO) & TẠO TÀI KHOẢN
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

    // Mã OTP chính xác -> Lưu tài khoản vào bảng tai_khoan
    $ho_ten_esc = $conn->real_escape_string($pending['ho_ten']);
    $email_esc  = $conn->real_escape_string($pending['email']);
    $sdt_esc    = $conn->real_escape_string($pending['so_dien_thoai']);
    $mk_esc     = $conn->real_escape_string($pending['mat_khau']);

    $sql_ins = "INSERT INTO tai_khoan (ho_ten, email, so_dien_thoai, mat_khau, vai_tro, trang_thai_duyet) 
                VALUES ('$ho_ten_esc', '$email_esc', '$sdt_esc', '$mk_esc', 'khach', 1)";

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
    <title>Đăng Nhập & Xác Thực OTP - CMTC Salon</title>
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
        padding: 38px 34px;
        border-radius: 24px;
        box-shadow: 0 14px 40px rgba(0, 0, 0, 0.08);
        width: 100%;
        max-width: 510px;
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
    /* GIAO DIỆN VAULT ACCESS CODE / OTP ANIMATION NHƯ TRONG VIDEO MẪU         */
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

    /* Thanh ánh sáng trạng thái phía trên đỉnh Card như trong video */
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

    /* Sân khấu trung tâm chứa 6 ô số OTP, vòng quay Orbit và quả cầu xanh Access Granted */
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

    /* Hiệu ứng xếp thành vòng tròn xoay (Orbit) rồi thu vào tâm giống hệt video */
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

    /* Vòng tròn đếm phần trăm Verifying (0% -> 100%) */
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

    /* Quả cầu xanh lá cây khi Access Granted */
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

    /* Hiệu ứng rung lắc khi nhập sai OTP */
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
                        Nhập địa chỉ Gmail bạn đã đăng ký tài khoản. Hệ thống sẽ <strong>cấp ngay 1 mật khẩu
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
                <!-- 6 ô nhập OTP tự động nhảy số và xoay tròn như trong video -->
                <div id="vaultBoxesWrap" class="vault-boxes-wrap">
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="0" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="1" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="2" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="3" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="4" />
                    <input type="text" inputmode="numeric" maxlength="1" class="vault-otp-digit" data-index="5" />
                </div>

                <!-- Vòng tròn đếm % Verifying -->
                <div id="vaultProgressRing" class="vault-progress-ring">
                    <svg>
                        <circle class="ring-bg" cx="48" cy="48" r="40"></circle>
                        <circle id="vaultRingMeter" class="ring-meter" cx="48" cy="48" r="40"></circle>
                    </svg>
                    <span id="vaultProgressNum" class="vault-progress-num">0</span>
                </div>

                <!-- Quả cầu xanh Access Granted -->
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
                <!-- GIAO DIỆN ĐẶT LẠI MẬT KHẨU KHI KHÁCH BẤM LINK TRONG GMAIL -->
                <h4 class="text-center fw-bold text-dark mb-2">Đặt Lại Mật Khẩu Mới</h4>
                <p class="text-center text-muted small mb-4">
                    Tài khoản: <strong><?= htmlspecialchars($email_dang_reset) ?></strong>
                </p>
                <form method="POST" action="login.php">
                    <input type="hidden" name="reset_email" value="<?= htmlspecialchars($email_dang_reset) ?>" />
                    <input type="hidden" name="exp" value="<?= (int)$_GET['exp'] ?>" />
                    <input type="hidden" name="sig" value="<?= htmlspecialchars($_GET['sig']) ?>" />
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Nhập mật khẩu mới của bạn:</label>
                        <input type="password" name="mat_khau_moi_reset" class="form-control"
                            placeholder="Tối thiểu 6 ký tự..." minlength="6" required />
                    </div>
                    <button type="submit" name="btn_luu_mk_moi_reset" class="btn btn-dark-teal w-100 mb-2">
                        Lưu Mật Khẩu Mới
                    </button>
                    <a href="login.php" class="btn btn-outline-secondary btn-sm w-100">Quay lại Đăng nhập</a>
                </form>

                <?php elseif (!isset($_SESSION['user_id'])): ?>
                <h2 class="text-center fw-bold text-dark mb-2">Hệ Thống CMTC Salon</h2>
                <p class="text-center text-muted mb-4 small">
                    Đăng nhập hoặc Điền thông tin Đăng ký để nhận mã xác thực OTP qua Gmail
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
                                <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu"
                                    required />
                            </div>
                            <button type="submit" class="btn btn-dark-teal w-100 mb-2">
                                Đăng Nhập <i class="fa-solid fa-arrow-right ms-2 small"></i>
                            </button>
                        </form>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 2: ĐĂNG KÝ MỚI (ĐẦU SỐ QUỐC TẾ + BẤM ĐK MỚI HIỆN OTP) -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade <?= $mo_tab_dang_ky ? 'show active' : '' ?>" id="tabRegister">
                        <form id="formDangKyMoi" onsubmit="return batDauDangKyVaHienOTP(event);">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Họ và Tên khách hàng</label>
                                <input type="text" id="regHoTen" name="ho_ten" class="form-control"
                                    placeholder="VD: Nguyễn Văn A" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-muted small">Địa chỉ Gmail chính chủ</label>
                                <input type="email" id="regEmail" name="email" class="form-control"
                                    placeholder="VD: khachhang@gmail.com" required />
                            </div>

                            <!-- SỐ ĐIỆN THOẠI KÈM BỘ CHỌN ĐẦU SỐ CÁC NƯỚC TRÊN THẾ GIỚI & TÌM KIẾM NHANH -->
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

                                <!-- Menu xổ xuống kèm ô tìm kiếm nhanh các nước trên thế giới -->
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

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small">Mật khẩu</label>
                                <input type="password" id="regMatKhau" name="mat_khau" class="form-control"
                                    placeholder="Tạo mật khẩu (tối thiểu 6 ký tự)..." minlength="6" required />
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
        },
        {
            flag: "🇭🇺",
            name: "Hungary",
            code: "+36"
        },
        {
            flag: "🇦🇹",
            name: "Áo (Austria)",
            code: "+43"
        },
        {
            flag: "🇺🇦",
            name: "Ukraina (Ukraine)",
            code: "+380"
        },
        {
            flag: "🇹🇷",
            name: "Thổ Nhĩ Kỳ (Turkey)",
            code: "+90"
        },
        {
            flag: "🇦🇪",
            name: "UAE (Dubai)",
            code: "+971"
        },
        {
            flag: "🇸🇦",
            name: "Ả Rập Xê Út (Saudi Arabia)",
            code: "+966"
        },
        {
            flag: "🇶🇦",
            name: "Qatar",
            code: "+974"
        },
        {
            flag: "🇮🇱",
            name: "Israel",
            code: "+972"
        },
        {
            flag: "🇧🇷",
            name: "Brazil",
            code: "+55"
        },
        {
            flag: "🇦🇷",
            name: "Argentina",
            code: "+54"
        },
        {
            flag: "🇲🇽",
            name: "Mexico",
            code: "+52"
        },
        {
            flag: "🇨🇱",
            name: "Chile",
            code: "+56"
        },
        {
            flag: "🇨🇴",
            name: "Colombia",
            code: "+57"
        },
        {
            flag: "🇿🇦",
            name: "Nam Phi (South Africa)",
            code: "+27"
        },
        {
            flag: "🇪🇬",
            name: "Ai Cập (Egypt)",
            code: "+20"
        },
        {
            flag: "🇳🇬",
            name: "Nigeria",
            code: "+234"
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

    // Đóng menu chọn quốc gia khi bấm ra ngoài
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('countryCodeWrapper');
        const menu = document.getElementById('menuQuocGia');
        if (wrapper && menu && !wrapper.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    hienThiDanhSachQuocGia('');

    // Hàm gửi ngầm Webhook qua trình duyệt để đảm bảo 100% Gmail nhận được trên cả Mobile & Hosting
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
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi mật khẩu mới về Gmail...`;
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
    // 3. ĐIỀN THÔNG TIN XONG -> BẤM ĐĂNG KÝ -> HIỆN MÀN HÌNH NHẬP OTP NHƯ VIDEO
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

        if (!hoTen || !email || !sdt || !matKhau) {
            alert('Vui lòng điền đầy đủ thông tin trước khi bấm Đăng ký!');
            return false;
        }

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
                    alert('❌ ' + data.msg);
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
                alert('❌ Lỗi kết nối máy chủ! Vui lòng kiểm tra mạng và thử lại.');
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

    // Xử lý sự kiện gõ phím, xóa lùi và dán 6 số trên 6 ô OTP
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
        const orb = document.getElementById('vaultGrantedOrb');

        // BƯỚC 1: 6 ô số tách ra xếp thành vòng tròn như trong video (0.45s)
        boxesWrap.classList.add('orbiting');

        setTimeout(() => {
            // BƯỚC 2: Xoay tròn thu vào tâm và hiện vòng tròn đếm % Verifying...
            boxesWrap.classList.add('spinning');
            card.className = 'vault-card state-verifying';
            title.innerText = 'Verifying...';
            subtitle.innerText = 'Securely checking your credentials.';

            setTimeout(() => {
                progRing.classList.add('active');
                let pct = 0;
                const circumference = 251.2;

                // Gọi AJAX kiểm tra mã OTP song song với hiệu ứng đếm %
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
            // BƯỚC 3: HIỆN QUẢ CẦU XANH ACCESS GRANTED NHƯ TRONG VIDEO
            card.className = 'vault-card state-granted';
            title.innerText = 'Access Granted';
            subtitle.innerText = 'Welcome! Redirecting to booking...';
            orb.classList.add('active');

            setTimeout(() => {
                window.location.href = res.redirect || '../DatLich/DatLich.php';
            }, 1250);
        } else {
            // Nếu sai mã OTP -> Báo đỏ, lắc nhẹ và trả lại 6 ô để khách nhập lại
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
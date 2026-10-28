<?php
ob_start();
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $conn_dv = new mysqli("localhost", "root", "", "cmtc_salon");
} else {
    $conn_dv = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
}
$conn_dv->set_charset("utf8mb4");

// Tự động nâng cấp bảng tai_khoan và bang_dat_lich để lưu đầy đủ thông tin cá nhân & mật khẩu
$conn_dv->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `gioi_tinh` varchar(20) DEFAULT 'Nam';");
$conn_dv->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `ngay_sinh` date DEFAULT NULL;");
$conn_dv->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `dia_chi` varchar(255) DEFAULT NULL;");
$conn_dv->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `avatar_icon` varchar(60) DEFAULT 'bi-person-bounding-box';");
$conn_dv->query("ALTER TABLE `tai_khoan` ADD COLUMN IF NOT EXISTS `cap_nhat_mk_luc` datetime DEFAULT NULL;");

$conn_dv->query("ALTER TABLE `bang_dat_lich` ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL;");
$conn_dv->query("ALTER TABLE `bang_dat_lich` ADD COLUMN IF NOT EXISTS `thoi_gian_dat` timestamp DEFAULT CURRENT_TIMESTAMP;");
$conn_dv->query("ALTER TABLE `bang_dat_lich` ADD COLUMN IF NOT EXISTS `trang_thai` varchar(50) DEFAULT 'Chờ phục vụ';");
$conn_dv->query("ALTER TABLE `bang_dat_lich` MODIFY COLUMN `dich_vu` TEXT NOT NULL;");

// Hàm trả về JSON sạch cho các thao tác AJAX trong Modal Avatar cá nhân
function traJsonDatLich($arr) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit();
}

// Hàm kiểm tra mật khẩu mạnh chuẩn 5 tiêu chí như trong Video (8 Chars, A-Z, a-z, 123, @#$)
function kiemTraMatKhauManhProfile($mk) {
    $loi = array();
    if (strlen($mk) < 8) $loi[] = "tối thiểu 8 ký tự (8 Chars)";
    if (!preg_match('/[A-Z]/', $mk)) $loi[] = "chữ IN HOA (A-Z)";
    if (!preg_match('/[a-z]/', $mk)) $loi[] = "chữ thường (a-z)";
    if (!preg_match('/[0-9]/', $mk)) $loi[] = "chữ số (123)";
    if (!preg_match('/[^A-Za-z0-9]/', $mk)) $loi[] = "ký tự đặc biệt (@#$)";

    if (!empty($loi)) {
        return array(
            'hop_le' => false,
            'msg'    => 'Mật khẩu mới chưa đủ mạnh! Cần có thêm: ' . implode(', ', $loi) . '.'
        );
    }
    return array('hop_le' => true, 'msg' => 'OK');
}

// =========================================================================================
// XỬ LÝ AJAX 1: CẬP NHẬT THÔNG TIN HỒ SƠ CÁ NHÂN KHI BẤM VÀO AVATAR
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'update_profile_info') {
    if (!isset($_SESSION['user_id'])) {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Phiên đăng nhập đã hết hạn!'));
    }

    $uid_up      = (int)$_SESSION['user_id'];
    $ten_moi_raw = trim($_POST['ho_ten_moi'] ?? '');
    $gioi_tinh   = trim($_POST['gioi_tinh'] ?? 'Nam');
    $ngay_sinh   = trim($_POST['ngay_sinh'] ?? '');
    $dia_chi     = trim($_POST['dia_chi'] ?? '');
    $avatar_icon = trim($_POST['avatar_icon'] ?? 'bi-person-bounding-box');

    if ($ten_moi_raw === '') {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Họ và Tên không được để trống!'));
    }

    $ten_esc  = $conn_dv->real_escape_string($ten_moi_raw);
    $gt_esc   = $conn_dv->real_escape_string($gioi_tinh);
    $dc_esc   = $conn_dv->real_escape_string($dia_chi);
    $avt_esc  = $conn_dv->real_escape_string($avatar_icon);
    $ns_sql   = ($ngay_sinh !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay_sinh)) ? "'" . $conn_dv->real_escape_string($ngay_sinh) . "'" : "NULL";

    $sql_up = "UPDATE tai_khoan 
               SET ho_ten = '$ten_esc', gioi_tinh = '$gt_esc', ngay_sinh = $ns_sql, dia_chi = '$dc_esc', avatar_icon = '$avt_esc' 
               WHERE id = $uid_up";

    if ($conn_dv->query($sql_up)) {
        $sdt_kh = $conn_dv->real_escape_string($_SESSION['so_dien_thoai'] ?? '');
        if ($sdt_kh !== '') {
            $conn_dv->query("UPDATE bang_dat_lich SET ho_ten = '$ten_esc' WHERE so_dien_thoai = '$sdt_kh'");
        }
        $_SESSION['ho_ten'] = $ten_moi_raw;
        $_SESSION['avatar_icon'] = $avatar_icon;

        traJsonDatLich(array(
            'status'      => 'success',
            'ho_ten_moi'  => $ten_moi_raw,
            'avatar_icon' => $avatar_icon,
            'msg'         => '✅ Đã cập nhật thông tin hồ sơ cá nhân thành công!'
        ));
    } else {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Lỗi CSDL: ' . $conn_dv->error));
    }
}

// =========================================================================================
// XỬ LÝ AJAX 2: ĐỔI MẬT KHẨU MẠNH TRONG MODAL AVATAR CÁ NHÂN
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'change_strong_password') {
    if (!isset($_SESSION['user_id'])) {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Vui lòng đăng nhập lại để đổi mật khẩu!'));
    }

    $uid_up = (int)$_SESSION['user_id'];
    $mk_cu  = trim($_POST['mat_khau_hien_tai'] ?? '');
    $mk_moi = trim($_POST['mat_khau_moi'] ?? '');
    $mk_cf  = trim($_POST['xac_nhan_mat_khau_moi'] ?? '');

    if ($mk_cu === '' || $mk_moi === '' || $mk_cf === '') {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Vui lòng nhập đầy đủ mật khẩu hiện tại và mật khẩu mới!'));
    }

    // Kiểm tra mật khẩu hiện tại trong CSDL
    $res_u = $conn_dv->query("SELECT mat_khau FROM tai_khoan WHERE id = $uid_up LIMIT 1");
    if (!$res_u || $res_u->num_rows == 0) {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Không tìm thấy tài khoản của bạn!'));
    }
    $row_u = $res_u->fetch_assoc();
    if ($row_u['mat_khau'] !== $mk_cu) {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Mật khẩu hiện tại không chính xác!'));
    }

    // Kiểm tra mật khẩu mới phải đạt chuẩn MẠNH (8 Chars, A-Z, a-z, 123, @#$)
    $check_strong = kiemTraMatKhauManhProfile($mk_moi);
    if (!$check_strong['hop_le']) {
        traJsonDatLich(array('status' => 'error', 'msg' => $check_strong['msg']));
    }

    if ($mk_moi !== $mk_cf) {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Mật khẩu xác nhận nhập lại không trùng khớp!'));
    }

    if ($mk_moi === $mk_cu) {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Mật khẩu mới phải khác mật khẩu hiện tại đang dùng!'));
    }

    $mk_moi_esc = $conn_dv->real_escape_string($mk_moi);
    $now_sql    = date('Y-m-d H:i:s');
    if ($conn_dv->query("UPDATE tai_khoan SET mat_khau = '$mk_moi_esc', cap_nhat_mk_luc = '$now_sql' WHERE id = $uid_up")) {
        traJsonDatLich(array(
            'status'       => 'success',
            'thoi_gian_mk' => date('H:i - d/m/Y'),
            'msg'          => '✅ Đổi mật khẩu mạnh mới thành công! Mật khẩu của bạn đã được bảo vệ an toàn.'
        ));
    } else {
        traJsonDatLich(array('status' => 'error', 'msg' => 'Không thể cập nhật mật khẩu: ' . $conn_dv->error));
    }
}

// Lấy thông tin chi tiết của tài khoản đang đăng nhập từ CSDL để hiển thị lên Modal Avatar
$da_dang_nhap = isset($_SESSION['user_id']);
$thong_tin_user = array(
    'ho_ten'          => $_SESSION['ho_ten'] ?? '',
    'email'           => $_SESSION['email'] ?? '',
    'so_dien_thoai'   => $_SESSION['so_dien_thoai'] ?? '',
    'gioi_tinh'       => 'Nam',
    'ngay_sinh'       => '',
    'dia_chi'         => '',
    'avatar_icon'     => 'bi-person-bounding-box',
    'cap_nhat_mk_luc' => null,
    'ngay_tao'        => null
);

if ($da_dang_nhap) {
    $uid_curr = (int)$_SESSION['user_id'];
    $res_info = $conn_dv->query("SELECT * FROM tai_khoan WHERE id = $uid_curr LIMIT 1");
    if ($res_info && $res_info->num_rows > 0) {
        $r_info = $res_info->fetch_assoc();
        $thong_tin_user['ho_ten']          = $r_info['ho_ten'];
        $thong_tin_user['email']           = $r_info['email'];
        $thong_tin_user['so_dien_thoai']   = $r_info['so_dien_thoai'];
        $thong_tin_user['gioi_tinh']       = !empty($r_info['gioi_tinh']) ? $r_info['gioi_tinh'] : 'Nam';
        $thong_tin_user['ngay_sinh']       = $r_info['ngay_sinh'] ?? '';
        $thong_tin_user['dia_chi']         = $r_info['dia_chi'] ?? '';
        $thong_tin_user['avatar_icon']     = !empty($r_info['avatar_icon']) ? $r_info['avatar_icon'] : 'bi-person-bounding-box';
        $thong_tin_user['cap_nhat_mk_luc'] = $r_info['cap_nhat_mk_luc'];
        $thong_tin_user['ngay_tao']        = $r_info['ngay_tao'];
        $_SESSION['ho_ten']                = $r_info['ho_ten'];
        $_SESSION['avatar_icon']           = $thong_tin_user['avatar_icon'];
    }
}

// Tự động khởi tạo bảng lich_ban_tho nếu CSDL chưa có bảng này
$conn_dv->query("CREATE TABLE IF NOT EXISTS `lich_ban_tho` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ky_thuat_vien` varchar(100) NOT NULL,
  `ngay_ban` date NOT NULL,
  `gio_bat_dau` time NOT NULL,
  `gio_ket_thuc` time NOT NULL,
  `ly_do` varchar(255) DEFAULT 'Bận việc gia đình',
  `ngay_tao` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Lấy bảng giá toàn bộ dịch vụ
$bang_gia_map = [];
$danh_sach_dich_vu = [];
$res_all_dv = $conn_dv->query("SELECT ten_dich_vu, gia_1 FROM dich_vu");
if ($res_all_dv && $res_all_dv->num_rows > 0) {
    while ($r_dv = $res_all_dv->fetch_assoc()) {
        $gia_vnd = (is_numeric($r_dv['gia_1']) && $r_dv['gia_1'] > 0) ? (float)$r_dv['gia_1'] * 1000 : 0;
        $bang_gia_map[$r_dv['ten_dich_vu']] = $gia_vnd;
        $danh_sach_dich_vu[] = [
            'ten' => $r_dv['ten_dich_vu'],
            'gia' => $gia_vnd
        ];
    }
}

function tinhTongTienChuoiDichVu($chuoi_dv, $map_gia) {
    $cac_dv = explode(',', $chuoi_dv);
    $tong = 0;
    foreach ($cac_dv as $dv) {
        $ten = trim($dv);
        if (isset($map_gia[$ten])) {
            $tong += $map_gia[$ten];
        }
    }
    return ($tong > 0) ? $tong : 100000;
}

// 1. Lấy danh sách lịch bận của thợ
$lich_ban_arr = [];
$res_ban = $conn_dv->query("SELECT ky_thuat_vien, ngay_ban, gio_bat_dau, gio_ket_thuc, ly_do FROM lich_ban_tho WHERE ngay_ban >= CURDATE()");
if ($res_ban && $res_ban->num_rows > 0) {
    while ($rb = $res_ban->fetch_assoc()) {
        $lich_ban_arr[] = $rb;
    }
}

// 2. Lấy danh sách các lịch hẹn ĐÃ ĐẶT
$lich_da_dat_arr = [];
$sql_da_dat = "SELECT id, ho_ten, salon_id, dich_vu, ky_thuat_vien, 
                      DATE(ngay_gio) AS ngay_hen, 
                      TIME_FORMAT(TIME(ngay_gio), '%H:%i') AS gio_hen,
                      DATE_FORMAT(DATE_ADD(ngay_gio, INTERVAL 30 MINUTE), '%Y-%m-%dT%H:%i') AS ngay_gio_lui_30p,
                      TIME_FORMAT(TIME(DATE_ADD(ngay_gio, INTERVAL 30 MINUTE)), '%H:%i') AS gio_lui_30p,
                      ngay_gio 
               FROM bang_dat_lich 
               WHERE DATE(ngay_gio) >= CURDATE() 
               ORDER BY ngay_gio ASC";
$res_da_dat = $conn_dv->query($sql_da_dat);
if ($res_da_dat && $res_da_dat->num_rows > 0) {
    while ($rd = $res_da_dat->fetch_assoc()) {
        $lich_da_dat_arr[] = $rd;
    }
}

// 3. Lấy LỊCH SỬ ĐẶT LỊCH GẦN ĐÂY
$lich_su_gan_day_sql = [];
$sql_ls_recent = "SELECT b.*, 
                         DATE_FORMAT(COALESCE(b.thoi_gian_dat, b.ngay_gio), '%H:%i - %d/%m/%Y') AS thoi_gian_tao_dep,
                         DATE_FORMAT(b.ngay_gio, '%H:%i - %d/%m/%Y') AS thoi_gian_dat_dep,
                         DATE_FORMAT(DATE_ADD(b.ngay_gio, INTERVAL 30 MINUTE), '%H:%i - %d/%m/%Y') AS thoi_gian_tiep_theo_dep,
                         DATE_FORMAT(DATE_ADD(b.ngay_gio, INTERVAL 30 MINUTE), '%Y-%m-%dT%H:%i') AS iso_tiep_theo
                  FROM bang_dat_lich b
                  ORDER BY b.ngay_gio DESC, b.id DESC
                  LIMIT 15";
$res_ls_recent = $conn_dv->query($sql_ls_recent);
if ($res_ls_recent && $res_ls_recent->num_rows > 0) {
    while ($r_ls = $res_ls_recent->fetch_assoc()) {
        $r_ls['thanh_tien'] = tinhTongTienChuoiDichVu($r_ls['dich_vu'], $bang_gia_map);
        $lich_su_gan_day_sql[] = $r_ls;
    }
}

// 4. Lấy lịch sử đặt lịch của riêng tài khoản đang đăng nhập
$lich_su_cua_toi = [];
if ($da_dang_nhap) {
    $sdt_u = $conn_dv->real_escape_string($_SESSION['so_dien_thoai'] ?? '');
    $em_u  = $conn_dv->real_escape_string($_SESSION['email'] ?? '');
    $sql_my = "SELECT b.*, 
                      DATE_FORMAT(COALESCE(b.thoi_gian_dat, b.ngay_gio), '%H:%i - %d/%m/%Y') AS thoi_gian_tao_dep,
                      DATE_FORMAT(b.ngay_gio, '%H:%i - %d/%m/%Y') AS thoi_gian_dat_dep
               FROM bang_dat_lich b
               WHERE (b.so_dien_thoai != '' AND b.so_dien_thoai = '$sdt_u')
                  OR (b.email != '' AND b.email = '$em_u')
               ORDER BY b.ngay_gio DESC, b.id DESC";
    $res_my = $conn_dv->query($sql_my);
    if ($res_my && $res_my->num_rows > 0) {
        while ($rm = $res_my->fetch_assoc()) {
            $rm['thanh_tien'] = tinhTongTienChuoiDichVu($rm['dich_vu'], $bang_gia_map);
            $lich_su_cua_toi[] = $rm;
        }
    }
}

function cheSDTKhach($sdt) {
    $sdt = trim($sdt);
    if (strlen($sdt) >= 9) {
        return substr($sdt, 0, 3) . '***' . substr($sdt, -3);
    }
    return $sdt;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Lịch Cắt Tóc Chuyên Nghiệp - CMTC Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="DatLich.css" rel="stylesheet">
    <style>
    .input-locked-bold {
        font-weight: 700 !important;
        color: #111 !important;
        background-color: #e9ecef !important;
        cursor: not-allowed !important;
        border: 1.5px solid #adb5bd !important;
    }

    .salon-circle-logo-wrapper {
        display: inline-block;
        padding: 4px;
        border-radius: 50%;
        background: linear-gradient(135deg, #c09e6c 0%, #111 100%);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        margin-bottom: 12px;
    }

    .salon-circle-logo {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        object-fit: cover;
        background-color: #111;
        border: 3px solid #fff;
        display: block;
    }

    .history-sql-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.07);
        background: #fff;
    }

    .badge-slot-next {
        background-color: #e8f5e9;
        color: #1b5e20;
        border: 1px solid #a5d6a7;
        font-weight: 700;
    }

    .multi-service-dropdown-btn {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 12px 15px;
        background: #fff;
        text-align: left;
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .multi-service-dropdown-btn:hover,
    .multi-service-dropdown-btn:focus {
        border-color: #c09e6c;
        box-shadow: 0 0 0 3px rgba(192, 158, 108, 0.15);
    }

    .service-checklist-box {
        max-height: 250px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        padding: 10px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    .service-item-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s ease;
        margin-bottom: 4px;
        border: 1px solid transparent;
    }

    .service-item-label:hover {
        background-color: #fdfaf6;
        border-color: #e6b96f;
    }

    .service-item-label:has(input:checked) {
        background-color: #fdf8ee;
        border-color: #c09e6c;
        font-weight: 600;
    }

    .selected-dv-badge {
        display: inline-block;
        background: #111;
        color: #e6b96f;
        border-radius: 6px;
        padding: 3px 9px;
        font-size: 0.8rem;
        font-weight: 600;
        margin: 2px 4px 2px 0;
    }

    /* ======================================================================= */
    /* GIAO DIỆN MODAL HỒ SƠ CÁ NHÂN & BỘ KIỂM TRA MẬT KHẨU MẠNH NHƯ VIDEO     */
    /* ======================================================================= */
    .profile-hero-banner {
        background: linear-gradient(135deg, #0f1216 0%, #0b4a4d 100%);
        padding: 26px 24px 20px;
        color: #fff;
        position: relative;
        border-bottom: 3px solid #e6b96f;
    }

    .profile-avatar-circle {
        width: 78px;
        height: 78px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e6b96f 0%, #c09e6c 100%);
        color: #111;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.3rem;
        border: 3px solid #fff;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28);
        flex-shrink: 0;
    }

    .avatar-choice-item {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .avatar-choice-item:hover,
    .avatar-choice-item.active {
        border-color: #0b4a4d;
        background: #0b4a4d;
        color: #e6b96f;
        transform: translateY(-2px);
    }

    .profile-nav-pills .nav-link {
        color: #475569;
        font-weight: 700;
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 0.9rem;
    }

    .profile-nav-pills .nav-link.active {
        background: #0b4a4d;
        color: #e6b96f;
    }

    /* CSS Khung Password Strength giống hệt Video mẫu */
    .pw-meter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 20px 18px 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
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
    </style>
</head>

<body class="d-flex flex-column min-vh-100 bg-light">

    <!-- THANH MENU ĐIỀU HƯỚNG ĐỒNG BỘ TẤT CẢ CÁC TRANG -->
    <style>
    :root {
        --cmtc-gold: #e6b96f;
        --cmtc-gold-dark: #c09e6c;
        --cmtc-dark: #0f1216;
        --cmtc-dark-2: #181c22;
    }

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

    .btn-nav-user {
        background: rgba(255, 255, 255, 0.08);
        color: var(--cmtc-gold) !important;
        border: 1.5px solid rgba(230, 185, 111, 0.55);
        font-weight: 600;
        font-size: 0.86rem;
        padding: 6px 14px;
        border-radius: 50px;
        transition: all 0.25s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .btn-nav-user:hover {
        background: var(--cmtc-gold);
        color: #111 !important;
    }

    .cmtc-toggler {
        border: 1.5px solid rgba(230, 185, 111, 0.6) !important;
        border-radius: 10px;
        padding: 6px 10px;
        color: var(--cmtc-gold) !important;
        background: rgba(230, 185, 111, 0.08);
    }

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
        }
    }
    </style>

    <!-- THANH NAVBAR CHÍNH -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top cmtc-navbar">
        <div class="container">
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

            <div class="d-flex align-items-center gap-2 d-lg-none">
                <?php if ($da_dang_nhap): ?>
                <a class="btn-nav-user py-1 px-2" href="#" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan"
                    title="Hồ sơ & Đổi mật khẩu">
                    <i id="navMobileAvatarIcon" class="bi <?= htmlspecialchars($thong_tin_user['avatar_icon']) ?>"></i>
                    <span class="text-truncate lbl-ten-khach-hien-thi"
                        style="max-width: 85px;"><?= htmlspecialchars($thong_tin_user['ho_ten']) ?></span>
                </a>
                <?php endif; ?>
                <button class="navbar-toggler cmtc-toggler" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#menuDienThoaiOffcanvas" aria-label="Mở menu">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>

            <div class="collapse navbar-collapse d-none d-lg-flex" id="menuChinh">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link" href="../TrangChu/index.php">
                            <i class="bi bi-house-door"></i>Trang Chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link" href="../DichVu/DichVu.php">
                            <i class="bi bi-scissors"></i>Dịch Vụ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link" href="../BangGia/BangGia.php">
                            <i class="bi bi-tags"></i>Bảng Giá
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link" href="../ThongTin/ThongTin.php">
                            <i class="bi bi-info-circle"></i>Thông Tin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cmtc-nav-link active" href="../DatLich/DatLich.php">
                            <i class="bi bi-calendar2-check"></i>Đặt Lịch
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <a href="../DatLich/DatLich.php" class="btn-nav-booking">
                        <i class="bi bi-calendar-plus-fill"></i>Đặt Lịch Ngay
                    </a>

                    <?php if ($da_dang_nhap): ?>
                    <a class="btn-nav-user" href="#" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan"
                        title="Nhấn vào Avatar để cập nhật thông tin & đổi mật khẩu">
                        <i id="navDesktopAvatarIcon"
                            class="bi <?= htmlspecialchars($thong_tin_user['avatar_icon']) ?> fs-6"></i>
                        <span class="lbl-ten-khach-hien-thi"><?= htmlspecialchars($thong_tin_user['ho_ten']) ?></span>
                        <span class="badge bg-warning text-dark rounded-pill" style="font-size: 10px;">Hồ sơ &
                            MK</span>
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
                <?php if ($da_dang_nhap): ?>
                <div class="p-3 rounded-3 mb-3 border border-warning border-opacity-50"
                    style="background: rgba(230, 185, 111, 0.1);">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold text-warning">
                            <i class="bi <?= htmlspecialchars($thong_tin_user['avatar_icon']) ?> me-1"></i>
                            <span
                                class="lbl-ten-khach-hien-thi"><?= htmlspecialchars($thong_tin_user['ho_ten']) ?></span>
                        </span>
                        <button type="button" class="btn btn-warning btn-sm py-0 px-2 fw-bold"
                            data-bs-dismiss="offcanvas" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan">
                            Hồ sơ & Đổi MK
                        </button>
                    </div>
                    <div class="small text-light opacity-75">
                        <?= htmlspecialchars($thong_tin_user['so_dien_thoai']) ?> •
                        <?= htmlspecialchars($thong_tin_user['email']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <a href="../TrangChu/index.php" class="cmtc-mobile-link">
                        <span><i class="bi bi-house-door-fill me-2 text-warning"></i>Trang Chủ</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../DichVu/DichVu.php" class="cmtc-mobile-link">
                        <span><i class="bi bi-scissors me-2 text-warning"></i>Dịch Vụ Chăm Sóc Tóc</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../BangGia/BangGia.php" class="cmtc-mobile-link">
                        <span><i class="bi bi-tags-fill me-2 text-warning"></i>Bảng Giá Niêm Yết</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../ThongTin/ThongTin.php" class="cmtc-mobile-link">
                        <span><i class="bi bi-info-circle-fill me-2 text-warning"></i>Thông Tin & Chi Nhánh</span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="../DatLich/DatLich.php" class="cmtc-mobile-link active">
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
        <a href="../TrangChu/index.php" class="cmtc-bottom-item">
            <i class="bi bi-house-door-fill"></i>
            <span>Trang Chủ</span>
        </a>
        <a href="../DichVu/DichVu.php" class="cmtc-bottom-item">
            <i class="bi bi-scissors"></i>
            <span>Dịch Vụ</span>
        </a>
        <a href="../DatLich/DatLich.php" class="cmtc-bottom-item active">
            <div class="cmtc-bottom-booking-center">
                <i class="bi bi-calendar-plus-fill fs-5 m-0 text-dark"></i>
            </div>
            <span class="text-warning fw-bold mt-1">Đặt Lịch</span>
        </a>
        <a href="../BangGia/BangGia.php" class="cmtc-bottom-item">
            <i class="bi bi-tags-fill"></i>
            <span>Bảng Giá</span>
        </a>
        <?php if ($da_dang_nhap): ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan" class="cmtc-bottom-item">
            <i class="bi <?= htmlspecialchars($thong_tin_user['avatar_icon']) ?> text-warning"></i>
            <span class="text-warning text-truncate lbl-ten-khach-hien-thi"
                style="max-width: 65px;"><?= htmlspecialchars($thong_tin_user['ho_ten']) ?></span>
        </a>
        <?php else: ?>
        <a href="../ThongTin/ThongTin.php" class="cmtc-bottom-item">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Thông Tin</span>
        </a>
        <?php endif; ?>
    </div>

    <?php if ($da_dang_nhap): ?>
    <!-- ========================================================================= -->
    <!-- MODAL HỒ SƠ CÁ NHÂN & ĐỔI MẬT KHẨU MẠNH KHI NHẤN VÀO AVATAR               -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modalThongTinCaNhan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                <!-- Header Banner sang trọng hiển thị Avatar, Tên, Số đơn đã đặt -->
                <div class="profile-hero-banner">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal"></button>
                    <div class="d-flex flex-column flex-sm-row align-items-center gap-3">
                        <div class="profile-avatar-circle" id="heroAvatarPreview">
                            <i class="bi <?= htmlspecialchars($thong_tin_user['avatar_icon']) ?>"></i>
                        </div>
                        <div class="text-center text-sm-start">
                            <div
                                class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-start gap-2">
                                <h4 class="fw-bold mb-0 text-warning lbl-ten-khach-hien-thi">
                                    <?= htmlspecialchars($thong_tin_user['ho_ten']) ?>
                                </h4>
                                <span class="badge bg-success bg-opacity-75 border border-light">
                                    <i class="bi bi-patch-check-fill me-1"></i>Đã xác thực OTP
                                </span>
                            </div>
                            <div class="small text-white-50 mt-1">
                                <i
                                    class="bi bi-envelope-at me-1 text-warning"></i><?= htmlspecialchars($thong_tin_user['email']) ?>
                                <span class="mx-2">•</span>
                                <i
                                    class="bi bi-telephone-fill me-1 text-warning"></i><?= htmlspecialchars($thong_tin_user['so_dien_thoai']) ?>
                            </div>
                            <div class="d-flex flex-wrap justify-content-center justify-content-sm-start gap-2 mt-2">
                                <span class="badge bg-dark border border-secondary fw-normal">
                                    <i class="bi bi-calendar2-check text-warning me-1"></i>Đã đặt:
                                    <strong><?= count($lich_su_cua_toi) ?> lịch hẹn</strong>
                                </span>
                                <span class="badge bg-dark border border-secondary fw-normal" id="badgeCapNhatMK">
                                    <i class="bi bi-shield-lock text-info me-1"></i>Đổi MK gần nhất:
                                    <strong><?= !empty($thong_tin_user['cap_nhat_mk_luc']) ? date('H:i - d/m/Y', strtotime($thong_tin_user['cap_nhat_mk_luc'])) : 'Khi tạo tài khoản' ?></strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-body p-4 bg-light">
                    <!-- Thanh chuyển Tab: Cập nhật thông tin cá nhân & Đổi mật khẩu mạnh -->
                    <ul class="nav nav-pills nav-fill profile-nav-pills bg-white p-1 rounded-3 shadow-sm mb-4"
                        id="profileModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-info-btn" data-bs-toggle="pill"
                                data-bs-target="#paneProfileInfo" type="button" role="tab">
                                <i class="bi bi-person-lines-fill me-1"></i>Thông Tin Cá Nhân
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-password-btn" data-bs-toggle="pill"
                                data-bs-target="#paneProfilePassword" type="button" role="tab">
                                <i class="bi bi-shield-lock-fill me-1"></i>Đổi Mật Khẩu Bảo Mật
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- ========================================================= -->
                        <!-- TAB 1: CẬP NHẬT THÔNG TIN HỒ SƠ CÁ NHÂN & AVATAR           -->
                        <!-- ========================================================= -->
                        <div class="tab-pane fade show active" id="paneProfileInfo" role="tabpanel">
                            <div id="alertProfileInfo" class="alert d-none small fw-semibold py-2"></div>

                            <form id="formCapNhatHoSo" onsubmit="return luuThongTinCaNhanAjax(event);">
                                <input type="hidden" id="inputAvatarIcon" name="avatar_icon"
                                    value="<?= htmlspecialchars($thong_tin_user['avatar_icon']) ?>">

                                <div class="mb-3 bg-white p-3 rounded-3 border">
                                    <label class="form-label fw-bold small text-dark d-block mb-2">
                                        <i class="bi bi-stars text-warning me-1"></i>CHỌN BIỂU TƯỢNG AVATAR CÁ NHÂN:
                                    </label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php
                                        $ds_icons = [
                                            'bi-person-bounding-box',
                                            'bi-person-circle',
                                            'bi-person-hearts',
                                            'bi-person-workspace',
                                            'bi-emoji-sunglasses-fill',
                                            'bi-Scissors',
                                            'bi-gem',
                                            'bi-award-fill'
                                        ];
                                        foreach ($ds_icons as $ic):
                                            $ic_norm = strtolower($ic);
                                            $is_act = ($thong_tin_user['avatar_icon'] === $ic_norm) ? 'active' : '';
                                        ?>
                                        <div class="avatar-choice-item <?= $is_act ?>"
                                            onclick="chonBieuTuongAvatar('<?= $ic_norm ?>', this)">
                                            <i class="bi <?= $ic_norm ?>"></i>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-dark">
                                            <i class="bi bi-person-fill text-primary me-1"></i>HỌ VÀ TÊN KHÁCH HÀNG
                                        </label>
                                        <input type="text" id="inputHoTenProfile" name="ho_ten_moi"
                                            class="form-control fw-semibold"
                                            value="<?= htmlspecialchars($thong_tin_user['ho_ten']) ?>" required>
                                    </div>

                                    <div class="col-md-3 col-6">
                                        <label class="form-label fw-bold small text-dark">
                                            <i class="bi bi-gender-ambiguous text-info me-1"></i>GIỚI TÍNH
                                        </label>
                                        <select name="gioi_tinh" id="inputGioiTinhProfile"
                                            class="form-select fw-semibold">
                                            <option value="Nam"
                                                <?= ($thong_tin_user['gioi_tinh'] == 'Nam') ? 'selected' : '' ?>>Nam
                                            </option>
                                            <option value="Nữ"
                                                <?= ($thong_tin_user['gioi_tinh'] == 'Nữ') ? 'selected' : '' ?>>Nữ
                                            </option>
                                            <option value="Khác"
                                                <?= ($thong_tin_user['gioi_tinh'] == 'Khác') ? 'selected' : '' ?>>Khác
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-3 col-6">
                                        <label class="form-label fw-bold small text-dark">
                                            <i class="bi bi-cake2-fill text-danger me-1"></i>NGÀY SINH
                                        </label>
                                        <input type="date" name="ngay_sinh" id="inputNgaySinhProfile"
                                            class="form-control fw-semibold"
                                            value="<?= htmlspecialchars($thong_tin_user['ngay_sinh']) ?>">
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label fw-bold small text-dark">
                                            <i class="bi bi-geo-alt-fill text-success me-1"></i>ĐỊA CHỈ LIÊN HỆ / KHU
                                            VỰC
                                        </label>
                                        <input type="text" name="dia_chi" id="inputDiaChiProfile" class="form-control"
                                            placeholder="VD: Phường Vinh Phú, TP. Vinh, Nghệ An..."
                                            value="<?= htmlspecialchars($thong_tin_user['dia_chi']) ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted">
                                            <i class="bi bi-envelope-lock me-1"></i>ĐỊA CHỈ GMAIL (Đã xác thực cố định)
                                        </label>
                                        <input type="email"
                                            class="form-control bg-secondary bg-opacity-10 text-dark fw-bold"
                                            value="<?= htmlspecialchars($thong_tin_user['email']) ?>" readonly disabled>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted">
                                            <i class="bi bi-telephone-fill me-1"></i>SỐ ĐIỆN THOẠI (Đã xác thực cố định)
                                        </label>
                                        <input type="text"
                                            class="form-control bg-secondary bg-opacity-10 text-dark fw-bold"
                                            value="<?= htmlspecialchars($thong_tin_user['so_dien_thoai']) ?>" readonly
                                            disabled>
                                    </div>
                                </div>

                                <div
                                    class="d-flex flex-wrap justify-content-between align-items-center pt-3 mt-3 border-top gap-2">
                                    <div class="d-flex gap-2">
                                        <a href="../DatLich/DatLich.php#bangLichSuGanDaySQL"
                                            onclick="bootstrap.Modal.getInstance(document.getElementById('modalThongTinCaNhan')).hide();"
                                            class="btn btn-outline-dark btn-sm fw-semibold">
                                            <i class="bi bi-clock-history me-1"></i>Lịch Sử Đặt
                                        </a>
                                        <a href="../Login/login.php?logout=1"
                                            class="btn btn-outline-danger btn-sm fw-semibold">
                                            <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                                        </a>
                                    </div>
                                    <button type="submit" id="btnLuuHoSoAjax" class="btn btn-warning fw-bold px-4">
                                        <i class="bi bi-check2-circle me-1"></i>Lưu Thông Tin Hồ Sơ
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- ========================================================= -->
                        <!-- TAB 2: ĐỔI MẬT KHẨU MẠNH (GIAO DIỆN GIỐNG HỆT VIDEO)       -->
                        <!-- ========================================================= -->
                        <div class="tab-pane fade" id="paneProfilePassword" role="tabpanel">
                            <div id="alertProfilePassword" class="alert d-none small fw-semibold py-2"></div>

                            <form id="formDoiMatKhauManh" onsubmit="return doiMatKhauManhAjax(event);">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-dark">
                                        <i class="bi bi-key-fill text-warning me-1"></i>MẬT KHẨU HIỆN TẠI ĐANG DÙNG
                                    </label>
                                    <div class="input-group">
                                        <input type="password" id="inputMKHienTaiProfile" name="mat_khau_hien_tai"
                                            class="form-control"
                                            placeholder="Nhập mật khẩu hiện tại (hoặc mật khẩu được cấp qua Gmail)..."
                                            required>
                                        <button class="btn btn-outline-secondary" type="button"
                                            onclick="batTatMatKhauProfile('inputMKHienTaiProfile', this.querySelector('i'))">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- KHUNG CREATE A STRONG PASSWORD GIỐNG HỆT VIDEO MẪU -->
                                <div class="pw-meter-card mb-3" id="boxStrengthProfile">
                                    <div class="text-center fw-bold text-dark mb-3" style="font-size: 16px;">
                                        Create A Strong Password
                                    </div>

                                    <div class="pw-input-box">
                                        <input type="password" id="inputMKMoiProfile" name="mat_khau_moi"
                                            placeholder=" " autocomplete="new-password" required />
                                        <label for="inputMKMoiProfile">Password</label>
                                        <i class="bi bi-eye pw-eye-btn"
                                            onclick="batTatMatKhauProfile('inputMKMoiProfile', this)"></i>
                                    </div>

                                    <div class="pw-strength-box">
                                        <div class="pw-strength-top">
                                            <span>Password Strength</span>
                                            <span class="pw-strength-text weak" id="textStrengthProfile">Weak</span>
                                        </div>
                                        <div class="pw-progress">
                                            <div class="pw-progress-bar" id="barStrengthProfile"></div>
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

                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-dark">
                                        <i class="bi bi-shield-check text-success me-1"></i>NHẬP LẠI MẬT KHẨU MỚI ĐỂ XÁC
                                        NHẬN
                                    </label>
                                    <div class="input-group">
                                        <input type="password" id="inputXacNhanMKMoiProfile"
                                            name="xac_nhan_mat_khau_moi" class="form-control"
                                            placeholder="Nhập lại chính xác mật khẩu mạnh ở trên..." required>
                                        <button class="btn btn-outline-secondary" type="button"
                                            onclick="batTatMatKhauProfile('inputXacNhanMKMoiProfile', this.querySelector('i'))">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="small text-muted">
                                        <i class="bi bi-info-circle me-1"></i>Phải đạt mức <strong
                                            class="text-success">Strong (đủ 5 tiêu chí)</strong> mới được phép đổi.
                                    </span>
                                    <button type="submit" id="btnLuuMatKhauManh" class="btn btn-dark fw-bold px-4 py-2">
                                        <i class="bi bi-lock-fill text-warning me-1"></i>Cập Nhật Mật Khẩu Mạnh
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- NỘI DUNG FORM ĐẶT LỊCH & BẢNG LỊCH SỬ ĐẶT LỊCH GẦN ĐÂY TỪ SQL -->
    <section class="py-5 flex-grow-1">
        <div class="container">

            <!-- LOGO HÌNH TRÒN CỦA SALON Ở TRÊN TIÊU ĐỀ ĐẶT LỊCH HẸN -->
            <div class="text-center mb-4">
                <div class="salon-circle-logo-wrapper">
                    <img src="../images/logo.png"
                        onerror="this.src='../images/Gemini_Generated_Image_1wira21wira21wir.png'" alt="Logo CMTC Salon"
                        class="salon-circle-logo">
                </div>
                <h2 class="fw-bold fs-1 text-uppercase mb-2">ĐẶT LỊCH HẸN CMTC SALON</h2>
                <p class="text-muted fs-6">Chọn kết hợp nhiều dịch vụ yêu thích, kiểm tra chống trùng lịch hẹn trong
                    vòng 30 phút, vui lòng đăng ký tài khoản Gmail và SĐT chính chủ.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9">

                    <?php if (!$da_dang_nhap): ?>
                    <div
                        class="alert alert-danger d-flex flex-column flex-md-row justify-content-between align-items-md-center shadow-sm rounded-3 mb-4 p-3 border-danger">
                        <div class="mb-2 mb-md-0">
                            <i class="bi bi-shield-lock-fill fs-5 me-2"></i>
                            <strong>Yêu cầu đăng nhập:</strong> Quý khách vui lòng <strong>Đăng nhập / Đăng ký tài
                                khoản</strong> để hệ thống xác thực cố định Số điện thoại và Email trước khi đặt lịch!
                        </div>
                        <a href="../Login/login.php" class="btn btn-dark fw-bold px-4 flex-shrink-0">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập ngay
                        </a>
                    </div>
                    <?php else: ?>
                    <div
                        class="alert alert-success d-flex flex-wrap justify-content-between align-items-center shadow-sm rounded-3 mb-4 py-2 px-3 gap-2">
                        <span class="small">
                            <i class="bi bi-person-check-fill me-1"></i>Khách hàng:
                            <strong
                                class="lbl-ten-khach-hien-thi"><?= htmlspecialchars($thong_tin_user['ho_ten']) ?></strong>
                            • SĐT & Gmail đã được xác thực cố định
                        </span>
                        <div class="d-flex gap-2">
                            <a href="#bangLichSuGanDaySQL" class="btn btn-dark btn-sm py-0 fw-semibold">
                                <i class="bi bi-table me-1"></i>Lịch sử đặt gần đây
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm py-0 fw-bold"
                                data-bs-toggle="modal" data-bs-target="#modalThongTinCaNhan"
                                onclick="document.getElementById('tab-info-btn').click();">
                                <i class="bi bi-person-gear me-1"></i>Hồ sơ
                            </button>
                            <button type="button" class="btn btn-warning btn-sm py-0 fw-bold" data-bs-toggle="modal"
                                data-bs-target="#modalThongTinCaNhan"
                                onclick="document.getElementById('tab-password-btn').click();">
                                <i class="bi bi-key-fill me-1"></i>Đổi mật khẩu
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card shadow-lg border-0 rounded-4 mb-5">
                        <div class="card-body p-4 p-md-5">
                            <form id="formDatLich" action="xu_ly_dat_lich.php" method="POST"
                                onsubmit="return kiemTraTruocKhiGui()">
                                <!-- Hàng 1: Chọn Tỉnh/Thành Phố -->
                                <div class="mb-4">
                                    <label class="form-label form-label-custom"><i class="bi bi-geo-alt me-2"></i>CHỌN
                                        TỈNH/THÀNH PHỐ</label>
                                    <select name="tinh_thanh" id="tinhThanh"
                                        class="form-select form-select-lg text-dark fw-semibold custom-input" required>
                                        <option selected disabled value="">Đang tải dữ liệu...</option>
                                    </select>
                                    <input type="hidden" name="quan_huyen" id="quanHuyenHidden" value="">
                                </div>

                                <!-- Hàng 2: Chọn Cơ Sở Salon -->
                                <div class="mb-4">
                                    <label class="form-label form-label-custom"><i class="bi bi-shop me-2"></i>CHỌN CƠ
                                        SỞ
                                        SALON</label>
                                    <div id="salonContainer" class="row g-3">
                                        <div class="col-12">
                                            <div class="salon-warning-box text-center py-4 rounded-3 border">
                                                <p class="mb-0 text-danger"><i
                                                        class="bi bi-exclamation-circle text-danger me-2"></i>Chọn
                                                    <strong>Tỉnh/Thành phố</strong> để hiển thị những cơ sở salon của
                                                    chúng
                                                    tôi!
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hàng 3: CHỌN NHIỀU DỊCH VỤ & KỸ THUẬT VIÊN -->
                                <div class="row g-4 mb-3">
                                    <div class="col-md-6" id="cotChonDichVu">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label form-label-custom mb-0"><i
                                                    class="bi bi-layers me-2"></i>CHỌN DỊCH VỤ (CÓ THỂ CHỌN
                                                NHIỀU)</label>
                                            <span id="tongTienHienThi" class="badge bg-success"
                                                style="font-size: 12px;">0 VNĐ</span>
                                        </div>

                                        <button type="button" class="multi-service-dropdown-btn"
                                            data-bs-toggle="collapse" data-bs-target="#khungChonNhieuDichVu"
                                            aria-expanded="false">
                                            <span id="textTomTatDichVu" class="text-muted">Bấm để tích chọn 1 hoặc nhiều
                                                dịch vụ...</span>
                                            <i class="bi bi-chevron-down text-secondary"></i>
                                        </button>

                                        <div class="collapse mt-2 show" id="khungChonNhieuDichVu">
                                            <div class="service-checklist-box">
                                                <?php if (count($danh_sach_dich_vu) > 0): ?>
                                                <?php foreach ($danh_sach_dich_vu as $idx_dv => $dv_obj): 
                                                        $gia_fmt = number_format($dv_obj['gia'], 0, ',', '.') . ' VNĐ';
                                                    ?>
                                                <label class="service-item-label">
                                                    <div class="d-flex align-items-center">
                                                        <input type="checkbox" name="dich_vu[]"
                                                            value="<?= htmlspecialchars($dv_obj['ten']) ?>"
                                                            data-gia="<?= $dv_obj['gia'] ?>"
                                                            class="form-check-input me-2 chk-dich-vu"
                                                            onchange="capNhatDanhSachDichVu()">
                                                        <span
                                                            class="text-dark small"><?= htmlspecialchars($dv_obj['ten']) ?></span>
                                                    </div>
                                                    <span
                                                        class="badge bg-light text-danger border"><?= $gia_fmt ?></span>
                                                </label>
                                                <?php endforeach; ?>
                                                <?php else: ?>
                                                <div class="text-muted small p-2">Chưa có dịch vụ trong CSDL.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div id="khungTheDichVuDaChon" class="mt-2"></div>
                                    </div>

                                    <div class="col-md-6 d-none" id="khungChonKyThuatVien">
                                        <label class="form-label form-label-custom mb-1"><i
                                                class="bi bi-scissors me-2"></i>KỸ THUẬT VIÊN</label>
                                        <select name="ky_thuat_vien" id="kyThuatVien"
                                            class="form-select form-select-lg text-muted custom-input" disabled
                                            required>
                                            <option selected disabled value="">Vui lòng chọn Cơ sở Salon trước</option>
                                        </select>

                                        <div class="p-3 bg-light rounded-3 border mt-3 small text-muted">
                                            <i class="bi bi-shield-lock text-danger me-1"></i><strong>Kiểm soát trùng
                                                lịch hẹn:</strong><br>
                                            Mỗi ca cắt kéo dài <strong>30 phút</strong>. Nếu khung giờ bạn chọn trùng
                                            với
                                            khách đã đặt trước hoặc giờ bận của thợ, hệ thống sẽ không cho phép đặt vào
                                            giờ đó.
                                        </div>
                                    </div>
                                </div>

                                <div id="khungCanhBaoLichBan" class="alert alert-danger d-none small mb-4"></div>

                                <!-- Hàng 4: Ngày giờ, Họ tên, SĐT & Gmail -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-label form-label-custom mb-1"><i
                                                    class="bi bi-calendar3 me-2"></i>CHỌN NGÀY GIỜ HẸN</label>
                                            <span class="badge bg-warning text-dark mb-1" style="font-size: 11px;">Mỗi
                                                ca 30p</span>
                                        </div>
                                        <input type="datetime-local" name="ngay_gio" id="ngayGioInput"
                                            min="<?= date('Y-m-d\TH:i') ?>"
                                            class="form-control form-control-lg fw-bold text-dark custom-input"
                                            required>
                                        <small id="goiYKhungGioTiepTheo"
                                            class="text-success fw-semibold d-block mt-1"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom mb-1"><i
                                                class="bi bi-person me-2"></i>HỌ
                                            VÀ TÊN KHÁCH HÀNG (Có thể sửa)</label>
                                        <input type="text" id="inputHoTenFormDatLich" name="ho_ten"
                                            class="form-control form-control-lg custom-input fw-semibold"
                                            placeholder="<?= $da_dang_nhap ? 'Tên của bạn' : 'Vui lòng đăng nhập trước...' ?>"
                                            value="<?= htmlspecialchars($thong_tin_user['ho_ten']) ?>"
                                            <?= !$da_dang_nhap ? 'readonly' : '' ?> required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom"><i
                                                class="bi bi-telephone-fill me-2"></i>SỐ ĐIỆN THOẠI (Cố định)</label>
                                        <input type="tel" name="so_dien_thoai"
                                            class="form-control form-control-lg custom-input input-locked-bold"
                                            placeholder="<?= $da_dang_nhap ? 'Số điện thoại' : 'Đăng nhập để hiển thị SĐT cố định' ?>"
                                            value="<?= htmlspecialchars($thong_tin_user['so_dien_thoai']) ?>" readonly
                                            title="Số điện thoại cố định theo tài khoản, không thể chỉnh sửa" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom"><i
                                                class="bi bi-envelope-lock me-2"></i>ĐỊA CHỈ GMAIL (Cố định)</label>
                                        <input type="email" name="email"
                                            class="form-control form-control-lg custom-input input-locked-bold"
                                            placeholder="<?= $da_dang_nhap ? 'Địa chỉ Gmail' : 'Đăng nhập để hiển thị Gmail cố định' ?>"
                                            value="<?= htmlspecialchars($thong_tin_user['email']) ?>" readonly
                                            title="Địa chỉ Gmail cố định theo tài khoản, không thể chỉnh sửa" required>
                                    </div>
                                </div>

                                <!-- Nút Submit -->
                                <?php if ($da_dang_nhap): ?>
                                <button type="submit" id="btnSubmitBooking"
                                    class="btn btn-dark w-100 py-3 fs-5 fw-bold btn-submit-custom">
                                    XÁC NHẬN ĐẶT LỊCH HẸN
                                </button>
                                <?php else: ?>
                                <a href="../Login/login.php" class="btn btn-danger w-100 py-3 fs-5 fw-bold">
                                    <i class="bi bi-lock-fill me-2"></i>VUI LÒNG ĐĂNG NHẬP TÀI KHOẢN ĐỂ ĐẶT LỊCH
                                </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- BẢNG LỊCH SỬ ĐẶT LỊCH GẦN ĐÂY LẤY TỪ SQL -->
                    <div id="bangLichSuGanDaySQL" class="history-sql-card p-4 p-md-5">
                        <div
                            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-3 mb-3 gap-3">
                            <div>
                                <h4 class="fw-bold text-uppercase mb-1">
                                    <i class="bi bi-clock-history text-warning me-2"></i>Lịch Sử Đặt Lịch Gần Đây
                                </h4>
                                <p class="text-muted small mb-0">
                                    Hiển thị đầy đủ <strong>Thời gian đặt đơn</strong>, <strong>Giờ hẹn cắt</strong> và
                                    <strong>Giờ trống kế tiếp (+30p)</strong>
                                </p>
                            </div>

                            <ul class="nav nav-pills bg-light p-1 rounded-3" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active btn-sm fw-bold" data-bs-toggle="pill"
                                        data-bs-target="#tabAllSQL" type="button">
                                        Lịch Đặt Gần Đây (<?= count($lich_su_gan_day_sql) ?>)
                                    </button>
                                </li>
                                <?php if ($da_dang_nhap): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link btn-sm fw-bold" data-bs-toggle="pill"
                                        data-bs-target="#tabMySQL" type="button">
                                        Lịch Đặt Của Tôi (<?= count($lich_su_cua_toi) ?>)
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tabAllSQL">
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle small mb-0">
                                        <thead class="table-dark text-center">
                                            <tr>
                                                <th>Mã</th>
                                                <th>Khách hàng</th>
                                                <th>Cơ sở Salon</th>
                                                <th>Gói dịch vụ</th>
                                                <th>Thợ cắt</th>
                                                <th>Thời gian đặt</th>
                                                <th>Giờ hẹn cắt</th>
                                                <th>Giờ trống kế tiếp (+30p)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($lich_su_gan_day_sql) > 0): ?>
                                            <?php foreach ($lich_su_gan_day_sql as $row_s): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-secondary">#<?= $row_s['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($row_s['ho_ten']) ?></strong><br>
                                                    <span
                                                        class="text-muted"><?= htmlspecialchars(cheSDTKhach($row_s['so_dien_thoai'])) ?></span>
                                                </td>
                                                <td class="fw-semibold"><?= htmlspecialchars($row_s['salon_id']) ?></td>
                                                <td>
                                                    <span
                                                        class="text-primary fw-semibold"><?= htmlspecialchars($row_s['dich_vu']) ?></span><br>
                                                    <small
                                                        class="text-danger fw-bold"><?= number_format($row_s['thanh_tien'], 0, ',', '.') ?>đ</small>
                                                </td>
                                                <td class="fw-bold text-dark">
                                                    <?= htmlspecialchars($row_s['ky_thuat_vien']) ?></td>
                                                <td class="text-center text-muted">
                                                    <i
                                                        class="bi bi-calendar-check me-1"></i><?= $row_s['thoi_gian_tao_dep'] ?>
                                                </td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1 fw-bold">
                                                        <i
                                                            class="bi bi-lock-fill me-1"></i><?= $row_s['thoi_gian_dat_dep'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <span class="badge badge-slot-next px-2 py-1">
                                                            <i
                                                                class="bi bi-clock-fill me-1"></i><?= $row_s['thoi_gian_tiep_theo_dep'] ?>
                                                        </span>
                                                        <button type="button"
                                                            class="btn btn-outline-success btn-sm py-0 px-2 fw-bold"
                                                            style="font-size: 11px;"
                                                            onclick="chonNhanhGioLui30p('<?= htmlspecialchars($row_s['ky_thuat_vien']) ?>', '<?= $row_s['iso_tiep_theo'] ?>')">
                                                            <i class="bi bi-plus-circle me-1"></i>Chọn giờ trống này
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Chưa có lịch đặt nào
                                                    trong cơ sở dữ liệu SQL.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <?php if ($da_dang_nhap): ?>
                            <div class="tab-pane fade" id="tabMySQL">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered align-middle small mb-0">
                                        <thead class="table-dark text-center">
                                            <tr>
                                                <th>Mã đơn</th>
                                                <th>Cơ sở Salon</th>
                                                <th>Dịch vụ & Tổng tiền</th>
                                                <th>Kỹ thuật viên</th>
                                                <th>Thời gian đặt</th>
                                                <th>Thời gian hẹn của bạn</th>
                                                <th>Trạng thái đơn</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($lich_su_cua_toi) > 0): ?>
                                            <?php foreach ($lich_su_cua_toi as $my_r): ?>
                                            <tr>
                                                <td class="text-center fw-bold">#<?= $my_r['id'] ?></td>
                                                <td class="fw-bold"><?= htmlspecialchars($my_r['salon_id']) ?></td>
                                                <td>
                                                    <span
                                                        class="fw-semibold text-primary"><?= htmlspecialchars($my_r['dich_vu']) ?></span>
                                                    <span
                                                        class="badge bg-light text-danger border ms-1"><?= number_format($my_r['thanh_tien'], 0, ',', '.') ?>đ</span>
                                                </td>
                                                <td class="fw-bold"><?= htmlspecialchars($my_r['ky_thuat_vien']) ?></td>
                                                <td class="text-center text-muted"><?= $my_r['thoi_gian_tao_dep'] ?>
                                                </td>
                                                <td class="text-center fw-bold text-success">
                                                    <?= $my_r['thoi_gian_dat_dep'] ?></td>
                                                <td class="text-center">
                                                    <?php if (($my_r['trang_thai'] ?? '') === 'Đã hoàn thành'): ?>
                                                    <span class="badge bg-success"><i
                                                            class="bi bi-check-circle-fill me-1"></i>Đã cắt & Hoàn
                                                        thành</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i
                                                            class="bi bi-hourglass-split me-1"></i>Chờ phục vụ</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Bạn chưa có đơn đặt
                                                    lịch nào.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </section>

    <?php $conn_dv->close(); ?>

    <!-- FOOTER CHÂN TRANG -->
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

    <!-- SCRIPT XỬ LÝ MODAL HỒ SƠ CÁ NHÂN, ĐỔI MẬT KHẨU MẠNH & ĐẶT LỊCH -->
    <script>
    // =========================================================================
    // A. LOGIC HỒ SƠ CÁ NHÂN & ĐỔI MẬT KHẨU MẠNH TRONG MODAL AVATAR
    // =========================================================================
    function batTatMatKhauProfile(inputId, iconEl) {
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

    function chonBieuTuongAvatar(iconName, el) {
        document.getElementById('inputAvatarIcon').value = iconName;
        document.querySelectorAll('.avatar-choice-item').forEach(item => item.classList.remove('active'));
        if (el) el.classList.add('active');
        const heroPreview = document.getElementById('heroAvatarPreview');
        if (heroPreview) {
            heroPreview.innerHTML = `<i class="bi ${iconName}"></i>`;
        }
    }

    function danhGiaMatKhauProfile() {
        const inp = document.getElementById("inputMKMoiProfile");
        const box = document.getElementById("boxStrengthProfile");
        const textEl = document.getElementById("textStrengthProfile");
        const barEl = document.getElementById("barStrengthProfile");
        if (!inp || !box || !textEl || !barEl) return {
            score: 0,
            missing: []
        };

        const val = inp.value;
        const rules = {
            len: val.length >= 8,
            upper: /[A-Z]/.test(val),
            lower: /[a-z]/.test(val),
            num: /[0-9]/.test(val),
            spec: /[^A-Za-z0-9]/.test(val)
        };
        const labels = {
            len: "tối thiểu 8 ký tự (8 Chars)",
            upper: "chữ IN HOA (A-Z)",
            lower: "chữ thường (a-z)",
            num: "chữ số (123)",
            spec: "ký tự đặc biệt (@#$)"
        };

        let score = 0;
        let missing = [];

        Object.keys(rules).forEach(k => {
            const rEl = box.querySelector(`.pw-rule[data-rule="${k}"]`);
            if (rules[k]) {
                score++;
                if (rEl) rEl.classList.add("active");
            } else {
                missing.push(labels[k]);
                if (rEl) rEl.classList.remove("active");
            }
        });

        const pct = (score / 5) * 100;
        barEl.style.width = (val.length === 0 ? 0 : Math.max(pct, 15)) + "%";

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

    const inpMKProfile = document.getElementById("inputMKMoiProfile");
    if (inpMKProfile) {
        inpMKProfile.addEventListener("input", () => {
            danhGiaMatKhauProfile();
            const al = document.getElementById("alertProfilePassword");
            if (al) al.classList.add("d-none");
        });
    }

    function luuThongTinCaNhanAjax(e) {
        if (e) e.preventDefault();
        const form = document.getElementById("formCapNhatHoSo");
        const alertEl = document.getElementById("alertProfileInfo");
        const btn = document.getElementById("btnLuuHoSoAjax");

        const fd = new FormData(form);
        fd.append("ajax_action", "update_profile_info");

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...`;

        fetch("DatLich.php", {
                method: "POST",
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-check2-circle me-1"></i>Lưu Thông Tin Hồ Sơ`;
                if (data.status === "success") {
                    alertEl.className = "alert alert-success small fw-semibold py-2";
                    alertEl.innerText = data.msg;
                    alertEl.classList.remove("d-none");

                    // Cập nhật ngay tên & icon trên toàn trang mà không cần reload
                    document.querySelectorAll(".lbl-ten-khach-hien-thi").forEach(el => {
                        el.innerText = data.ho_ten_moi;
                    });
                    const inpTenBooking = document.getElementById("inputHoTenFormDatLich");
                    if (inpTenBooking) inpTenBooking.value = data.ho_ten_moi;

                    const deskAvt = document.getElementById("navDesktopAvatarIcon");
                    if (deskAvt) deskAvt.className = `bi ${data.avatar_icon} fs-6`;
                    const mobAvt = document.getElementById("navMobileAvatarIcon");
                    if (mobAvt) mobAvt.className = `bi ${data.avatar_icon}`;
                } else {
                    alertEl.className = "alert alert-danger small fw-semibold py-2";
                    alertEl.innerText = "❌ " + data.msg;
                    alertEl.classList.remove("d-none");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-check2-circle me-1"></i>Lưu Thông Tin Hồ Sơ`;
                alertEl.className = "alert alert-danger small fw-semibold py-2";
                alertEl.innerText = "❌ Lỗi kết nối máy chủ!";
                alertEl.classList.remove("d-none");
            });

        return false;
    }

    function doiMatKhauManhAjax(e) {
        if (e) e.preventDefault();
        const alertEl = document.getElementById("alertProfilePassword");
        const btn = document.getElementById("btnLuuMatKhauManh");
        const mkCu = document.getElementById("inputMKHienTaiProfile").value.trim();
        const mkMoi = document.getElementById("inputMKMoiProfile").value;
        const mkCf = document.getElementById("inputXacNhanMKMoiProfile").value;

        const st = danhGiaMatKhauProfile();
        if (st.score < 5) {
            alertEl.className = "alert alert-danger small fw-semibold py-2";
            alertEl.innerHTML =
                `❌ <strong>Mật khẩu mới chưa đủ mạnh (Strong)!</strong><br>Vui lòng bổ sung: ${st.missing.join(", ")}.`;
            alertEl.classList.remove("d-none");
            document.getElementById("inputMKMoiProfile").focus();
            return false;
        }

        if (mkMoi !== mkCf) {
            alertEl.className = "alert alert-danger small fw-semibold py-2";
            alertEl.innerText = "❌ Mật khẩu xác nhận nhập lại không trùng khớp!";
            alertEl.classList.remove("d-none");
            document.getElementById("inputXacNhanMKMoiProfile").focus();
            return false;
        }

        const fd = new FormData();
        fd.append("ajax_action", "change_strong_password");
        fd.append("mat_khau_hien_tai", mkCu);
        fd.append("mat_khau_moi", mkMoi);
        fd.append("xac_nhan_mat_khau_moi", mkCf);

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Đang cập nhật mật khẩu...`;

        fetch("DatLich.php", {
                method: "POST",
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-lock-fill text-warning me-1"></i>Cập Nhật Mật Khẩu Mạnh`;
                if (data.status === "success") {
                    alertEl.className = "alert alert-success small fw-semibold py-2";
                    alertEl.innerText = data.msg;
                    alertEl.classList.remove("d-none");

                    document.getElementById("formDoiMatKhauManh").reset();
                    danhGiaMatKhauProfile();

                    const badgeMK = document.getElementById("badgeCapNhatMK");
                    if (badgeMK && data.thoi_gian_mk) {
                        badgeMK.innerHTML =
                            `<i class="bi bi-shield-lock text-info me-1"></i>Đổi MK gần nhất: <strong>${data.thoi_gian_mk}</strong>`;
                    }
                } else {
                    alertEl.className = "alert alert-danger small fw-semibold py-2";
                    alertEl.innerText = "❌ " + data.msg;
                    alertEl.classList.remove("d-none");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-lock-fill text-warning me-1"></i>Cập Nhật Mật Khẩu Mạnh`;
                alertEl.className = "alert alert-danger small fw-semibold py-2";
                alertEl.innerText = "❌ Lỗi kết nối máy chủ!";
                alertEl.classList.remove("d-none");
            });

        return false;
    }

    // =========================================================================
    // B. LOGIC CHẶN TRÙNG LỊCH NGAY LẬP TỨC, ẨN THỢ TRƯỚC KHI CHỌN SALON
    // =========================================================================
    const tinhThanhSelect = document.getElementById('tinhThanh');
    const quanHuyenHidden = document.getElementById('quanHuyenHidden');
    const salonContainer = document.getElementById('salonContainer');
    const khungChonKyThuatVien = document.getElementById('khungChonKyThuatVien');
    const kyThuatVienSelect = document.getElementById('kyThuatVien');
    const ngayGioInput = document.getElementById('ngayGioInput');
    const khungCanhBao = document.getElementById('khungCanhBaoLichBan');
    const goiYKhungGioTiepTheo = document.getElementById('goiYKhungGioTiepTheo');
    const btnSubmit = document.getElementById('btnSubmitBooking');

    const danhSachLichBan = <?= json_encode($lich_ban_arr, JSON_UNESCAPED_UNICODE) ?>;
    const danhSachLichDaDat = <?= json_encode($lich_da_dat_arr, JSON_UNESCAPED_UNICODE) ?>;
    let provincesData = [];
    let dangBiTrungLich = false;

    const DANH_SACH_THO_THEO_SALON = {
        "TP. Vinh": [{
                value: "Vừ Xuân Công",
                label: "Vừ Xuân Công (Hair Artist - Cơ sở Vinh)"
            },
            {
                value: "Moong Văn Minh",
                label: "Moong Văn Minh (Master - Cơ sở Vinh)"
            }
        ],
        "Huyện Kỳ Sơn": [{
                value: "Lầu Bá Trừ",
                label: "Lầu Bá Trừ (Stylist - Cơ sở Kỳ Sơn)"
            },
            {
                value: "Hờ Bá Chính",
                label: "Hờ Bá Chính (Stylist - Cơ sở Kỳ Sơn)"
            }
        ]
    };

    function layChuoiNgayGioHienTaiISO() {
        const now = new Date();
        const nam = now.getFullYear();
        const thang = String(now.getMonth() + 1).padStart(2, '0');
        const ngay = String(now.getDate()).padStart(2, '0');
        const gio = String(now.getHours()).padStart(2, '0');
        const phut = String(now.getMinutes()).padStart(2, '0');
        return `${nam}-${thang}-${ngay}T${gio}:${phut}`;
    }

    function capNhatMinNgayGioHienTai() {
        if (!ngayGioInput) return;
        const minISO = layChuoiNgayGioHienTaiISO();
        ngayGioInput.setAttribute('min', minISO);
    }

    capNhatMinNgayGioHienTai();
    setInterval(capNhatMinNgayGioHienTai, 30000);

    function laThoiGianTrongQuaKhu(isoString) {
        if (!isoString) return false;
        const thoiGianChon = new Date(isoString);
        const hienTai = new Date();
        hienTai.setSeconds(0, 0);
        return thoiGianChon.getTime() < (hienTai.getTime() - 60000);
    }

    function capNhatDanhSachDichVu() {
        const checkboxes = document.querySelectorAll('.chk-dich-vu:checked');
        const textTomTat = document.getElementById('textTomTatDichVu');
        const khungBadges = document.getElementById('khungTheDichVuDaChon');
        const tongTienEl = document.getElementById('tongTienHienThi');

        let dsTen = [];
        let tongTien = 0;

        checkboxes.forEach(chk => {
            dsTen.push(chk.value);
            tongTien += parseFloat(chk.getAttribute('data-gia') || 0);
        });

        if (dsTen.length === 0) {
            textTomTat.innerHTML = 'Bấm để tích chọn 1 hoặc nhiều dịch vụ...';
            textTomTat.className = 'text-muted';
            khungBadges.innerHTML = '';
            tongTienEl.innerText = '0 VNĐ';
        } else {
            textTomTat.innerHTML = `Đã chọn <strong>${dsTen.length} dịch vụ</strong>`;
            textTomTat.className = 'text-dark fw-semibold';
            khungBadges.innerHTML = dsTen.map(t =>
                `<span class="selected-dv-badge"><i class="bi bi-check2 me-1"></i>${t}</span>`).join('');
            tongTienEl.innerText = 'Tổng: ' + tongTien.toLocaleString('vi-VN') + ' VNĐ';
        }
    }

    function kiemTraTruocKhiGui() {
        const checkboxes = document.querySelectorAll('.chk-dich-vu:checked');
        if (checkboxes.length === 0) {
            alert('⚠️ Quý khách vui lòng tích chọn ít nhất 1 dịch vụ trước khi đặt lịch!');
            return false;
        }

        const radioSalon = document.querySelector('input[name="salon_id"]:checked');
        if (!radioSalon) {
            alert('⚠️ Quý khách vui lòng chọn 1 trong 2 Cơ sở Salon!');
            return false;
        }

        if (!kyThuatVienSelect.value) {
            alert('⚠️ Quý khách vui lòng chọn Nhân viên cắt tóc của cơ sở!');
            return false;
        }

        if (!ngayGioInput.value || laThoiGianTrongQuaKhu(ngayGioInput.value)) {
            alert('❌ Không thể đặt lịch vào ngày giờ đã qua! Vui lòng chọn ngày giờ từ thời điểm hiện tại trở đi.');
            capNhatMinNgayGioHienTai();
            ngayGioInput.focus();
            return false;
        }

        kiemTraTrungLichKhongChoDat();
        if (dangBiTrungLich) {
            alert(
                '❌ Khung giờ bạn chọn đã có khách đặt trước hoặc trùng lịch bận của thợ! Vui lòng chọn khung giờ khác.'
            );
            ngayGioInput.focus();
            return false;
        }

        return true;
    }

    fetch('https://provinces.open-api.vn/api/?depth=1')
        .then(response => response.json())
        .then(data => {
            provincesData = data;
            tinhThanhSelect.innerHTML = '<option selected disabled value="">Chọn Tỉnh/Thành Phố</option>';
            data.forEach(province => {
                tinhThanhSelect.innerHTML += `<option value="${province.name}">${province.name}</option>`;
            });
        });

    tinhThanhSelect.addEventListener('change', function() {
        const provinceName = this.value;

        if (khungChonKyThuatVien) {
            khungChonKyThuatVien.classList.add('d-none');
        }
        kyThuatVienSelect.disabled = true;
        kyThuatVienSelect.innerHTML =
            '<option selected disabled value="">Vui lòng chọn Cơ sở Salon trước</option>';
        khungCanhBao.classList.add('d-none');

        if (provinceName.includes('Nghệ An')) {
            salonContainer.innerHTML = `
                    <div class="col-md-6">
                        <label class="salon-card">
                            <input type="radio" name="salon_id" value="CMTC Salon - Cơ sở Vinh (Số 51 Lý Tự Trọng, TP. Vinh)" data-khu-vuc="TP. Vinh" required onchange="enableKTV(this)">
                            <div class="salon-info">
                                <h6>CMTC Salon - Cơ sở 1 (TP. Vinh)</h6>
                                <p class="mb-0"><i class="bi bi-geo-alt-fill text-danger"></i> Số 51 Lý Tự Trọng, Phường Vinh Phú, TP. Vinh, Nghệ An</p>
                            </div>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="salon-card">
                            <input type="radio" name="salon_id" value="CMTC Salon - Cơ sở Kỳ Sơn (Mường Lống, Kỳ Sơn)" data-khu-vuc="Huyện Kỳ Sơn" required onchange="enableKTV(this)">
                            <div class="salon-info">
                                <h6>CMTC Salon - Cơ sở 2 (Kỳ Sơn)</h6>
                                <p class="mb-0"><i class="bi bi-geo-alt-fill text-danger"></i> Mường Lống, Huyện Kỳ Sơn, Nghệ An</p>
                            </div>
                        </label>
                    </div>
                `;
        } else {
            salonContainer.innerHTML = `
                    <div class="col-12">
                        <div class="salon-warning-box text-center py-4 rounded-3 border">
                            <p class="mb-0 text-muted"><i class="bi bi-info-circle me-2"></i>Rất tiếc, CMTC Salon hiện chưa có cơ sở tại <strong>${provinceName}</strong>. Hẹn gặp bạn trong tương lai!</p>
                        </div>
                    </div>
                `;
        }
    });

    function enableKTV(radioEl) {
        let khuVuc = 'TP. Vinh';
        if (radioEl && quanHuyenHidden) {
            khuVuc = radioEl.getAttribute('data-khu-vuc') || 'TP. Vinh';
            quanHuyenHidden.value = khuVuc;
        } else if (quanHuyenHidden && quanHuyenHidden.value) {
            khuVuc = quanHuyenHidden.value;
        }

        if (khungChonKyThuatVien) {
            khungChonKyThuatVien.classList.remove('d-none');
        }

        const dsThoCuaCoSo = DANH_SACH_THO_THEO_SALON[khuVuc] || DANH_SACH_THO_THEO_SALON["TP. Vinh"];
        kyThuatVienSelect.disabled = false;
        kyThuatVienSelect.classList.remove('text-muted');
        kyThuatVienSelect.classList.add('text-dark', 'fw-semibold');

        let optionsHtml = `<option selected disabled value="">-- Chọn nhân viên cắt tóc tại ${khuVuc} --</option>`;
        dsThoCuaCoSo.forEach(tho => {
            optionsHtml += `<option value="${tho.value}">${tho.label}</option>`;
        });
        kyThuatVienSelect.innerHTML = optionsHtml;
        khungCanhBao.classList.add("d-none");
        goiYKhungGioTiepTheo.innerHTML = "";
        dangBiTrungLich = false;
        if (btnSubmit) btnSubmit.disabled = false;
    }

    function doiGioSangPhut(strGio) {
        const parts = strGio.split(':');
        return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    }

    function doiPhutSangGio(tongPhut) {
        const h = Math.floor(tongPhut / 60);
        const m = tongPhut % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }

    function chonNhanhGioLui30p(tenTho, isoTimeTiepTheo) {
        const minHienTai = layChuoiNgayGioHienTaiISO();
        const thoiGianGan = laThoiGianTrongQuaKhu(isoTimeTiepTheo) ? minHienTai : isoTimeTiepTheo;

        let khuVucTho = "TP. Vinh";
        if (DANH_SACH_THO_THEO_SALON["Huyện Kỳ Sơn"].some(t => t.value === tenTho)) {
            khuVucTho = "Huyện Kỳ Sơn";
        }

        if (!tinhThanhSelect.value || !tinhThanhSelect.value.includes('Nghệ An')) {
            const optNgheAn = Array.from(tinhThanhSelect.options).find(o => o.value.includes('Nghệ An'));
            if (optNgheAn) {
                tinhThanhSelect.value = optNgheAn.value;
                tinhThanhSelect.dispatchEvent(new Event('change'));
            }
        }

        const radioTarget = document.querySelector(`input[name="salon_id"][data-khu-vuc="${khuVucTho}"]`);
        if (radioTarget) {
            radioTarget.checked = true;
            enableKTV(radioTarget);
        }

        kyThuatVienSelect.value = tenTho;
        ngayGioInput.value = thoiGianGan;
        kiemTraTrungLichKhongChoDat();
        ngayGioInput.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    function kiemTraTrungLichKhongChoDat() {
        capNhatMinNgayGioHienTai();
        const ktv = kyThuatVienSelect.value;
        let dtVal = ngayGioInput.value;
        dangBiTrungLich = false;

        if (dtVal && laThoiGianTrongQuaKhu(dtVal)) {
            const minHienTai = layChuoiNgayGioHienTaiISO();
            khungCanhBao.className = "alert alert-danger fw-bold small mb-4";
            khungCanhBao.innerHTML =
                `❌ KHÔNG THỂ ĐẶT LỊCH QUÁ KHỨ: Ngày giờ bạn chọn đã qua! Vui lòng chọn từ thời điểm hiện tại (<strong>${minHienTai.replace('T', ' ')}</strong>) trở đi.`;
            khungCanhBao.classList.remove("d-none");
            goiYKhungGioTiepTheo.innerHTML =
                `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Không thể chọn ngày giờ trong quá khứ</span>`;
            ngayGioInput.value = "";
            dangBiTrungLich = true;
            if (btnSubmit) btnSubmit.disabled = true;
            return;
        }

        if (!ktv) return;

        const lichBanCuaTho = danhSachLichBan.filter(item => item.ky_thuat_vien === ktv);
        const lichKhachTruocCuaTho = danhSachLichDaDat.filter(item => item.ky_thuat_vien === ktv);

        if (!dtVal) {
            let htmlThongBao = [];
            if (lichBanCuaTho.length > 0) {
                let textBan = lichBanCuaTho.map(b =>
                        `Ngày ${b.ngay_ban} (${b.gio_bat_dau.slice(0,5)} - ${b.gio_ket_thuc.slice(0,5)}: ${b.ly_do})`)
                    .join('; ');
                htmlThongBao.push(`<strong>Giờ bận cá nhân:</strong> ${textBan}`);
            }
            if (lichKhachTruocCuaTho.length > 0) {
                let textDat = lichKhachTruocCuaTho.slice(-4).map(d =>
                    `Ngày ${d.ngay_hen} đã có khách đặt lúc <strong>${d.gio_hen}</strong> (Khung giờ trống tiếp theo từ: <strong>${d.gio_lui_30p}</strong>)`
                ).join('<br>• ');
                htmlThongBao.push(`<strong>Các ca đã có khách đặt:</strong><br>• ${textDat}`);
            }
            if (htmlThongBao.length > 0) {
                khungCanhBao.className = "alert alert-warning small mb-4";
                khungCanhBao.innerHTML =
                    `<i class="bi bi-clock-history me-1"></i><strong>Lịch của nhân viên ${ktv}:</strong><br>` +
                    htmlThongBao.join('<br>');
                khungCanhBao.classList.remove("d-none");
            } else {
                khungCanhBao.classList.add("d-none");
            }
            if (btnSubmit) btnSubmit.disabled = false;
            return;
        }

        let [ngayChon, gioChon] = dtVal.split('T');
        let phutChon = doiGioSangPhut(gioChon);

        const cacDonTrongNgay = danhSachLichDaDat
            .filter(item => item.ky_thuat_vien === ktv && item.ngay_hen === ngayChon)
            .sort((a, b) => doiGioSangPhut(a.gio_hen) - doiGioSangPhut(b.gio_hen));

        for (let i = 0; i < cacDonTrongNgay.length; i++) {
            const don = cacDonTrongNgay[i];
            const phutKhachTruoc = doiGioSangPhut(don.gio_hen);
            if (Math.abs(phutChon - phutKhachTruoc) < 30) {
                dangBiTrungLich = true;
                const gioTrongTiep = doiPhutSangGio(phutKhachTruoc + 30);
                khungCanhBao.className = "alert alert-danger border-danger fw-semibold small mb-4";
                khungCanhBao.innerHTML =
                    `<i class="bi bi-x-octagon-fill fs-6 me-1"></i><strong>KHÔNG THỂ ĐẶT GIỜ NÀY (TRÙNG LỊCH HẸN):</strong> Nhân viên <strong>${ktv}</strong> đã có khách đặt lịch lúc <strong>${don.gio_hen} ngày ${ngayChon}</strong> (mỗi ca cắt kéo dài 30 phút). Quý khách vui lòng chọn khung giờ khác (từ <strong>${gioTrongTiep}</strong> trở đi) hoặc chọn nhân viên khác!`;
                khungCanhBao.classList.remove("d-none");
                goiYKhungGioTiepTheo.innerHTML =
                    `<span class="text-danger"><i class="bi bi-lock-fill me-1"></i>Giờ ${gioChon} đã kín lịch! Vui lòng chọn từ <strong>${gioTrongTiep}</strong> trở đi.</span>`;
                if (btnSubmit) btnSubmit.disabled = true;
                return;
            }
        }

        const biTrungBan = danhSachLichBan.find(item =>
            item.ky_thuat_vien === ktv &&
            item.ngay_ban === ngayChon &&
            gioChon >= item.gio_bat_dau.slice(0, 5) &&
            gioChon <= item.gio_ket_thuc.slice(0, 5)
        );

        if (biTrungBan) {
            dangBiTrungLich = true;
            khungCanhBao.className = "alert alert-danger fw-bold small mb-4";
            khungCanhBao.innerHTML =
                `❌ KHÔNG THỂ ĐẶT GIỜ NÀY: Nhân viên ${ktv} đang bận từ ${biTrungBan.gio_bat_dau.slice(0,5)} đến ${biTrungBan.gio_ket_thuc.slice(0,5)} ngày ${ngayChon} (${biTrungBan.ly_do}). Vui lòng chọn giờ ngoài khung này!`;
            khungCanhBao.classList.remove("d-none");
            goiYKhungGioTiepTheo.innerHTML =
                `<span class="text-danger"><i class="bi bi-lock-fill me-1"></i>Nhân viên đang bận trong khung giờ này!</span>`;
            if (btnSubmit) btnSubmit.disabled = true;
            return;
        }

        dangBiTrungLich = false;
        khungCanhBao.className = "alert alert-info small mb-4";
        khungCanhBao.innerHTML =
            `<i class="bi bi-check-circle-fill text-success me-1"></i>Khung giờ <strong>${gioChon} ngày ${ngayChon}</strong> với nhân viên <strong>${ktv}</strong> đang trống và hoàn toàn hợp lệ!`;
        khungCanhBao.classList.remove("d-none");
        goiYKhungGioTiepTheo.innerHTML =
            `<span class="text-success"><i class="bi bi-check2-all me-1"></i>Khung giờ hợp lệ: <strong>${gioChon} (${ngayChon})</strong></span>`;
        if (btnSubmit) btnSubmit.disabled = false;
    }

    kyThuatVienSelect.addEventListener('change', function() {
        kiemTraTrungLichKhongChoDat();
    });
    ngayGioInput.addEventListener('change', function() {
        kiemTraTrungLichKhongChoDat();
    });
    ngayGioInput.addEventListener('input', function() {
        kiemTraTrungLichKhongChoDat();
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
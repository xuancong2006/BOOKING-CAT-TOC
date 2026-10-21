<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $conn = new mysqli("localhost", "root", "", "cmtc_salon");
} else {
    $conn = new mysqli("sql313.infinityfree.com", "if0_43116533", "0B2ClnIIsyqt0B", "if0_43116533_cmtc");
}
$conn->set_charset("utf8mb4");

// =========================================================================================
// CẤU HÌNH LINK GOOGLE APPS SCRIPT WEBHOOK ĐỂ GỬI GMAIL TỪ TRANG ADMIN
// (Tự động đồng bộ link Webhook từ file ../DatLich/xu_ly_dat_lich.php nếu bạn đã dán ở đó)
// =========================================================================================
$GMAIL_CUA_BAN = "xuancongsupport@gmail.com";
$GOOGLE_SCRIPT_WEBHOOK_URL = "https://script.google.com/macros/s/AKfycbyo14N6xmP1VffreSzZ7xRwa4cbWdSrFRmalqMbRMa98Wpq_k29sDsu7-x1mUY1P2Y/exec";

$file_xldl = __DIR__ . "/../DatLich/xu_ly_dat_lich.php";
if (file_exists($file_xldl)) {
    $xldl_content = file_get_contents($file_xldl);
    if (preg_match('/\$GOOGLE_SCRIPT_WEBHOOK_URL\s*=\s*"([^"]+)"/', $xldl_content, $m_url)) {
        if (!empty(trim($m_url[1]))) {
            $GOOGLE_SCRIPT_WEBHOOK_URL = trim($m_url[1]);
        }
    }
    if (preg_match('/\$GMAIL_CUA_BAN\s*=\s*"([^"]+)"/', $xldl_content, $m_gm)) {
        if (!empty(trim($m_gm[1]))) {
            $GMAIL_CUA_BAN = trim($m_gm[1]);
        }
    }
}

// Hàm kiểm tra và thêm cột an toàn tương thích 100% với cả MySQL và MariaDB (Đồng bộ với DatLich v5)
function damBaoCotTonTai($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $res->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

// 1. Đảm bảo các bảng và cột cần thiết đều tồn tại (Khớp hoàn toàn với DatLich v5)
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

$conn->query("CREATE TABLE IF NOT EXISTS `lich_ban_tho` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ky_thuat_vien` varchar(100) NOT NULL,
  `ngay_ban` date NOT NULL,
  `gio_bat_dau` time NOT NULL,
  `gio_ket_thuc` time NOT NULL,
  `ly_do` varchar(255) DEFAULT 'Bận việc gia đình',
  `ngay_tao` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. TẠO BẢNG KHÁCH HÀNG VIP CỦA QUÁN (CHỈ LƯU TỐI ĐA 5 KHÁCH HÀNG VIP)
$conn->query("CREATE TABLE IF NOT EXISTS `khach_hang_vip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ho_ten` varchar(100) NOT NULL,
  `so_dien_thoai` varchar(20) NOT NULL UNIQUE,
  `email` varchar(100) DEFAULT NULL,
  `so_lan_cat` int(11) DEFAULT 1,
  `tong_chi_tieu` decimal(15,0) DEFAULT 0,
  `hang_vip` varchar(50) DEFAULT 'VIP Bạc',
  `phan_tram_giam` int(11) DEFAULT 10,
  `uu_dai_dac_quyen` varchar(255) DEFAULT 'Giảm 10% tổng hóa đơn + Tặng gội massage thảo dược',
  `ngay_cap_nhat` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

damBaoCotTonTai($conn, 'bang_dat_lich', 'email', 'varchar(100) DEFAULT NULL');
damBaoCotTonTai($conn, 'bang_dat_lich', 'thoi_gian_dat', 'timestamp DEFAULT CURRENT_TIMESTAMP');
damBaoCotTonTai($conn, 'bang_dat_lich', 'trang_thai', "varchar(50) DEFAULT 'Chờ phục vụ'");
$conn->query("ALTER TABLE `bang_dat_lich` MODIFY COLUMN `dich_vu` TEXT NOT NULL;");

// Lấy bảng giá dịch vụ để tính doanh thu chuẩn (hỗ trợ cả đơn chọn nhiều dịch vụ từ DatLich v5)
$bang_gia_map = [];
$res_map_dv = $conn->query("SELECT ten_dich_vu, gia_1 FROM dich_vu");
if ($res_map_dv && $res_map_dv->num_rows > 0) {
    while ($rm = $res_map_dv->fetch_assoc()) {
        $bang_gia_map[trim($rm['ten_dich_vu'])] = (float)$rm['gia_1'] * 1000;
    }
}

function tinhTienDonDatLich($chuoi_dv, $map_gia) {
    $ds = explode(',', (string)$chuoi_dv);
    $tong = 0;
    foreach ($ds as $item) {
        $ten = trim($item);
        if (isset($map_gia[$ten]) && $map_gia[$ten] > 0) {
            $tong += $map_gia[$ten];
        }
    }
    return ($tong > 0) ? $tong : 100000;
}

// Hàm tự động xác định Hạng VIP & Ưu đãi theo thứ hạng / số lần cắt
function xacDinhUuDaiVIP($thu_hang, $so_lan) {
    if ($thu_hang == 1 || $so_lan >= 10) {
        return [
            'hang' => '👑 VIP Kim Cương',
            'badge' => 'bg-danger text-white',
            'giam' => 25,
            'uu_dai' => 'Giảm 25% mọi dịch vụ + Miễn phí Gội dưỡng sinh & Đắp mặt nạ + Ưu tiên chọn thợ Master'
        ];
    } elseif ($thu_hang == 2 || $so_lan >= 5) {
        return [
            'hang' => '🥇 VIP Vàng',
            'badge' => 'bg-warning text-dark',
            'giam' => 20,
            'uu_dai' => 'Giảm 20% tổng hóa đơn + Tặng Combo Gội Massage cổ vai gáy + Tặng sáp vuốt tóc'
        ];
    } elseif ($thu_hang == 3 || $so_lan >= 3) {
        return [
            'hang' => '🥈 VIP Bạch Kim',
            'badge' => 'bg-primary text-white',
            'giam' => 15,
            'uu_dai' => 'Giảm 15% tổng hóa đơn + Miễn phí Gội thảo dược + Ưu tiên đặt lịch giờ cao điểm'
        ];
    } else {
        return [
            'hang' => '💎 VIP Thân Thiết',
            'badge' => 'bg-info text-dark',
            'giam' => 10,
            'uu_dai' => 'Giảm 10% tổng hóa đơn + Tặng 1 lần hấp dầu phục hồi hoặc ấn huyệt thư giãn'
        ];
    }
}

// Hàm đồng bộ tự động đúng 5 Khách Hàng VIP đứng đầu từ lịch sử đặt lịch vào bảng khach_hang_vip
function dongBoTop5KhachVIP($conn, $bang_gia_map) {
    $sql_top5 = "SELECT MAX(ho_ten) AS ho_ten, so_dien_thoai, MAX(email) AS email, COUNT(*) AS so_lan, GROUP_CONCAT(dich_vu SEPARATOR ',') AS tat_ca_dv
                 FROM bang_dat_lich
                 WHERE so_dien_thoai != ''
                 GROUP BY so_dien_thoai
                 ORDER BY so_lan DESC, MAX(id) DESC
                 LIMIT 5";
    $res_top = $conn->query($sql_top5);
    if ($res_top) {
        $conn->query("TRUNCATE TABLE khach_hang_vip");
        $rank = 1;
        while ($row_v = $res_top->fetch_assoc()) {
            $sdt_v = $conn->real_escape_string($row_v['so_dien_thoai']);
            $ten_v = $conn->real_escape_string($row_v['ho_ten']);
            $em_v  = $conn->real_escape_string($row_v['email'] ?? '');
            $so_lan = (int)$row_v['so_lan'];

            $ds_dv_khach = explode(',', (string)$row_v['tat_ca_dv']);
            $tong_chi = 0;
            foreach ($ds_dv_khach as $dv_k) {
                $t_dv = trim($dv_k);
                $tong_chi += (isset($bang_gia_map[$t_dv]) && $bang_gia_map[$t_dv] > 0) ? $bang_gia_map[$t_dv] : 100000;
            }

            $goi_vip = xacDinhUuDaiVIP($rank, $so_lan);
            $hang_v = $conn->real_escape_string($goi_vip['hang']);
            $giam_v = (int)$goi_vip['giam'];
            $uudai_v = $conn->real_escape_string($goi_vip['uu_dai']);

            $conn->query("INSERT INTO khach_hang_vip (ho_ten, so_dien_thoai, email, so_lan_cat, tong_chi_tieu, hang_vip, phan_tram_giam, uu_dai_dac_quyen)
                          VALUES ('$ten_v', '$sdt_v', '$em_v', $so_lan, $tong_chi, '$hang_v', $giam_v, '$uudai_v')");
            $rank++;
        }
    }
}

$msg = "";
$payload_hoan_thanh_don = null;

// Xử lý xóa đơn đặt lịch (nếu Admin muốn dọn các đơn test)
if (isset($_GET['xoa_don'])) {
    $id_xoa = (int)$_GET['xoa_don'];
    $conn->query("DELETE FROM bang_dat_lich WHERE id = $id_xoa");
    dongBoTop5KhachVIP($conn, $bang_gia_map);
    $msg = "🗑️ Đã xóa đơn đặt lịch #$id_xoa và cập nhật lại thống kê!";
}

// =========================================================================================
// XÁC NHẬN ĐƠN ĐẶT LỊCH ĐÃ CẮT & HOÀN THÀNH + GỬI GMAIL CHO KHÁCH HÀNG
// =========================================================================================
if (isset($_GET['hoan_thanh_don'])) {
    $id_ht = (int)$_GET['hoan_thanh_don'];
    $conn->query("UPDATE bang_dat_lich SET trang_thai = 'Đã hoàn thành' WHERE id = $id_ht");
    dongBoTop5KhachVIP($conn, $bang_gia_map);

    $res_don_ht = $conn->query("SELECT * FROM bang_dat_lich WHERE id = $id_ht LIMIT 1");
    if ($res_don_ht && $row_ht = $res_don_ht->fetch_assoc()) {
        $email_kh = trim($row_ht['email'] ?? '');
        if ($email_kh === '' && !empty($row_ht['so_dien_thoai'])) {
            $sdt_find = $conn->real_escape_string($row_ht['so_dien_thoai']);
            $q_em = $conn->query("SELECT email FROM tai_khoan WHERE so_dien_thoai = '$sdt_find' LIMIT 1");
            if ($q_em && $r_em = $q_em->fetch_assoc()) {
                $email_kh = trim($r_em['email']);
            }
        }

        $ten_kh = $row_ht['ho_ten'];
        $sdt_kh = $row_ht['so_dien_thoai'];
        $cs_salon = $row_ht['salon_id'];
        $dv_da_cat = $row_ht['dich_vu'];
        $tho_cat = $row_ht['ky_thuat_vien'];
        $gio_hoan_thanh = date('H:i - d/m/Y');
        $tong_tien_ht = number_format(tinhTienDonDatLich($dv_da_cat, $bang_gia_map), 0, ',', '.') . ' VNĐ';

        if ($email_kh !== '' && strpos($email_kh, '@') !== false) {
            $sub_ht = "✅ [CMCT SALON] Xác nhận hoàn thành dịch vụ & Cảm ơn quý khách (#$id_ht)";
            $html_ht = "
            <div style='font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #0b4a4d 0%, #111 100%); color: #e6b96f; padding: 26px; text-align: center; border-bottom: 3px solid #c09e6c;'>
                    <h2 style='margin: 0; text-transform: uppercase; letter-spacing: 1px;'>HOÀN THÀNH DỊCH VỤ CẮT TÓC</h2>
                    <p style='color: #fff; margin: 6px 0 0;'>Mã phiếu hẹn: <strong>#$id_ht</strong> — Trạng thái: <strong>ĐÃ CẮT & HOÀN THÀNH</strong></p>
                </div>
                <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.65;'>
                    <p>Xin chào <strong>$ten_kh</strong>,</p>
                    <p><strong>CMCT Salon</strong> xin thông báo đơn đặt lịch cắt tóc <strong>#$id_ht</strong> của Quý khách đã được phục vụ và hoàn thành tốt đẹp! Dưới đây là thông tin phiếu dịch vụ của Quý khách:</p>
                    <table style='width: 100%; border-collapse: collapse; margin: 18px 0;'>
                        <tr>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa; width: 38%;'><strong>Khách hàng:</strong></td>
                            <td style='padding: 10px 12px; border: 1px solid #ddd;'>$ten_kh ($sdt_kh)</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Cơ sở phục vụ:</strong></td>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; font-weight: bold;'>$cs_salon</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Gói dịch vụ đã sử dụng:</strong></td>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; color: #198754; font-weight: bold;'>$dv_da_cat</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Kỹ thuật viên thực hiện:</strong></td>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; font-weight: bold;'>$tho_cat</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Tổng chi phí dịch vụ:</strong></td>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; color: #d63384; font-weight: bold; font-size: 15px;'>$tong_tien_ht</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Thời điểm hoàn thành:</strong></td>
                            <td style='padding: 10px 12px; border: 1px solid #ddd; color: #198754; font-weight: bold;'>✔️ Đã cắt xong lúc $gio_hoan_thanh</td>
                        </tr>
                    </table>
                    <div style='background-color: #f0fdf4; border-left: 4px solid #198754; padding: 14px 16px; border-radius: 6px; margin-top: 18px; color: #166534; font-size: 14px; text-align: justify;'>
                        <strong>Lời cảm ơn từ CMCT Salon:</strong> Cảm ơn Quý khách <strong>$ten_kh</strong> đã tin tưởng và lựa chọn <strong>CMCT Salon</strong> hôm nay! Lượt cắt này đã được cộng tích lũy vào lịch sử tài khoản của Quý khách để xét hạng <strong>Top 5 Khách Hàng VIP (Giảm 10% - 25%)</strong>. Chúc Quý khách luôn tự tin, phong độ với mái tóc mới và hẹn gặp lại Quý khách trong những lần phục vụ tiếp theo!
                    </div>
                </div>
                <div style='background-color: #111; color: #e6b96f; padding: 14px; text-align: center; font-size: 12px;'>
                    CMCT Salon — Hệ Thống Đặt Lịch & Chăm Sóc Tóc Nam Chuyên Nghiệp
                </div>
            </div>";

            $payload_hoan_thanh_don = [
                'to_email'      => $email_kh,
                'admin_email'   => "",
                'subject'       => $sub_ht,
                'html_body'     => $html_ht,
                'ma_don'        => $id_ht,
                'ho_ten'        => $ten_kh
            ];
            $msg = "✅ Đã xác nhận HOÀN THÀNH đơn cắt tóc #$id_ht của khách hàng $ten_kh và tự động gửi thư cảm ơn tới Gmail: $email_kh!";
        } else {
            $msg = "✅ Đã xác nhận HOÀN THÀNH đơn cắt tóc #$id_ht của khách hàng $ten_kh! (Đơn này chưa có địa chỉ Gmail).";
        }
    }
}

// Nếu bảng khach_hang_vip đang trống hoặc Admin bấm nút "Cập nhật Top 5 VIP", tự động đồng bộ từ bang_dat_lich
$check_vip_count = $conn->query("SELECT COUNT(*) AS c FROM khach_hang_vip");
$vip_count_now = ($check_vip_count && $r_c = $check_vip_count->fetch_assoc()) ? (int)$r_c['c'] : 0;
if ($vip_count_now == 0 || isset($_POST['btn_dong_bo_vip'])) {
    dongBoTop5KhachVIP($conn, $bang_gia_map);
    if (isset($_POST['btn_dong_bo_vip'])) {
        $msg = "👑 Đã cập nhật danh sách Top 5 Khách Hàng VIP và áp dụng gói ưu đãi mới nhất từ lịch sử đặt lịch!";
    }
}

// Xử lý khi Admin chỉnh sửa ưu đãi / mức giảm giá cho 1 khách VIP trong bảng Top 5
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_cap_nhat_uu_dai_vip'])) {
    $id_vip = (int)$_POST['id_vip'];
    $hang_moi = $conn->real_escape_string(trim($_POST['hang_vip']));
    $giam_moi = (int)$_POST['phan_tram_giam'];
    $uu_dai_moi = $conn->real_escape_string(trim($_POST['uu_dai_dac_quyen']));
    $conn->query("UPDATE khach_hang_vip SET hang_vip = '$hang_moi', phan_tram_giam = $giam_moi, uu_dai_dac_quyen = '$uu_dai_moi' WHERE id = $id_vip");
    $msg = "✅ Đã lưu thay đổi gói ưu đãi đặc quyền cho khách hàng VIP!";
}

// 1. XỬ LÝ DUYỆT / KHÓA TÀI KHOẢN KHÁCH HÀNG
if (isset($_GET['duyet_tk'])) {
    $id_tk = (int)$_GET['duyet_tk'];
    $tt = (int)$_GET['tt'];
    $conn->query("UPDATE tai_khoan SET trang_thai_duyet = $tt WHERE id = $id_tk");
    $msg = "✅ Đã cập nhật trạng thái xác minh tài khoản khách hàng!";
}

// 2. XỬ LÝ NHẬP GIỜ BẬN CHO THỢ CẮT TÓC (VD: Bận 7h - 10h)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_them_lich_ban'])) {
    $ktv = $conn->real_escape_string($_POST['ky_thuat_vien']);
    $ngay_ban = $conn->real_escape_string($_POST['ngay_ban']);
    $gio_bd = $conn->real_escape_string($_POST['gio_bat_dau']);
    $gio_kt = $conn->real_escape_string($_POST['gio_ket_thuc']);
    $ly_do = $conn->real_escape_string($_POST['ly_do']);

    $conn->query("INSERT INTO lich_ban_tho (ky_thuat_vien, ngay_ban, gio_bat_dau, gio_ket_thuc, ly_do) 
                  VALUES ('$ktv', '$ngay_ban', '$gio_bd', '$gio_kt', '$ly_do')");
    $msg = "✅ Đã khóa lịch bận cho thợ $ktv ngày $ngay_ban (từ $gio_bd đến $gio_kt)!";
}

if (isset($_GET['xoa_lich_ban'])) {
    $id_lb = (int)$_GET['xoa_lich_ban'];
    $conn->query("DELETE FROM lich_ban_tho WHERE id = $id_lb");
    $msg = "🗑️ Đã xóa khung giờ bận của thợ!";
}

// 3. XỬ LÝ THÊM / CẬP NHẬT BẢNG GIÁ DỊCH VỤ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_them_dich_vu'])) {
    $ten_dv = $conn->real_escape_string(trim($_POST['ten_dich_vu']));
    $gia_1 = (float)$_POST['gia_1'];
    $conn->query("INSERT INTO dich_vu (ten_dich_vu, gia_1) VALUES ('$ten_dv', '$gia_1')");
    $msg = "✅ Đã thêm dịch vụ mới vào bảng giá!";
}

if (isset($_GET['xoa_dv'])) {
    $id_dv = (int)$_GET['xoa_dv'];
    $conn->query("DELETE FROM dich_vu WHERE id = $id_dv");
    $msg = "🗑️ Đã xóa dịch vụ khỏi bảng giá!";
}

// =========================================================================================
// LẤY DANH SÁCH TẤT CẢ GMAIL KHÔNG TRÙNG LẶP TRÊN HỆ THỐNG (TỪ BẢNG tai_khoan & bang_dat_lich)
// =========================================================================================
$ds_tat_ca_gmail = [];
$map_email_seen = [];

$map_vip_emails = [];
$q_vip_em = $conn->query("SELECT email, so_dien_thoai, hang_vip FROM khach_hang_vip");
if ($q_vip_em) {
    while ($rv = $q_vip_em->fetch_assoc()) {
        if (!empty($rv['email'])) {
            $map_vip_emails[strtolower(trim($rv['email']))] = $rv['hang_vip'];
        }
    }
}

$q_tk_em = $conn->query("SELECT ho_ten, email, so_dien_thoai, vai_tro FROM tai_khoan WHERE email != '' ORDER BY id DESC");
if ($q_tk_em) {
    while ($rtk = $q_tk_em->fetch_assoc()) {
        $em_clean = strtolower(trim($rtk['email']));
        if (strpos($em_clean, '@') !== false && !isset($map_email_seen[$em_clean])) {
            $map_email_seen[$em_clean] = true;
            $ds_tat_ca_gmail[] = [
                'ho_ten' => $rtk['ho_ten'],
                'email' => $em_clean,
                'so_dien_thoai' => $rtk['so_dien_thoai'],
                'is_vip' => isset($map_vip_emails[$em_clean]),
                'hang_vip' => $map_vip_emails[$em_clean] ?? 'Thành viên'
            ];
        }
    }
}

$q_dl_em = $conn->query("SELECT ho_ten, email, so_dien_thoai FROM bang_dat_lich WHERE email IS NOT NULL AND email != '' ORDER BY id DESC");
if ($q_dl_em) {
    while ($rdl = $q_dl_em->fetch_assoc()) {
        $em_clean = strtolower(trim($rdl['email']));
        if (strpos($em_clean, '@') !== false && !isset($map_email_seen[$em_clean])) {
            $map_email_seen[$em_clean] = true;
            $ds_tat_ca_gmail[] = [
                'ho_ten' => $rdl['ho_ten'],
                'email' => $em_clean,
                'so_dien_thoai' => $rdl['so_dien_thoai'],
                'is_vip' => isset($map_vip_emails[$em_clean]),
                'hang_vip' => $map_vip_emails[$em_clean] ?? 'Khách đặt lịch'
            ];
        }
    }
}

// TÍNH TOÁN THỐNG KÊ DOANH THU THEO NGÀY, TUẦN, THÁNG
function tinhThongKeChuan($conn, $dieu_kien_sql, $map_gia) {
    $sql = "SELECT dich_vu FROM bang_dat_lich b WHERE $dieu_kien_sql";
    $res = $conn->query($sql);
    $so_don = 0;
    $doanh_thu = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $so_don++;
            $doanh_thu += tinhTienDonDatLich($row['dich_vu'], $map_gia);
        }
    }
    return ['so_don' => $so_don, 'doanh_thu' => $doanh_thu];
}

$tk_ngay  = tinhThongKeChuan($conn, "DATE(b.ngay_gio) = CURDATE()", $bang_gia_map);
$tk_tuan  = tinhThongKeChuan($conn, "YEARWEEK(b.ngay_gio, 1) = YEARWEEK(CURDATE(), 1)", $bang_gia_map);
$tk_thang = tinhThongKeChuan($conn, "MONTH(b.ngay_gio) = MONTH(CURDATE()) AND YEAR(b.ngay_gio) = YEAR(CURDATE())", $bang_gia_map);
$tk_tong  = tinhThongKeChuan($conn, "1=1", $bang_gia_map);

// =========================================================================================
// XỬ LÝ XUẤT BÁO CÁO RA FILE EXCEL (.XLS) GỌN GÀNG, CHUẨN CÚ PHÁP 100%, KHÔNG LỖI FONT
// =========================================================================================
if (isset($_GET['xuat_excel'])) {
    $loai_xuat = $_GET['xuat_excel'];
    $ngay_xuat = date('d/m/Y H:i');
    $file_date = date('d_m_Y');

    if ($loai_xuat === 'dat_lich') {
        $filename = "Bao_Cao_Dat_Lich_Doanh_Thu_CMTC_Salon_" . $file_date . ".xls";
    } else {
        $filename = "Danh_Sach_Khach_Hang_VIP_CMTC_Salon_" . $file_date . ".xls";
    }

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("Pragma: no-cache");
    header("Expires: 0");

    // Xuất BOM UTF-8 để Excel nhận diện chuẩn Tiếng Việt 100%
    echo "\xEF\xBB\xBF";
    echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head>';
    echo '<body style="font-family: Times New Roman, Arial, sans-serif; font-size: 12pt;">';

    $th_style = 'border:1px solid #000; padding:8px 10px; background-color:#0b4a4d; color:#ffffff; font-weight:bold; text-align:center; height:35px;';
    $td_style = 'border:1px solid #000; padding:8px 10px; vertical-align:middle;';
    $td_center = 'border:1px solid #000; padding:8px 10px; vertical-align:middle; text-align:center;';
    $td_phone = 'border:1px solid #000; padding:8px 10px; vertical-align:middle; text-align:center; font-weight:bold; mso-number-format:"\@";';
    $td_money = 'border:1px solid #000; padding:8px 10px; vertical-align:middle; text-align:right; font-weight:bold; color:#c00000; mso-number-format:"\#\,\#\#0";';

    if ($loai_xuat === 'dat_lich') {
        echo '<table style="border-collapse:collapse; width:100%;">';
        echo '<tr><td colspan="11" style="border:1px solid #000; background-color:#111111; color:#e6b96f; font-size:16pt; font-weight:bold; text-align:center; height:45px;">BÁO CÁO DANH SÁCH ĐẶT LỊCH & DOANH THU - CMCT SALON</td></tr>';
        echo '<tr><td colspan="11" style="border:1px solid #000; background-color:#f8f9fa; color:#333333; font-size:11pt; font-style:italic; text-align:center; height:28px;">Thời gian xuất báo cáo: ' . $ngay_xuat . ' — Hệ thống Quản lý CMCT Salon</td></tr>';
        echo '<tr><td colspan="11"></td></tr>';
        echo '<tr>
                <th style="' . $th_style . ' width:50px;">STT</th>
                <th style="' . $th_style . ' width:75px;">Mã Đơn</th>
                <th style="' . $th_style . ' width:180px;">Họ và Tên Khách Hàng</th>
                <th style="' . $th_style . ' width:125px;">Số Điện Thoại</th>
                <th style="' . $th_style . ' width:210px;">Địa Chỉ Email</th>
                <th style="' . $th_style . ' width:230px;">Cơ Sở Salon</th>
                <th style="' . $th_style . ' width:250px;">Gói Dịch Vụ Đăng Ký</th>
                <th style="' . $th_style . ' width:150px;">Kỹ Thuật Viên</th>
                <th style="' . $th_style . ' width:145px;">Thời Gian Đặt</th>
                <th style="' . $th_style . ' width:145px;">Ngày Giờ Hẹn</th>
                <th style="' . $th_style . ' width:135px;">Trạng Thái Đơn</th>
                <th style="' . $th_style . ' width:140px;">Thành Tiền (VNĐ)</th>
              </tr>';

        $sql_ex = "SELECT * FROM bang_dat_lich ORDER BY ngay_gio DESC, id DESC";
        $res_ex = $conn->query($sql_ex);
        $stt = 1;
        $tong_cong = 0;
        if ($res_ex && $res_ex->num_rows > 0) {
            while ($r = $res_ex->fetch_assoc()) {
                $tien = tinhTienDonDatLich($r['dich_vu'], $bang_gia_map);
                $tong_cong += $tien;
                $tt_don = !empty($r['trang_thai']) ? $r['trang_thai'] : 'Chờ phục vụ';
                $tg_dat = !empty($r['thoi_gian_dat']) ? date('H:i - d/m/Y', strtotime($r['thoi_gian_dat'])) : date('H:i - d/m/Y', strtotime($r['ngay_gio']));
                echo '<tr>
                        <td style="' . $td_center . '">' . ($stt++) . '</td>
                        <td style="' . $td_center . ' font-weight:bold;">#' . $r['id'] . '</td>
                        <td style="' . $td_style . ' font-weight:bold;">' . htmlspecialchars($r['ho_ten']) . '</td>
                        <td style="' . $td_phone . '">' . htmlspecialchars($r['so_dien_thoai']) . '</td>
                        <td style="' . $td_style . '">' . htmlspecialchars($r['email'] ?? '') . '</td>
                        <td style="' . $td_style . '">' . htmlspecialchars($r['salon_id']) . '</td>
                        <td style="' . $td_style . '">' . htmlspecialchars($r['dich_vu']) . '</td>
                        <td style="' . $td_center . '">' . htmlspecialchars($r['ky_thuat_vien']) . '</td>
                        <td style="' . $td_center . '">' . $tg_dat . '</td>
                        <td style="' . $td_center . '">' . date('H:i - d/m/Y', strtotime($r['ngay_gio'])) . '</td>
                        <td style="' . $td_center . ' font-weight:bold;">' . htmlspecialchars($tt_don) . '</td>
                        <td style="' . $td_money . '">' . $tien . '</td>
                      </tr>';
            }
        }
        echo '<tr>
                <td colspan="11" style="border:1px solid #000; padding:8px 10px; background-color:#fff3cd; font-weight:bold; font-size:13pt; text-align:right; height:36px;">TỔNG DOANH THU HỆ THỐNG (' . ($stt - 1) . ' ĐƠN):</td>
                <td style="' . $td_money . ' background-color:#fff3cd; font-size:13pt;">' . $tong_cong . '</td>
              </tr>';
        echo '</table>';
    } else {
        echo '<table style="border-collapse:collapse; width:100%;">';
        echo '<tr><td colspan="9" style="border:1px solid #000; background-color:#111111; color:#e6b96f; font-size:16pt; font-weight:bold; text-align:center; height:45px;">DANH SÁCH TOP 5 KHÁCH HÀNG VIP & ĐẶC QUYỀN ƯU ĐÃI - CMCT SALON</td></tr>';
        echo '<tr><td colspan="9" style="border:1px solid #000; background-color:#f8f9fa; color:#333333; font-size:11pt; font-style:italic; text-align:center; height:28px;">Thời gian xuất báo cáo: ' . $ngay_xuat . '</td></tr>';
        echo '<tr><td colspan="9"></td></tr>';
        echo '<tr>
                <th style="' . $th_style . ' width:60px;">Hạng</th>
                <th style="' . $th_style . ' width:200px;">Tên Khách Hàng VIP</th>
                <th style="' . $th_style . ' width:140px;">Số Điện Thoại</th>
                <th style="' . $th_style . ' width:220px;">Email</th>
                <th style="' . $th_style . ' width:110px;">Số Lần Cắt</th>
                <th style="' . $th_style . ' width:150px;">Tổng Chi Tiêu (VNĐ)</th>
                <th style="' . $th_style . ' width:150px;">Hạng Thẻ VIP</th>
                <th style="' . $th_style . ' width:90px;">Giảm Giá</th>
                <th style="' . $th_style . ' width:350px;">Đặc Quyền Ưu Đãi</th>
              </tr>';

        $res_v_ex = $conn->query("SELECT * FROM khach_hang_vip ORDER BY so_lan_cat DESC, tong_chi_tieu DESC LIMIT 5");
        $top_i = 1;
        if ($res_v_ex && $res_v_ex->num_rows > 0) {
            while ($v = $res_v_ex->fetch_assoc()) {
                echo '<tr>
                        <td style="' . $td_center . ' font-weight:bold;">Top ' . ($top_i++) . '</td>
                        <td style="' . $td_style . ' font-weight:bold;">' . htmlspecialchars($v['ho_ten']) . '</td>
                        <td style="' . $td_phone . '">' . htmlspecialchars($v['so_dien_thoai']) . '</td>
                        <td style="' . $td_style . '">' . htmlspecialchars($v['email'] ?? '') . '</td>
                        <td style="' . $td_center . ' font-weight:bold;">' . (int)$v['so_lan_cat'] . ' lần</td>
                        <td style="' . $td_money . '">' . (float)$v['tong_chi_tieu'] . '</td>
                        <td style="' . $td_center . ' font-weight:bold;">' . htmlspecialchars($v['hang_vip']) . '</td>
                        <td style="' . $td_center . ' font-weight:bold;">' . (int)$v['phan_tram_giam'] . '%</td>
                        <td style="' . $td_style . '">' . htmlspecialchars($v['uu_dai_dac_quyen']) . '</td>
                      </tr>';
            }
        }
        echo '</table>';
    }
    echo '</body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị Hệ Thống (Admin Dashboard) - CMCT Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
    body {
        background-color: #f4f6f9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .stat-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .section-card {
        background: #fff;
        border-radius: 14px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
    }

    .vip-card {
        background: linear-gradient(135deg, #1a1d20 0%, #2c3237 100%);
        color: #fff;
        border-radius: 16px;
        border: 2px solid #e6b96f;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        margin-bottom: 24px;
    }

    .vip-table th {
        background-color: #111 !important;
        color: #e6b96f !important;
        border-color: #3c4248 !important;
    }

    .vip-table td {
        background-color: #212529 !important;
        color: #f8f9fa !important;
        border-color: #373b3e !important;
    }

    .broadcast-card {
        background: linear-gradient(135deg, #ffffff 0%, #fdfaf3 100%);
        border: 2px solid #0b4a4d;
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(11, 74, 77, 0.08);
        margin-bottom: 24px;
    }

    .badge-peak {
        background-color: #dc3545;
        color: #fff;
    }

    .dv-tag-admin {
        display: inline-block;
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        border-radius: 6px;
        padding: 2px 7px;
        font-weight: 600;
        font-size: 0.78rem;
        margin: 1px 2px;
    }
    </style>
</head>

<body>

    <!-- THANH NAVBAR ADMIN -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold text-warning" href="admin.php">
                <i class="bi bi-shield-check me-2"></i>CMCT SALON - ADMIN PANEL
            </a>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="#khungGuiMailHangLoat" class="btn btn-sm btn-danger fw-bold">
                    <i class="bi bi-envelope-paper-heart-fill me-1"></i>Gửi Gmail Cho Tất Cả Thành Viên
                    (<?= count($ds_tat_ca_gmail) ?>)
                </a>
                <a href="admin.php?xuat_excel=dat_lich" class="btn btn-sm btn-success fw-bold">
                    <i class="bi bi-file-earmark-excel-fill me-1"></i>Xuất Excel Đơn Đặt Lịch
                </a>
                <a href="admin.php?xuat_excel=vip" class="btn btn-sm btn-warning fw-bold text-dark">
                    <i class="bi bi-award-fill me-1"></i>Xuất Excel Top 5 VIP
                </a>
                <a href="../TrangChu/index.php" class="btn btn-sm btn-outline-warning"><i
                        class="bi bi-house-door me-1"></i>Trang Chủ</a>
                <a href="../DatLich/DatLich.php" class="btn btn-sm btn-outline-light"><i
                        class="bi bi-calendar-plus me-1"></i>Trang Đặt Lịch</a>
                <a href="login.php" class="btn btn-sm btn-outline-info"><i class="bi bi-person-circle me-1"></i>Trang
                    Login</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">
        <?php if ($msg != ""): ?>
        <div class="alert alert-success alert-dismissible fade show fw-semibold shadow-sm" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Hộp thông báo trạng thái gửi Gmail tự động -->
        <div id="alertGuiMailAdmin" class="alert alert-info d-none fw-semibold shadow-sm"></div>

        <!-- HÀNG 1: 4 THẺ THỐNG KÊ DOANH THU THEO NGÀY, TUẦN, THÁNG, TỔNG -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small text-uppercase opacity-75 fw-bold">Doanh Thu Hôm Nay</p>
                            <h4 class="fw-bold mb-0"><?= number_format($tk_ngay['doanh_thu'], 0, ',', '.') ?> VNĐ</h4>
                            <small><?= $tk_ngay['so_don'] ?> đơn đặt lịch hôm nay</small>
                        </div>
                        <i class="bi bi-calendar-day fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small text-uppercase opacity-75 fw-bold">Doanh Thu Tuần Này</p>
                            <h4 class="fw-bold mb-0"><?= number_format($tk_tuan['doanh_thu'], 0, ',', '.') ?> VNĐ</h4>
                            <small><?= $tk_tuan['so_don'] ?> đơn trong tuần</small>
                        </div>
                        <i class="bi bi-calendar-week fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-dark p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small text-uppercase opacity-75 fw-bold">Doanh Thu Tháng <?= date('m/Y') ?>
                            </p>
                            <h4 class="fw-bold mb-0"><?= number_format($tk_thang['doanh_thu'], 0, ',', '.') ?> VNĐ</h4>
                            <small><?= $tk_thang['so_don'] ?> đơn trong tháng</small>
                        </div>
                        <i class="bi bi-calendar-month fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-warning p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 small text-uppercase opacity-75 fw-bold">Tổng Doanh Thu Hệ Thống</p>
                            <h4 class="fw-bold mb-0"><?= number_format($tk_tong['doanh_thu'], 0, ',', '.') ?> VNĐ</h4>
                            <small>Tổng cộng: <?= $tk_tong['so_don'] ?> lượt cắt</small>
                        </div>
                        <i class="bi bi-cash-coin fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================================= -->
        <!-- KHỐI GỬI TIN NHẮN / THÔNG BÁO HÀNG LOẠT TỚI TẤT CẢ GMAIL TRÊN HỆ THỐNG                    -->
        <!-- ========================================================================================= -->
        <div id="khungGuiMailHangLoat" class="card broadcast-card p-4">
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-3 mb-3 gap-2">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-megaphone-fill text-danger me-2"></i>GỬI TIN NHẮN / THÔNG BÁO TỚI TẤT CẢ THÀNH
                        VIÊN & GMAIL TRÊN HỆ THỐNG
                    </h5>
                    <p class="text-muted small mb-0">
                        Tự động lọc toàn bộ <strong><?= count($ds_tat_ca_gmail) ?> địa chỉ Gmail hợp lệ</strong> từ bảng
                        <code>tai_khoan</code> và <code>bang_dat_lich</code> để gửi thông báo khuyến mãi, tri ân hoặc sự
                        kiện của CMCT Salon.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" onclick="dienMauThongBao(1)">
                        🎁 Mẫu Khuyến Mãi Lễ/Tết
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm fw-semibold"
                        onclick="dienMauThongBao(2)">
                        💈 Mẫu Kiểu Tóc / Dịch Vụ Mới
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm fw-semibold"
                        onclick="dienMauThongBao(3)">
                        👑 Mẫu Tri Ân Khách Hàng
                    </button>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold mb-0 text-dark">
                            <i class="bi bi-people-fill me-1 text-primary"></i>1. Chọn danh sách Gmail nhận tin nhắn:
                        </label>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary py-0"
                                onclick="chonTatCaGmail(true)">Chọn tất cả</button>
                            <button type="button" class="btn btn-outline-warning text-dark fw-bold py-0"
                                onclick="chonRiengVIP()">Chỉ chọn VIP</button>
                            <button type="button" class="btn btn-outline-secondary py-0"
                                onclick="chonTatCaGmail(false)">Bỏ chọn</button>
                        </div>
                    </div>

                    <div class="border rounded-3 p-2 bg-white" style="max-height: 210px; overflow-y: auto;">
                        <?php if (count($ds_tat_ca_gmail) > 0): ?>
                        <?php foreach ($ds_tat_ca_gmail as $idx_em => $item_em): ?>
                        <label class="d-flex justify-content-between align-items-center py-1 px-2 border-bottom small"
                            style="cursor: pointer;">
                            <div class="d-flex align-items-center text-truncate pe-2">
                                <input type="checkbox" class="form-check-input me-2 chk-gmail-member"
                                    value="<?= htmlspecialchars($item_em['email']) ?>"
                                    data-ten="<?= htmlspecialchars($item_em['ho_ten']) ?>"
                                    data-vip="<?= $item_em['is_vip'] ? '1' : '0' ?>" checked
                                    onchange="capNhatSoLuongGmailChon()">
                                <div class="text-truncate">
                                    <strong class="text-dark"><?= htmlspecialchars($item_em['ho_ten']) ?></strong>
                                    <span class="text-muted">(<?= htmlspecialchars($item_em['email']) ?>)</span>
                                </div>
                            </div>
                            <?php if ($item_em['is_vip']): ?>
                            <span
                                class="badge bg-warning text-dark flex-shrink-0"><?= htmlspecialchars($item_em['hang_vip']) ?></span>
                            <?php else: ?>
                            <span class="badge bg-light text-secondary border flex-shrink-0">Thành viên</span>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center text-muted small py-4">Chưa có địa chỉ Gmail nào trong CSDL.</div>
                        <?php endif; ?>
                    </div>
                    <small id="demSoGmailChon" class="text-success fw-bold d-block mt-1">
                        Đã chọn: <?= count($ds_tat_ca_gmail) ?> / <?= count($ds_tat_ca_gmail) ?> địa chỉ Gmail
                    </small>
                </div>

                <div class="col-lg-7">
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1 text-dark">
                            <i class="bi bi-card-heading me-1 text-danger"></i>2. Tiêu đề Email gửi cho tất cả thành
                            viên:
                        </label>
                        <input type="text" id="tieuDeBroadcast" class="form-control form-control-sm fw-bold"
                            value="🎁 [CMCT SALON] Ưu đãi đặc biệt & Lời tri ân gửi tới Quý khách hàng!">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-dark">
                            <i class="bi bi-chat-left-text-fill me-1 text-success"></i>3. Nội dung tin nhắn (Hệ thống sẽ
                            tự động chào tên từng khách hàng ở đầu thư):
                        </label>
                        <textarea id="noiDungBroadcast" rows="4"
                            class="form-control form-control-sm">CMCT Salon xin gửi lời cảm ơn chân thành nhất tới Quý khách vì đã luôn đồng hành và ủng hộ Salon trong suốt thời gian qua. Nhân dịp tuần lễ tri ân khách hàng, CMCT Salon dành tặng Quý khách ưu đãi giảm ngay 15% tổng hóa đơn khi đặt lịch cắt tóc, uốn nhuộm hoặc gội dưỡng sinh trực tuyến trên Website. Hãy đặt lịch ngay hôm nay để đội ngũ Stylist phục vụ Quý khách chu đáo nhất!</textarea>
                    </div>

                    <div id="khungTienTrinhBroadcast" class="d-none mb-2">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span id="textTienTrinhBroadcast" class="text-primary">Đang gửi...</span>
                            <span id="phanTramBroadcast">0%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div id="barTienTrinhBroadcast"
                                class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                style="width: 0%"></div>
                        </div>
                    </div>

                    <button type="button" id="btnGuiBroadcast" onclick="guiThongBaoTatCaGmail()"
                        class="btn btn-dark w-100 fw-bold py-2" style="color: #e6b96f;">
                        <i class="bi bi-send-check-fill me-2"></i>GỬI TIN NHẮN TỚI TẤT CẢ GMAIL ĐÃ CHỌN NGAY
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================================= -->
        <!-- BẢNG KHÁCH HÀNG VIP CỦA QUÁN (CHỈ LẤY ĐÚNG 5 KHÁCH HÀNG VIP & ƯU ĐÃI ĐẶC QUYỀN)           -->
        <!-- ========================================================================================= -->
        <div class="card vip-card p-4">
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom border-secondary pb-3 mb-3 gap-3">
                <div>
                    <h5 class="fw-bold text-warning mb-1">
                        <i class="bi bi-award-fill me-2"></i>BẢNG KHÁCH HÀNG VIP CỦA QUÁN (TOP 5 KHÁCH VIP & ƯU ĐÃI ĐẶC
                        QUYỀN)
                    </h5>
                    <p class="text-light opacity-75 small mb-0">
                        Hệ thống tự động chọn lọc đúng <strong>5 khách hàng có số lần cắt và chi tiêu cao nhất</strong>
                        vào bảng <code>khach_hang_vip</code> để trao đặc quyền ưu đãi.
                    </p>
                </div>
                <form method="POST" class="m-0">
                    <button type="submit" name="btn_dong_bo_vip" class="btn btn-warning btn-sm fw-bold px-3">
                        <i class="bi bi-arrow-repeat me-1"></i>Cập Nhật Lại Top 5 VIP Từ Lịch Sử Cắt
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle small vip-table mb-0">
                    <thead class="text-center">
                        <tr>
                            <th style="width: 6%;">Top</th>
                            <th style="width: 18%;">Khách Hàng VIP</th>
                            <th style="width: 12%;">Lịch Sử & Chi Tiêu</th>
                            <th style="width: 14%;">Hạng Thẻ VIP</th>
                            <th style="width: 10%;">Giảm Giá</th>
                            <th style="width: 30%;">Chính Sách Ưu Đãi Đặc Quyền Dành Cho Khách VIP</th>
                            <th style="width: 10%;">Lưu Ưu Đãi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res_vip = $conn->query("SELECT * FROM khach_hang_vip ORDER BY so_lan_cat DESC, tong_chi_tieu DESC LIMIT 5");
                        if ($res_vip && $res_vip->num_rows > 0):
                            $stt_vip = 1;
                            while ($vip = $res_vip->fetch_assoc()):
                                $fid = "form_vip_" . (int)$vip['id'];
                        ?>
                        <tr>
                            <td class="text-center fw-bold fs-6 text-warning">
                                #<?= $stt_vip++ ?>
                                <form id="<?= $fid ?>" method="POST" action="admin.php" class="d-none">
                                    <input type="hidden" name="id_vip" value="<?= (int)$vip['id'] ?>">
                                </form>
                            </td>
                            <td>
                                <strong class="text-warning fs-6"><?= htmlspecialchars($vip['ho_ten']) ?></strong><br>
                                <span class="text-light opacity-75"><i
                                        class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($vip['so_dien_thoai']) ?></span>
                                <?php if (!empty($vip['email'])): ?>
                                <br><small class="text-info"><?= htmlspecialchars($vip['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary mb-1"><?= (int)$vip['so_lan_cat'] ?> lần cắt</span><br>
                                <strong
                                    class="text-success"><?= number_format($vip['tong_chi_tieu'], 0, ',', '.') ?>đ</strong>
                            </td>
                            <td>
                                <select name="hang_vip" form="<?= $fid ?>"
                                    class="form-select form-select-sm bg-dark text-warning border-secondary fw-bold">
                                    <option value="👑 VIP Kim Cương"
                                        <?= ($vip['hang_vip'] == '👑 VIP Kim Cương') ? 'selected' : '' ?>>👑 VIP Kim
                                        Cương</option>
                                    <option value="🥇 VIP Vàng"
                                        <?= ($vip['hang_vip'] == '🥇 VIP Vàng') ? 'selected' : '' ?>>🥇 VIP Vàng
                                    </option>
                                    <option value="🥈 VIP Bạch Kim"
                                        <?= ($vip['hang_vip'] == '🥈 VIP Bạch Kim') ? 'selected' : '' ?>>🥈 VIP Bạch Kim
                                    </option>
                                    <option value="💎 VIP Thân Thiết"
                                        <?= ($vip['hang_vip'] == '💎 VIP Thân Thiết') ? 'selected' : '' ?>>💎 VIP Thân
                                        Thiết</option>
                                </select>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="phan_tram_giam" form="<?= $fid ?>"
                                        class="form-control bg-dark text-warning border-secondary fw-bold text-center"
                                        value="<?= (int)$vip['phan_tram_giam'] ?>" min="5" max="50">
                                    <span class="input-group-text bg-secondary text-white border-secondary">%</span>
                                </div>
                            </td>
                            <td>
                                <input type="text" name="uu_dai_dac_quyen" form="<?= $fid ?>"
                                    class="form-control form-control-sm bg-dark text-light border-secondary"
                                    value="<?= htmlspecialchars($vip['uu_dai_dac_quyen']) ?>">
                            </td>
                            <td class="text-center">
                                <button type="submit" name="btn_cap_nhat_uu_dai_vip" form="<?= $fid ?>"
                                    class="btn btn-outline-warning btn-sm fw-bold w-100">
                                    <i class="bi bi-check2-circle me-1"></i>Lưu
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">Chưa có dữ liệu khách hàng VIP.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4">
            <!-- CỘT TRÁI: DUYỆT TÀI KHOẢN KHÁCH & NHẬP GIỜ BẬN CỦA THỢ -->
            <div class="col-lg-5">

                <!-- KHỐI 1: NHẬP GIỜ BẬN CHO THỢ CẮT TÓC (VD: Bận 7h-10h) -->
                <div class="card section-card p-4">
                    <h5 class="fw-bold text-danger mb-3"><i class="bi bi-clock-fill me-2"></i>Nhập Giờ Bận Cho Thợ (Khóa
                        Lịch Đặt)</h5>
                    <form method="POST" class="row g-2 mb-3 bg-light p-3 rounded-3 border">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Chọn Kỹ thuật viên</label>
                            <select name="ky_thuat_vien" class="form-select form-select-sm" required>
                                <option value="Vừ Xuân Công">Vừ Xuân Công (Hair Artist)</option>
                                <option value="Moong Văn Minh">Moong Văn Minh (Master)</option>
                                <option value="Lầu Bá Trừ">Lầu Bá Trừ (Stylist)</option>
                                <option value="Hờ Bá Chính">Hờ Bá Chính (Stylist)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ngày bận</label>
                            <input type="date" name="ngay_ban" class="form-control form-control-sm"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Từ giờ (VD: 07:00)</label>
                            <input type="time" name="gio_bat_dau" class="form-control form-control-sm" value="07:00"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Đến giờ (VD: 10:00)</label>
                            <input type="time" name="gio_ket_thuc" class="form-control form-control-sm" value="10:00"
                                required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Lý do bận</label>
                            <input type="text" name="ly_do" class="form-control form-control-sm"
                                value="Bận việc gia đình (7h - 10h)" required>
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" name="btn_them_lich_ban" class="btn btn-danger btn-sm w-100 fw-bold">
                                <i class="bi bi-lock-fill me-1"></i>Khóa Khung Giờ Này (Không Cho Khách Đặt)
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle small mb-0">
                            <thead class="table-secondary text-center">
                                <tr>
                                    <th>Thợ cắt</th>
                                    <th>Ngày bận</th>
                                    <th>Khung giờ khóa</th>
                                    <th>Lý do</th>
                                    <th>Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                            $res_lb = $conn->query("SELECT * FROM lich_ban_tho ORDER BY ngay_ban DESC, gio_bat_dau ASC");
                            if ($res_lb && $res_lb->num_rows > 0):
                                while($lb = $res_lb->fetch_assoc()):
                            ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($lb['ky_thuat_vien']) ?></td>
                                    <td class="text-center"><?= date('d/m/Y', strtotime($lb['ngay_ban'])) ?></td>
                                    <td class="text-center text-danger fw-bold"><?= substr($lb['gio_bat_dau'],0,5) ?> -
                                        <?= substr($lb['gio_ket_thuc'],0,5) ?></td>
                                    <td><?= htmlspecialchars($lb['ly_do']) ?></td>
                                    <td class="text-center">
                                        <a href="admin.php?xoa_lich_ban=<?= $lb['id'] ?>"
                                            class="btn btn-outline-danger btn-sm py-0 px-1"><i
                                                class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Chưa có lịch bận nào được thiết lập.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- KHỐI 2: QUẢN LÝ TÀI KHOẢN KHÁCH HÀNG -->
                <div class="card section-card p-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-person-check-fill me-2"></i>Danh Sách Tài
                        Khoản Khách Hàng</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th>Khách hàng</th>
                                    <th>Gmail / SĐT</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                            $res_tk = $conn->query("SELECT * FROM tai_khoan ORDER BY id DESC LIMIT 15");
                            if ($res_tk && $res_tk->num_rows > 0):
                                while($tk = $res_tk->fetch_assoc()):
                            ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($tk['ho_ten']) ?></td>
                                    <td><?= htmlspecialchars($tk['email']) ?><br><span
                                            class="text-muted"><?= htmlspecialchars($tk['so_dien_thoai']) ?></span></td>
                                    <td class="text-center">
                                        <?php if ($tk['trang_thai_duyet'] == 1): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Đã khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($tk['trang_thai_duyet'] == 0): ?>
                                        <a href="admin.php?duyet_tk=<?= $tk['id'] ?>&tt=1"
                                            class="btn btn-success btn-sm fw-bold py-0 px-2">Mở khóa</a>
                                        <?php else: ?>
                                        <a href="admin.php?duyet_tk=<?= $tk['id'] ?>&tt=0"
                                            class="btn btn-outline-secondary btn-sm py-0 px-2">Khóa</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- KHỐI 3: QUẢN LÝ BẢNG GIÁ DỊCH VỤ & SẢN PHẨM -->
                <div class="card section-card p-4">
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-tags-fill me-2"></i>Quản Lý Bảng Giá Dịch Vụ
                        Salon</h5>
                    <form method="POST" class="row g-2 mb-3">
                        <div class="col-7">
                            <input type="text" name="ten_dich_vu" class="form-control form-control-sm"
                                placeholder="Tên dịch vụ mới..." required>
                        </div>
                        <div class="col-3">
                            <input type="number" name="gia_1" class="form-control form-control-sm"
                                placeholder="Giá (x1000đ)" required>
                        </div>
                        <div class="col-2">
                            <button type="submit" name="btn_them_dich_vu"
                                class="btn btn-success btn-sm w-100 fw-bold">Thêm</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle small mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>Tên Dịch Vụ</th>
                                    <th>Đơn Giá Niêm Yết</th>
                                    <th>Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                            $res_dv = $conn->query("SELECT * FROM dich_vu ORDER BY id ASC");
                            if ($res_dv && $res_dv->num_rows > 0):
                                while($dv = $res_dv->fetch_assoc()):
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($dv['ten_dich_vu']) ?></td>
                                    <td class="text-end fw-bold text-success">
                                        <?= number_format($dv['gia_1'] * 1000, 0, ',', '.') ?> VNĐ</td>
                                    <td class="text-center">
                                        <a href="admin.php?xoa_dv=<?= $dv['id'] ?>" class="text-danger"><i
                                                class="bi bi-x-circle-fill"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- CỘT PHẢI: THỐNG KÊ KHUNG GIỜ CAO ĐIỂM, THỐNG KÊ THEO KHÁCH & DANH SÁCH LỊCH ĐẶT (ĐỒNG BỘ DATLICH V5) -->
            <div class="col-lg-7">

                <!-- KHỐI 4: THỐNG KÊ KHUNG GIỜ CAO ĐIỂM & THỐNG KÊ SỐ LẦN CẮT CỦA TỪNG KHÁCH -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card section-card p-3 h-100 mb-0">
                            <h6 class="fw-bold text-dark border-bottom pb-2"><i
                                    class="bi bi-bar-chart-fill text-warning me-2"></i>Thống Kê Theo Giờ (Giờ Cao Điểm)
                            </h6>
                            <table class="table table-sm table-striped small mb-0">
                                <thead>
                                    <tr>
                                        <th>Khung giờ</th>
                                        <th class="text-center">Số lượt đặt</th>
                                        <th>Đánh giá</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                $sql_gio = "SELECT HOUR(ngay_gio) AS gio, COUNT(*) AS so_luong 
                                            FROM bang_dat_lich 
                                            GROUP BY HOUR(ngay_gio) 
                                            ORDER BY so_luong DESC LIMIT 6";
                                $res_gio = $conn->query($sql_gio);
                                if ($res_gio && $res_gio->num_rows > 0):
                                    $max_g = true;
                                    while($g = $res_gio->fetch_assoc()):
                                        $khung = sprintf("%02d:00 - %02d:00", $g['gio'], $g['gio'] + 1);
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?= $khung ?></td>
                                        <td class="text-center fw-bold text-primary"><?= $g['so_luong'] ?> khách</td>
                                        <td>
                                            <?php if ($max_g): $max_g = false; ?>
                                            <span class="badge badge-peak">Đông khách nhất</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Bình thường</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card section-card p-3 h-100 mb-0">
                            <h6 class="fw-bold text-dark border-bottom pb-2"><i
                                    class="bi bi-people-fill text-info me-2"></i>Thống Kê Lịch Sử Cắt Theo Khách Hàng
                            </h6>
                            <table class="table table-sm table-striped small mb-0">
                                <thead>
                                    <tr>
                                        <th>Tên khách hàng</th>
                                        <th>SĐT</th>
                                        <th class="text-center">Tổng lần cắt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                $sql_kh = "SELECT MAX(ho_ten) AS ho_ten, so_dien_thoai, COUNT(*) AS so_lan 
                                           FROM bang_dat_lich 
                                           GROUP BY so_dien_thoai 
                                           ORDER BY so_lan DESC LIMIT 6";
                                $res_kh = $conn->query($sql_kh);
                                if ($res_kh && $res_kh->num_rows > 0):
                                    while($kh = $res_kh->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($kh['ho_ten']) ?></td>
                                        <td><?= htmlspecialchars($kh['so_dien_thoai']) ?></td>
                                        <td class="text-center"><span class="badge bg-primary"><?= $kh['so_lan'] ?>
                                                lần</span></td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- KHỐI 5: DANH SÁCH CHI TIẾT ĐƠN ĐẶT LỊCH (KHỚP 100% VỚI ĐẶT LỊCH V5) -->
                <div class="card section-card p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div>
                            <h5 class="fw-bold text-dark m-0">
                                <i class="bi bi-card-checklist text-success me-2"></i>Danh Sách Nhận Lịch Đặt & Xác Nhận
                                Hoàn Thành Đơn
                            </h5>
                            <small class="text-muted">Đồng bộ với Đặt Lịch v5: Hiển thị nhiều dịch vụ, Thời gian đặt
                                đơn, Giờ hẹn cắt & Gửi mail xác nhận hoàn thành</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="admin.php?xuat_excel=dat_lich" class="btn btn-success btn-sm fw-bold">
                                <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i>Tải File Excel (.xls)
                            </a>
                            <span class="badge bg-dark fs-6">Tổng tiền:
                                <?= number_format($tk_tong['doanh_thu'], 0, ',', '.') ?> VNĐ</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle small">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th>ID</th>
                                    <th>Khách hàng & Liên hệ</th>
                                    <th>Cơ sở Salon</th>
                                    <th>Gói dịch vụ (Chọn nhiều)</th>
                                    <th>Thợ cắt</th>
                                    <th>Thời gian đặt & Giờ hẹn</th>
                                    <th>Thành tiền</th>
                                    <th>Trạng thái & Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                            $sql_all = "SELECT * FROM bang_dat_lich ORDER BY id DESC, ngay_gio DESC LIMIT 30";
                            $res_all = $conn->query($sql_all);
                            if ($res_all && $res_all->num_rows > 0):
                                while($r = $res_all->fetch_assoc()):
                                    $thanh_tien_don = tinhTienDonDatLich($r['dich_vu'], $bang_gia_map);
                                    $da_xong = (isset($r['trang_thai']) && $r['trang_thai'] === 'Đã hoàn thành');
                                    $mang_dv = array_filter(array_map('trim', explode(',', (string)$r['dich_vu'])));
                                    $tg_tao_don = !empty($r['thoi_gian_dat']) ? date('H:i - d/m/Y', strtotime($r['thoi_gian_dat'])) : date('H:i - d/m/Y', strtotime($r['ngay_gio']));
                            ?>
                                <tr class="<?= $da_xong ? 'table-success bg-opacity-10' : '' ?>">
                                    <td class="text-center fw-bold">#<?= $r['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($r['ho_ten']) ?></strong><br>
                                        <span class="text-muted"><?= htmlspecialchars($r['so_dien_thoai']) ?></span>
                                        <?php if (!empty($r['email'])): ?>
                                        <br><small class="text-primary"><?= htmlspecialchars($r['email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($r['salon_id']) ?></td>
                                    <td>
                                        <?php foreach ($mang_dv as $ten_dv_nho): ?>
                                        <span class="dv-tag-admin"><i
                                                class="bi bi-check2 me-1"></i><?= htmlspecialchars($ten_dv_nho) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="fw-semibold text-center"><?= htmlspecialchars($r['ky_thuat_vien']) ?>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted d-block" title="Thời điểm khách đặt đơn">Đặt:
                                            <?= $tg_tao_don ?></small>
                                        <strong class="text-danger" title="Giờ hẹn đến cắt">Hẹn:
                                            <?= date('H:i - d/m/Y', strtotime($r['ngay_gio'])) ?></strong>
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        <?= number_format($thanh_tien_don, 0, ',', '.') ?>đ</td>
                                    <td class="text-center">
                                        <?php if ($da_xong): ?>
                                        <span class="badge bg-success mb-1 d-inline-block">
                                            <i class="bi bi-check-circle-fill me-1"></i>Đã cắt & Hoàn thành
                                        </span><br>
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="admin.php?hoan_thanh_don=<?= $r['id'] ?>"
                                                class="btn btn-outline-success btn-sm py-0 px-2"
                                                style="font-size: 11px;" title="Gửi lại email hoàn thành">
                                                <i class="bi bi-envelope-check"></i> Gửi lại
                                            </a>
                                            <a href="admin.php?xoa_don=<?= $r['id'] ?>"
                                                onclick="return confirm('Bạn có chắc muốn xóa đơn #<?= $r['id'] ?> không?');"
                                                class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 11px;"
                                                title="Xóa đơn này">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark mb-1 d-inline-block">
                                            <i class="bi bi-hourglass-split me-1"></i>Chờ phục vụ
                                        </span><br>
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="admin.php?hoan_thanh_don=<?= $r['id'] ?>"
                                                class="btn btn-success btn-sm fw-bold py-1 px-2"
                                                style="font-size: 11px;">
                                                <i class="bi bi-check2-all me-1"></i>Xác Nhận Đã Cắt & Gửi Mail
                                            </a>
                                            <a href="admin.php?xoa_don=<?= $r['id'] ?>"
                                                onclick="return confirm('Bạn có chắc muốn xóa đơn #<?= $r['id'] ?> không?');"
                                                class="btn btn-outline-danger btn-sm py-1 px-2" style="font-size: 11px;"
                                                title="Xóa đơn này">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const webhookUrl = <?= json_encode($GOOGLE_SCRIPT_WEBHOOK_URL) ?>;
    const payloadHoanThanhDon = <?= json_encode($payload_hoan_thanh_don, JSON_UNESCAPED_UNICODE) ?>;

    // 1. Tự động gửi Gmail xác nhận "Đã cắt & Hoàn thành đơn" khi Admin vừa bấm nút xác nhận
    if (payloadHoanThanhDon && webhookUrl && webhookUrl.trim() !== "") {
        const alertBox = document.getElementById('alertGuiMailAdmin');
        if (alertBox) {
            alertBox.className = "alert alert-info fw-semibold shadow-sm";
            alertBox.innerHTML =
                `<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi thư xác nhận Hoàn Thành Đơn #${payloadHoanThanhDon.ma_don} tới Gmail <strong>${payloadHoanThanhDon.to_email}</strong>...`;
            alertBox.classList.remove('d-none');
        }

        fetch(webhookUrl, {
            method: "POST",
            mode: "no-cors",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payloadHoanThanhDon)
        }).then(() => {
            if (alertBox) {
                alertBox.className = "alert alert-success fw-semibold shadow-sm";
                alertBox.innerHTML =
                    `<i class="bi bi-envelope-check-fill me-2"></i>Đã phát hành thành công Email xác nhận <strong>Đã Cắt & Hoàn Thành Đơn #${payloadHoanThanhDon.ma_don}</strong> tới hộp thư <strong>${payloadHoanThanhDon.to_email}</strong> của khách hàng <strong>${payloadHoanThanhDon.ho_ten}</strong>!`;
            }
        }).catch(err => console.log("Webhook error:", err));
    }

    // 2. Các hàm hỗ trợ chọn danh sách Gmail và điền mẫu thông báo hàng loạt
    function capNhatSoLuongGmailChon() {
        const total = document.querySelectorAll('.chk-gmail-member').length;
        const checked = document.querySelectorAll('.chk-gmail-member:checked').length;
        const el = document.getElementById('demSoGmailChon');
        if (el) {
            el.innerText = `Đã chọn: ${checked} / ${total} địa chỉ Gmail`;
        }
    }

    function chonTatCaGmail(trangThai) {
        document.querySelectorAll('.chk-gmail-member').forEach(chk => {
            chk.checked = trangThai;
        });
        capNhatSoLuongGmailChon();
    }

    function chonRiengVIP() {
        document.querySelectorAll('.chk-gmail-member').forEach(chk => {
            chk.checked = (chk.getAttribute('data-vip') === '1');
        });
        capNhatSoLuongGmailChon();
    }

    function dienMauThongBao(loai) {
        const subEl = document.getElementById('tieuDeBroadcast');
        const bodyEl = document.getElementById('noiDungBroadcast');
        if (loai === 1) {
            subEl.value = "🎁 [CMCT SALON] Ưu đãi giảm giá 20% nhân dịp Tuần Lễ Vàng Tri Ân Khách Hàng!";
            bodyEl.value =
                "Nhân dịp sự kiện Tuần Lễ Vàng tại CMCT Salon, chúng tôi xin gửi tặng Quý khách chương trình ưu đãi giảm ngay 20% tổng hóa đơn cho tất cả các gói Combo Cắt + Gội Dưỡng Sinh, Uốn Định Hình và Nhuộm Thời Trang tại cả 2 cơ sở (TP. Vinh & Kỳ Sơn). Quý khách hãy truy cập Website đặt lịch hẹn trước để giữ khung giờ đẹp nhất nhé!";
        } else if (loai === 2) {
            subEl.value = "💈 [CMCT SALON] Ra mắt Bộ sưu tập Kiểu tóc Nam & Gội Dưỡng Sinh Thảo Dược mới!";
            bodyEl.value =
                "CMCT Salon vừa chính thức cập nhật các xu hướng kiểu tóc nam hot nhất năm nay (Ivy League, Mullet Layer, Side Part rủ...) cùng gói Gội Đầu Thảo Dược Ấn Huyệt Thư Giãn Chuyên Sâu. Mời Quý khách ghé Salon trải nghiệm để làm mới phong cách và tận hưởng phút giây thư giãn tuyệt vời nhất!";
        } else {
            subEl.value = "👑 [CMCT SALON] Thư cảm ơn & Đặc quyền ưu đãi dành cho Thành viên thân thiết!";
            bodyEl.value =
                "Đội ngũ CMCT Salon xin gửi lời tri ân sâu sắc nhất tới Quý khách vì đã luôn tin tưởng lựa chọn dịch vụ của chúng tôi. Tài khoản của Quý khách luôn được tích lũy số lần cắt để hưởng đặc quyền ưu đãi giảm giá từ 10% - 25% và miễn phí gội dưỡng sinh. Rất hân hạnh được đón tiếp Quý khách tại CMCT Salon!";
        }
    }

    // 3. Hàm gửi tin nhắn hàng loạt tới toàn bộ Gmail đã chọn trên hệ thống
    async function guiThongBaoTatCaGmail() {
        const checkedBoxes = Array.from(document.querySelectorAll('.chk-gmail-member:checked'));
        const tieuDe = document.getElementById('tieuDeBroadcast').value.trim();
        const noiDung = document.getElementById('noiDungBroadcast').value.trim();

        if (checkedBoxes.length === 0) {
            alert("⚠️ Vui lòng tích chọn ít nhất 1 địa chỉ Gmail để gửi thông báo!");
            return;
        }
        if (!tieuDe || !noiDung) {
            alert("⚠️ Vui lòng nhập đầy đủ Tiêu đề và Nội dung tin nhắn!");
            return;
        }
        if (!webhookUrl || webhookUrl.trim() === "") {
            alert("⚠️ Chưa cấu hình link Google Apps Script Webhook!");
            return;
        }

        const btn = document.getElementById('btnGuiBroadcast');
        const khungBar = document.getElementById('khungTienTrinhBroadcast');
        const textBar = document.getElementById('textTienTrinhBroadcast');
        const pctBar = document.getElementById('phanTramBroadcast');
        const barEl = document.getElementById('barTienTrinhBroadcast');

        btn.disabled = true;
        khungBar.classList.remove('d-none');

        const tongSo = checkedBoxes.length;
        const noiDungHtmlParagraph = noiDung.replace(/\n/g, "<br>");

        for (let i = 0; i < tongSo; i++) {
            const chk = checkedBoxes[i];
            const emailNhan = chk.value;
            const tenKhach = chk.getAttribute('data-ten') || 'Quý khách';

            textBar.innerHTML = `Đang gửi (${i + 1}/${tongSo}): <strong>${emailNhan}</strong>...`;
            const pct = Math.round(((i + 1) / tongSo) * 100);
            pctBar.innerText = pct + "%";
            barEl.style.width = pct + "%";

            const htmlBody = `
            <div style='font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 14px; overflow: hidden;'>
                <div style='background-color: #111; color: #e6b96f; padding: 24px; text-align: center; border-bottom: 3px solid #c09e6c;'>
                    <h2 style='margin: 0; text-transform: uppercase; letter-spacing: 1px;'>CMCT SALON - THÔNG BÁO ĐẶC BIỆT</h2>
                    <p style='color: #fff; margin: 6px 0 0;'>Hệ Thống Đặt Lịch & Chăm Sóc Tóc Nam Chuyên Nghiệp</p>
                </div>
                <div style='padding: 24px; background-color: #fff; color: #333; line-height: 1.7;'>
                    <p>Xin chào <strong>${tenKhach}</strong>,</p>
                    <div style='background-color: #fdfaf3; border-left: 4px solid #c09e6c; padding: 16px; border-radius: 8px; margin: 16px 0; color: #222; font-size: 14.5px; text-align: justify;'>
                        ${noiDungHtmlParagraph}
                    </div>
                    <p style='margin-top: 18px;'>
                        📍 <strong>Cơ sở 1 (TP. Vinh):</strong> Số 51 Lý Tự Trọng, Phường Vinh Phú, TP. Vinh, Nghệ An<br>
                        📍 <strong>Cơ sở 2 (Kỳ Sơn):</strong> Mường Lống, Huyện Kỳ Sơn, Tỉnh Nghệ An<br>
                        📞 <strong>Hotline hỗ trợ:</strong> 0971.714.681
                    </p>
                </div>
                <div style='background-color: #111; color: #e6b96f; padding: 14px; text-align: center; font-size: 12px;'>
                    CMCT Salon — Định Hình Phong Cách Phái Mạnh
                </div>
            </div>`;

            const payload = {
                to_email: emailNhan,
                admin_email: "",
                subject: tieuDe,
                html_body: htmlBody
            };

            try {
                await fetch(webhookUrl, {
                    method: "POST",
                    mode: "no-cors",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });
                await new Promise(r => setTimeout(r, 350));
            } catch (e) {
                console.log("Send error:", e);
            }
        }

        textBar.innerHTML =
            `<i class="bi bi-check-circle-fill text-success me-1"></i><strong>ĐÃ GỬI THÀNH CÔNG TỚI TOÀN BỘ ${tongSo} ĐỊA CHỈ GMAIL TRÊN HỆ THỐNG!</strong>`;
        btn.disabled = false;
    }
    </script>
</body>

</html>
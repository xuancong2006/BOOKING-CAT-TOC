import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-app.js";
import {
  getAuth,
  GoogleAuthProvider,
  signInWithPopup,
  sendPasswordResetEmail,
} from "https://www.gstatic.com/firebasejs/10.8.1/firebase-auth.js";

// Cấu hình Firebase chuẩn từ dự án của Vừ Xuân Công
const firebaseConfig = {
  apiKey: "AIzaSyCMKM_e3j3z-q3fV7IFYRCtuoCBURwSKXE",
  authDomain: "xuancongmxh.firebaseapp.com",
  databaseURL:
    "https://xuancongmxh-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "xuancongmxh",
  storageBucket: "xuancongmxh.firebasestorage.app",
  messagingSenderId: "417606278037",
  appId: "1:417606278037:web:7c4af2096fe8a0af92749c",
  measurementId: "G-YC876C90Y3",
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
auth.languageCode = "vi";

const alertBox = document.getElementById("alertBox");
const passwordInput = document.getElementById("password");

// Hiện/Ẩn mật khẩu
const togglePassword = document.getElementById("togglePassword");
if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", function () {
    const icon = this.querySelector("i");
    if (passwordInput.type === "password") {
      passwordInput.type = "text";
      icon.classList.replace("fa-eye-slash", "fa-eye");
    } else {
      passwordInput.type = "password";
      icon.classList.replace("fa-eye", "fa-eye-slash");
    }
  });
}

// Xử lý nút Quên mật khẩu qua Gmail
const btnSendReset = document.getElementById("btnSendReset");
if (btnSendReset) {
  btnSendReset.addEventListener("click", async () => {
    const resetEmail = document.getElementById("resetEmailInput").value.trim();
    const resetAlert = document.getElementById("resetAlert");

    if (!resetEmail || !resetEmail.includes("@")) {
      resetAlert.className = "alert alert-warning small py-2";
      resetAlert.innerHTML = "⚠️ Vui lòng nhập đúng định dạng Gmail!";
      resetAlert.classList.remove("d-none");
      return;
    }

    try {
      btnSendReset.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span> Đang gửi...';
      btnSendReset.disabled = true;
      await sendPasswordResetEmail(auth, resetEmail);
      resetAlert.className = "alert alert-success small py-2";
      resetAlert.innerHTML =
        "✅ Đã gửi link khôi phục vào Gmail! Vui lòng kiểm tra hộp thư.";
      resetAlert.classList.remove("d-none");
    } catch (error) {
      resetAlert.className = "alert alert-danger small py-2";
      resetAlert.classList.remove("d-none");
      resetAlert.innerHTML = "❌ Lỗi: " + error.message;
    } finally {
      btnSendReset.innerHTML = "Gửi Email Khôi Phục";
      btnSendReset.disabled = false;
    }
  });
}

// Xử lý Đăng nhập bằng tài khoản Google (Gmail) và đồng bộ về PHP/MySQL
const btnGoogleLogin = document.getElementById("btnGoogleLogin");
if (btnGoogleLogin) {
  btnGoogleLogin.addEventListener("click", async () => {
    if (alertBox) alertBox.classList.add("d-none");
    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: "select_account" });

    try {
      const result = await signInWithPopup(auth, provider);
      const user = result.user;

      // Gửi thông tin Gmail vừa xác thực về login.php để lưu vào bảng tai_khoan MySQL
      const formData = new URLSearchParams();
      formData.append("action", "google_sync");
      formData.append("email", user.email);
      formData.append("ho_ten", user.displayName || "Khách hàng Gmail");
      formData.append("so_dien_thoai", user.phoneNumber || "");

      const res = await fetch("login.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formData.toString(),
      });

      const data = await res.json();
      if (data.status === "success") {
        if (alertBox) {
          alertBox.className = "alert alert-success text-center fw-bold small";
          alertBox.innerHTML =
            "🎉 Đăng nhập Gmail thành công! Đang tải dữ liệu...";
          alertBox.classList.remove("d-none");
        }
        setTimeout(() => {
          window.location.href = data.redirect || "../DatLich/DatLich.php";
        }, 900);
      }
    } catch (error) {
      if (alertBox) {
        alertBox.className = "alert alert-danger text-center fw-bold small";
        alertBox.classList.remove("d-none");
        if (error.code === "auth/popup-closed-by-user") {
          alertBox.innerHTML = "⚠️ Bạn đã đóng cửa sổ đăng nhập Google.";
        } else if (error.code === "auth/unauthorized-domain") {
          alertBox.innerHTML =
            "⚠️ Tên miền hiện tại chưa được thêm vào Authorized Domains trong Firebase Console. Bạn có thể đăng nhập bằng form Email/Mật khẩu bên trên!";
        } else {
          alertBox.innerHTML = "❌ Lỗi đăng nhập Google: " + error.message;
        }
      }
    }
  });
}

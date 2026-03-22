<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "AgriLens");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
$message = "";

/* ================= REGISTER ================= */
if (isset($_POST['register'])) {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

    if (mysqli_num_rows($cek) > 0) {
        $message = "Email sudah terdaftar!";
    } else {
        mysqli_query($conn, "INSERT INTO users (nama, email, password)
        VALUES ('$nama', '$email', '$password')");

        $message = "Registrasi berhasil! Silakan login.";
    }
}

/* ================= LOGIN ================= */
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    $user = mysqli_fetch_assoc($query);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['login'] = true;
        $_SESSION['user'] = $user['nama'];

        header("Location: src/dashboard/konten.html");
        exit;
    } else {
        $message = "Email atau password salah!";
    }
}

/* ================= FORGOT ================= */
if (isset($_POST['forgot'])) {
    $email = $_POST['email'];
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

    if (mysqli_num_rows($cek) > 0) {
        $message = "Simulasi: Link reset password dikirim ke email.";
    } else {
        $message = "Email tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AgriLens - Autentikasi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-image: url('assets/bg.png');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            font-family: 'Poppins', sans-serif;
        }
        .bg-glass {
            background: rgba(10, 10, 10, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .form-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: background 0.3s, border-color 0.3s;
        }
        .form-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #34D399; /* emerald-400 */
        }
        ::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-white"> 

    <main class="w-full max-w-md relative">
        <?php if($message != ""){ ?>
            <p style="text-align:center;color:yellow;margin-bottom:10px;">
                <?php echo $message; ?>
            </p>
        <?php } ?>

        <a href="index.html" class="absolute top-0 left-1/2 -translate-x-1/2 -mt-12 bg-black/50 p-2 rounded-full text-gray-300 hover:text-white hover:bg-green-600 transition-all duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
        </a>

        <!-- LOGIN PAGE -->
        <section id="loginPage" class="bg-glass p-8 rounded-2xl shadow-2xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">🌿 <span class="text-green-400">Agri</span>Lens</h1>
                <p class="text-gray-300 mt-2">Selamat Datang Kembali</p>
            </div>

            <form id="loginForm" method="POST" class="space-y-6">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </span>
                    <input type="email" name="email" placeholder="Email" required
                        class="w-full p-3 pl-10 rounded-lg form-input focus:outline-none focus:ring-2 focus:ring-green-400" />
                </div>
            
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-lock text-gray-400"></i>
                    </span>
                    <input type="password" name="password" placeholder="Password" required
                        class="w-full p-3 pl-10 rounded-lg form-input focus:outline-none focus:ring-2 focus:ring-green-400" />
                </div>
            
                <button type="submit" name="login"
                    class="w-full bg-green-600 text-white font-bold py-3 px-8 rounded-full transition duration-300 transform hover:scale-105 text-lg hover:bg-green-500">
                    Login
                </button>

                <div class="text-center text-sm pt-4 space-y-2">
                    <p>
                        <span onclick="showPage('forgotPage')"
                            class="text-green-300 hover:text-green-200 cursor-pointer font-semibold">Lupa password?</span>
                    </p>
                    <p class="text-gray-300">
                        Belum punya akun? <span onclick="showPage('registerPage')"
                            class="text-green-300 hover:text-green-200 cursor-pointer font-semibold">Daftar sekarang</span>
                    </p>
                </div>
            </form>
        </section>

        <!-- REGISTER PAGE -->
        <section id="registerPage" class="bg-glass p-8 rounded-2xl shadow-2xl hidden">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">🌿 <span class="text-green-400">Agri</span>Lens</h1>
                <p class="text-gray-300 mt-2">Buat Akun Baru</p>
            </div>

            <form class="space-y-5" method="POST">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-user text-gray-400"></i></span>
                    <input type="text" name="nama" placeholder="Nama Lengkap" required class="w-full p-3 pl-10 rounded-lg form-input focus:outline-none focus:ring-2 focus:ring-green-400" />
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-envelope text-gray-400"></i></span>
                    <input type="email" name="email" placeholder="Email" required class="w-full p-3 pl-10 rounded-lg form-input focus:outline-none focus:ring-2 focus:ring-green-400" />
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-lock text-gray-400"></i></span>
                    <input type="password" name="password" id="registerPassword" placeholder="Password" required class="w-full p-3 pl-10 pr-10 rounded-lg form-input focus:outline-none focus:ring-2 focus:ring-green-400" />
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer" onclick="togglePasswordVisibility('registerPassword', 'toggleRegisterPassword')">
                        <i id="toggleRegisterPassword" class="fas fa-eye-slash text-gray-400"></i>
                    </span>
                </div>
                
                <button type="submit" name="register" class="w-full bg-green-600 text-white font-bold py-3 px-8 rounded-full transition duration-300 transform hover:scale-105 text-lg hover:bg-green-500">
                    Register
                </button>

                <p class="text-center text-sm pt-4 text-gray-300">
                    Sudah punya akun? <span onclick="showPage('loginPage')" class="text-green-300 hover:text-green-200 cursor-pointer font-semibold">Login</span>
                </p>
            </form>
        </section>

        <!-- FORGOT PASSWORD PAGE -->
        <section id="forgotPage" class="bg-glass p-8 rounded-2xl shadow-2xl hidden">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">Lupa Password?</h1>
                <p class="text-gray-300 mt-2">Kami akan bantu reset password Anda.</p>
            </div>

            <form class="space-y-6" method="POST">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-envelope text-gray-400"></i></span>
                    <input type="email" name="email" placeholder="Masukkan email terdaftar" required class="w-full p-3 pl-10 rounded-lg form-input focus:outline-none focus:ring-2 focus:ring-green-400" />
                </div>

                <button type="submit" name="forgot" class="w-full bg-green-600 text-white font-bold py-3 px-8 rounded-full transition duration-300 transform hover:scale-105 text-lg hover:bg-green-500">
                    Kirim Link Reset
                </button>

                <p class="text-center text-sm pt-4 text-gray-300">
                    Ingat password? <span onclick="showPage('loginPage')" class="text-green-300 hover:text-green-200 cursor-pointer font-semibold">Kembali ke Login</span>
                </p>
            </form>
        </section>
    </main>

    <script>
        const pages = ['loginPage', 'registerPage', 'forgotPage'];

        function showPage(pageId) {
            pages.forEach(id => {
                document.getElementById(id).classList.toggle('hidden', id !== pageId);
            });
        }

        // Default page
        showPage('loginPage');
    </script>

</body>
</html>

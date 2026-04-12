<?php
// ============================================================
//  AgriLens - Manajemen Konten (Single File)
//  Semua logic backend PHP ada di sini
// ============================================================

// ── CONFIG DATABASE ──────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'agrilens');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', '/uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// ── HELPERS ──────────────────────────────────────────────────
function jsonResponse(bool $success, string $message, $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function clean(?string $v): string {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function uploadImage(array $file, string $sub = ''): ?string {
    if (empty($file['tmp_name'])) return null;

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed))       throw new RuntimeException('Tipe file tidak diizinkan.');
    if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Ukuran file melebihi 2 MB.');

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = uniqid('img_', true) . '.' . $ext;
    $dir  = UPLOAD_DIR . ltrim($sub, '/') . '/';

    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . $name))
        throw new RuntimeException('Gagal menyimpan file.');

    return UPLOAD_URL . ltrim($sub, '/') . '/' . $name;
}

function deleteImageFile(?string $urlPath): void {
    if (!$urlPath) return;
    // UPLOAD_URL = '/uploads/', UPLOAD_DIR = __DIR__ . '/uploads/'
    if (str_starts_with($urlPath, UPLOAD_URL)) {
        $relativePath = substr($urlPath, strlen(UPLOAD_URL));
        $fullPath     = UPLOAD_DIR . $relativePath;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}

// ── ROUTER — hanya aktif ketika request adalah AJAX (?action=...) ──
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$fake   = strtoupper($_POST['_method'] ?? $_GET['_method'] ?? '');
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($action) {
    header('Content-Type: application/json');

    try {
        $pdo = getDB();

        // ════════════════════════════════════
        //  MODULE
        // ════════════════════════════════════
        if ($action === 'modules') {

            // GET — list / detail
            if ($method === 'GET') {
                if ($id) {
                    $s = $pdo->prepare('SELECT * FROM modules WHERE id = ?');
                    $s->execute([$id]);
                    $row = $s->fetch();
                    $row ? jsonResponse(true,'OK',$row) : jsonResponse(false,'Tidak ditemukan.',[],404);
                }
                $rows = $pdo->query('SELECT * FROM modules ORDER BY created_at DESC')->fetchAll();
                jsonResponse(true,'OK',$rows);
            }

            // POST — create
            if ($method === 'POST' && !$fake) {
                $title = clean($_POST['title'] ?? '');
                if (!$title) jsonResponse(false,'Judul wajib diisi.',[],422);

                $image = null;
                if (!empty($_FILES['image']['tmp_name']))
                    $image = uploadImage($_FILES['image'], 'modules');

                $pdo->prepare(
                    'INSERT INTO modules (image,title,short_desc,long_desc,benefits,planting_steps,care_tips)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $image,
                    $title,
                    clean($_POST['short_desc']     ?? ''),
                    clean($_POST['long_desc']       ?? ''),
                    clean($_POST['benefits']        ?? ''),
                    clean($_POST['planting_steps']  ?? ''),
                    clean($_POST['care_tips']       ?? ''),
                ]);
                jsonResponse(true,'Modul berhasil ditambahkan.',['id'=>$pdo->lastInsertId()],201);
            }

            if ($method === 'POST' && $fake === 'PUT' && $id) {
                $s = $pdo->prepare('SELECT * FROM modules WHERE id = ?');
                $s->execute([$id]);
                $ex = $s->fetch();
                if (!$ex) jsonResponse(false,'Tidak ditemukan.',[],404);

                $image = $ex['image']; // Default: pakai gambar lama
                if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Ada file baru → hapus file lama dulu, lalu upload yang baru
                    deleteImageFile($ex['image']);
                    $image = uploadImage($_FILES['image'], 'modules');
                }

                $pdo->prepare(
                    'UPDATE modules SET image=?,title=?,short_desc=?,long_desc=?,benefits=?,planting_steps=?,care_tips=? WHERE id=?'
                )->execute([
                    $image,
                    clean($_POST['title']          ?? $ex['title']),
                    clean($_POST['short_desc']     ?? $ex['short_desc']),
                    clean($_POST['long_desc']      ?? $ex['long_desc']),
                    clean($_POST['benefits']       ?? $ex['benefits']),
                    clean($_POST['planting_steps'] ?? $ex['planting_steps']),
                    clean($_POST['care_tips']      ?? $ex['care_tips']),
                    $id,
                ]);
                jsonResponse(true,'Modul berhasil diperbarui.');
            }

            // POST + _method=DELETE — hapus
            if ($method === 'POST' && $fake === 'DELETE' && $id) {
                $s = $pdo->prepare('SELECT image FROM modules WHERE id = ?');
                $s->execute([$id]);
                $row = $s->fetch();
                if ($row) deleteImageFile($row['image']);

                $pdo->prepare('DELETE FROM modules WHERE id = ?')->execute([$id]);
                jsonResponse(true,'Modul berhasil dihapus.');
            }
        }

        // ════════════════════════════════════
        //  NEWS
        // ════════════════════════════════════
        if ($action === 'news') {

            if ($method === 'GET') {
                if ($id) {
                    $s = $pdo->prepare('SELECT * FROM news WHERE id = ?');
                    $s->execute([$id]);
                    $row = $s->fetch();
                    $row ? jsonResponse(true,'OK',$row) : jsonResponse(false,'Tidak ditemukan.',[],404);
                }
                $rows = $pdo->query('SELECT * FROM news ORDER BY post_date DESC')->fetchAll();
                jsonResponse(true,'OK',$rows);
            }

            if ($method === 'POST' && !$fake) {
                $title = clean($_POST['title'] ?? '');
                if (!$title) jsonResponse(false,'Judul wajib diisi.',[],422);

                $image = null;
                if (!empty($_FILES['image']['tmp_name']))
                    $image = uploadImage($_FILES['image'], 'news');

                $pdo->prepare(
                    'INSERT INTO news (image,title,category,post_date,short_desc,long_desc) VALUES (?,?,?,?,?,?)'
                )->execute([
                    $image,
                    $title,
                    clean($_POST['category']  ?? ''),
                    clean($_POST['post_date'] ?? '') ?: null,
                    clean($_POST['short_desc']?? ''),
                    clean($_POST['long_desc'] ?? ''),
                ]);
                jsonResponse(true,'Berita berhasil ditambahkan.',['id'=>$pdo->lastInsertId()],201);
            }

            if ($method === 'POST' && $fake === 'PUT' && $id) {
                $s = $pdo->prepare('SELECT * FROM news WHERE id = ?');
                $s->execute([$id]);
                $ex = $s->fetch();
                if (!$ex) jsonResponse(false,'Tidak ditemukan.',[],404);

                $image = $ex['image']; // Default: pakai gambar lama
                if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Ada file baru → hapus file lama dulu, lalu upload yang baru
                    deleteImageFile($ex['image']);
                    $image = uploadImage($_FILES['image'], 'news');
                }

                $pdo->prepare(
                    'UPDATE news SET image=?,title=?,category=?,post_date=?,short_desc=?,long_desc=? WHERE id=?'
                )->execute([
                    $image,
                    clean($_POST['title']     ?? $ex['title']),
                    clean($_POST['category']  ?? $ex['category']),
                    clean($_POST['post_date'] ?? $ex['post_date']) ?: null,
                    clean($_POST['short_desc']?? $ex['short_desc']),
                    clean($_POST['long_desc'] ?? $ex['long_desc']),
                    $id,
                ]);
                jsonResponse(true,'Berita berhasil diperbarui.');
            }

            if ($method === 'POST' && $fake === 'DELETE' && $id) {
                $s = $pdo->prepare('SELECT image FROM news WHERE id = ?');
                $s->execute([$id]);
                $row = $s->fetch();
                if ($row) deleteImageFile($row['image']);

                $pdo->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
                jsonResponse(true,'Berita berhasil dihapus.');
            }
        }

        // ════════════════════════════════════
        //  QUIZ
        // ════════════════════════════════════
        if ($action === 'quizzes') {

            if ($method === 'GET') {
                if ($id) {
                    $s = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
                    $s->execute([$id]);
                    $quiz = $s->fetch();
                    if (!$quiz) jsonResponse(false,'Tidak ditemukan.',[],404);

                    if ($quiz['game_type'] === 'pg') {
                        $o = $pdo->prepare('SELECT * FROM quiz_options WHERE quiz_id = ? ORDER BY option_key');
                        $o->execute([$id]);
                        $quiz['options'] = $o->fetchAll();
                    } else {
                        $o = $pdo->prepare('SELECT * FROM quiz_matching_answers WHERE quiz_id = ?');
                        $o->execute([$id]);
                        $quiz['matching_answers'] = $o->fetchAll();
                    }
                    jsonResponse(true,'OK',$quiz);
                }

                $quizzes = $pdo->query('SELECT * FROM quizzes ORDER BY created_at DESC')->fetchAll();
                foreach ($quizzes as &$q) {
                    if ($q['game_type'] === 'pg') {
                        $o = $pdo->prepare('SELECT * FROM quiz_options WHERE quiz_id = ? ORDER BY option_key');
                        $o->execute([$q['id']]);
                        $q['options'] = $o->fetchAll();
                    } else {
                        $o = $pdo->prepare('SELECT * FROM quiz_matching_answers WHERE quiz_id = ?');
                        $o->execute([$q['id']]);
                        $q['matching_answers'] = $o->fetchAll();
                    }
                }
                jsonResponse(true,'OK',$quizzes);
            }

            if ($method === 'POST' && !$fake) {
                $question  = clean($_POST['question']  ?? '');
                $game_type = clean($_POST['game_type'] ?? '');
                if (!$question) jsonResponse(false,'Soal wajib diisi.',[],422);
                if (!in_array($game_type,['pg','matching'])) jsonResponse(false,'Tipe game tidak valid.',[],422);

                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO quizzes (question,game_type) VALUES (?,?)')->execute([$question,$game_type]);
                $qid = (int)$pdo->lastInsertId();

                if ($game_type === 'pg') {
                    $correct = strtoupper(clean($_POST['correct_option'] ?? ''));
                    $ins     = $pdo->prepare('INSERT INTO quiz_options (quiz_id,option_key,option_text,is_correct) VALUES (?,?,?,?)');
                    foreach (['A','B','C','D'] as $k) {
                        $txt = clean($_POST['options'][$k] ?? '');
                        if ($txt) $ins->execute([$qid,$k,$txt,($k===$correct)?1:0]);
                    }
                } else {
                    $ans = clean($_POST['matching_answer'] ?? '');
                    if ($ans) $pdo->prepare('INSERT INTO quiz_matching_answers (quiz_id,answer_text) VALUES (?,?)')->execute([$qid,$ans]);
                }
                $pdo->commit();
                jsonResponse(true,'Quiz berhasil ditambahkan.',['id'=>$qid],201);
            }

            if ($method === 'POST' && $fake === 'PUT' && $id) {
                $question  = clean($_POST['question']  ?? '');
                $game_type = clean($_POST['game_type'] ?? '');
                if (!$question || !in_array($game_type,['pg','matching'])) jsonResponse(false,'Data tidak valid.',[],422);

                $pdo->beginTransaction();
                $pdo->prepare('UPDATE quizzes SET question=?,game_type=? WHERE id=?')->execute([$question,$game_type,$id]);
                $pdo->prepare('DELETE FROM quiz_options WHERE quiz_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM quiz_matching_answers WHERE quiz_id=?')->execute([$id]);

                if ($game_type === 'pg') {
                    $correct = strtoupper(clean($_POST['correct_option'] ?? ''));
                    $ins     = $pdo->prepare('INSERT INTO quiz_options (quiz_id,option_key,option_text,is_correct) VALUES (?,?,?,?)');
                    foreach (['A','B','C','D'] as $k) {
                        $txt = clean($_POST['options'][$k] ?? '');
                        if ($txt) $ins->execute([$id,$k,$txt,($k===$correct)?1:0]);
                    }
                } else {
                    $ans = clean($_POST['matching_answer'] ?? '');
                    if ($ans) $pdo->prepare('INSERT INTO quiz_matching_answers (quiz_id,answer_text) VALUES (?,?)')->execute([$id,$ans]);
                }
                $pdo->commit();
                jsonResponse(true,'Quiz berhasil diperbarui.');
            }

            if ($method === 'POST' && $fake === 'DELETE' && $id) {
                $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$id]);
                jsonResponse(true,'Quiz berhasil dihapus.');
            }
        }

    } catch (Throwable $e) {
        jsonResponse(false, 'Server error: ' . $e->getMessage(), [], 500);
    }

    // Jika action ada tapi tidak match → 404
    jsonResponse(false, 'Action tidak dikenali.', [], 404);
}

// ── Jika bukan AJAX request, render HTML di bawah ───────────
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Manajemen Konten - AgriLens</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100 font-sans leading-normal">
    <div class="flex min-h-screen">

        <!-- SIDEBAR -->
        <aside class="w-64 bg-gray-800 border-r border-gray-700 p-6 fixed h-full overflow-y-auto shadow-xl">
            <h1 class="text-3xl font-extrabold text-green-400 mb-10 tracking-wide">AgriLens</h1>
            <nav class="space-y-3">
                <a href="konten.php"
                    class="block py-2 px-4 rounded-xl bg-green-700 text-white font-semibold shadow-md">Manajemen
                    Konten</a>
                <hr class="border-gray-600 my-5" />
                <a href="profile.html"
                    class="block py-2 px-4 rounded-xl text-gray-300 hover:bg-gray-700 hover:text-green-300 transition">Profile
                    Account</a>
                <a href="../../index.php"
                    class="block py-2 px-4 rounded-xl text-red-400 hover:bg-red-700 hover:text-white transition">Logout</a>
            </nav>
        </aside>

        <!-- MAIN -->
        <main id="mainContent" class="flex-1 ml-64 p-10 space-y-10 transition-all duration-200">

            <header class="flex justify-between items-center bg-gray-800 p-6 rounded-xl shadow-lg">
                <h2 class="text-3xl font-bold text-green-400">Manajemen Konten</h2>
            </header>

            <!-- Toast Notification -->
            <div id="toast"
                class="hidden fixed top-6 right-6 z-[999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium"></div>

            <div class="space-y-10">

                <!-- ── MODULE ─────────────────────────────────────────── -->
                <section class="bg-gray-800 border border-gray-700 p-8 rounded-2xl shadow-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-semibold text-gray-200">Manajemen Module Edukasi</h3>
                        <button onclick="openModal('moduleModal','tambah')"
                            class="bg-green-600 hover:bg-green-500 text-white px-5 py-2 rounded-lg shadow-md transition">+
                            Tambah Module</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-gray-300 border-b border-gray-600 text-sm uppercase tracking-wider">
                                    <th class="py-3 px-4">Judul Module</th>
                                    <th class="py-3 px-4">Deskripsi</th>
                                    <th class="py-3 px-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="moduleTbody">
                                <tr>
                                    <td colspan="3" class="py-4 px-4 text-gray-400 animate-pulse">Memuat data…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- ── NEWS ──────────────────────────────────────────── -->
                <section class="bg-gray-800/60 backdrop-blur border border-gray-700 p-8 rounded-2xl shadow-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-semibold text-gray-200">Manajemen News</h3>
                        <button onclick="openModal('newsModal','tambah')"
                            class="bg-green-600 hover:bg-green-500 text-white px-5 py-2 rounded-lg shadow-md transition">+
                            Tambah News</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-gray-300 border-b border-gray-600 text-sm uppercase tracking-wider">
                                    <th class="py-3 px-4">Judul Konten</th>
                                    <th class="py-3 px-4">Kategori</th>
                                    <th class="py-3 px-4">Tanggal Di Post</th>
                                    <th class="py-3 px-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="newsTbody">
                                <tr>
                                    <td colspan="4" class="py-4 px-4 text-gray-400 animate-pulse">Memuat data…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- ── QUIZ ──────────────────────────────────────────── -->
                <section class="bg-gray-800 border border-gray-700 p-8 rounded-2xl shadow-lg mb-10">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-semibold text-gray-200">Manajemen Quiz Question</h3>
                        <button onclick="openModal('quizModal','tambah')"
                            class="bg-green-600 hover:bg-green-500 text-white px-5 py-2 rounded-lg shadow-md transition">+
                            Tambah Quiz</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-gray-300 border-b border-gray-600 text-sm uppercase tracking-wider">
                                    <th class="py-3 px-4">Soal</th>
                                    <th class="py-3 px-4">Tipe Game</th>
                                    <th class="py-3 px-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="quizTbody">
                                <tr>
                                    <td colspan="3" class="py-4 px-4 text-gray-400 animate-pulse">Memuat data…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- OVERLAY -->
    <div id="globalOverlay" class="hidden fixed inset-0 bg-black/50 z-40"></div>

    <!-- ══════════════════════════════════════════════════════
     MODAL: Module
══════════════════════════════════════════════════════ -->
    <div id="moduleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-gray-900 border border-gray-700 shadow-xl rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="moduleTitleLabel" class="text-xl font-semibold text-gray-100">Tambah Module Edukasi</h2>
                <button onclick="closeModal('moduleModal')"
                    class="text-gray-400 hover:text-gray-200 text-2xl leading-none">✕</button>
            </div>
            <form id="moduleForm" class="space-y-4" enctype="multipart/form-data">
                <input type="hidden" name="_method" id="moduleMethod" value="">
                <input type="hidden" name="id" id="moduleId" value="">

                <div>
                    <label class="block mb-1 font-medium text-gray-300">Gambar</label>
                    <input type="file" name="image" id="moduleImageInput" accept="image/*"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" />
                    <div id="moduleImgWrapper" class="hidden mt-2">
                        <p class="text-xs text-gray-400 mb-1">Gambar saat ini (upload baru untuk mengganti):</p>
                        <img id="moduleImgPreview" class="h-24 rounded-lg object-cover border border-gray-600" />
                    </div>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Judul Module <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="title" id="moduleTitle" placeholder="Masukkan judul module"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" required />
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Deskripsi Pendek</label>
                    <textarea name="short_desc" id="moduleShortDesc" rows="2" placeholder="Deskripsi singkat"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Deskripsi Panjang</label>
                    <textarea name="long_desc" id="moduleLongDesc" rows="4" placeholder="Deskripsi lengkap"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Manfaat Tanaman</label>
                    <textarea name="benefits" id="moduleBenefits" rows="3" placeholder="Manfaat…"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Langkah Menanam</label>
                    <textarea name="planting_steps" id="modulePlantingSteps" rows="3" placeholder="Langkah…"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Tips Perawatan</label>
                    <textarea name="care_tips" id="moduleCareTips" rows="3" placeholder="Tips…"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <button type="submit" id="moduleSubmitBtn"
                    class="w-full bg-green-600 hover:bg-green-500 text-white py-2 rounded-lg shadow transition">
                    Simpan Data
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
     MODAL: News
══════════════════════════════════════════════════════ -->
    <div id="newsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-gray-900 border border-gray-700 shadow-xl rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="newsTitleLabel" class="text-xl font-semibold text-gray-100">Tambah News</h2>
                <button onclick="closeModal('newsModal')"
                    class="text-gray-400 hover:text-gray-200 text-2xl leading-none">✕</button>
            </div>
            <form id="newsForm" class="space-y-4" enctype="multipart/form-data">
                <input type="hidden" name="_method" id="newsMethod" value="">
                <input type="hidden" name="id" id="newsId" value="">

                <div>
                    <label class="block mb-1 font-medium text-gray-300">Gambar</label>
                    <input type="file" name="image" id="newsImageInput" accept="image/*"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" />
                    <div id="newsImgWrapper" class="hidden mt-2">
                        <p class="text-xs text-gray-400 mb-1">Gambar saat ini (upload baru untuk mengganti):</p>
                        <img id="newsImgPreview" class="h-24 rounded-lg object-cover border border-gray-600" />
                    </div>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Judul <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="title" id="newsTitle" placeholder="Judul berita"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" required />
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Kategori</label>
                    <input type="text" name="category" id="newsCategory" placeholder="Organik, Hama, dll."
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" />
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Tanggal Posting</label>
                    <input type="date" name="post_date" id="newsPostDate"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" />
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Deskripsi Pendek</label>
                    <textarea name="short_desc" id="newsShortDesc" rows="2"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Deskripsi Panjang</label>
                    <textarea name="long_desc" id="newsLongDesc" rows="4"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></textarea>
                </div>
                <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-500 text-white py-2 rounded-lg shadow transition">
                    Simpan Data
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
     MODAL: Quiz
══════════════════════════════════════════════════════ -->
    <div id="quizModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-gray-900 border border-gray-700 shadow-xl rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="quizTitleLabel" class="text-xl font-semibold text-gray-100">Tambah Quiz</h2>
                <button onclick="closeModal('quizModal')"
                    class="text-gray-400 hover:text-gray-200 text-2xl leading-none">✕</button>
            </div>
            <form id="quizForm" class="space-y-4">
                <input type="hidden" name="_method" id="quizMethod" value="">
                <input type="hidden" name="id" id="quizId" value="">

                <div>
                    <label class="block mb-1 font-medium text-gray-300">Soal Quiz <span
                            class="text-red-400">*</span></label>
                    <textarea name="question" id="quizQuestion" rows="2" placeholder="Masukkan pertanyaan"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2" required></textarea>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-gray-300">Tipe Game</label>
                    <select name="game_type" id="quizType"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2">
                        <option value="">-- Pilih tipe game --</option>
                        <option value="pg">Pilihan Ganda</option>
                        <option value="matching">Matching Game</option>
                    </select>
                </div>

                <!-- Pilihan Ganda -->
                <div id="pgFields" class="hidden space-y-3">
                    <div><label class="block text-gray-300 mb-1">Opsi A</label><input type="text" name="options[A]"
                            id="optA" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></div>
                    <div><label class="block text-gray-300 mb-1">Opsi B</label><input type="text" name="options[B]"
                            id="optB" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></div>
                    <div><label class="block text-gray-300 mb-1">Opsi C</label><input type="text" name="options[C]"
                            id="optC" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></div>
                    <div><label class="block text-gray-300 mb-1">Opsi D</label><input type="text" name="options[D]"
                            id="optD" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2"></div>
                    <div>
                        <label class="block mb-1 font-medium text-gray-300">Jawaban Benar</label>
                        <select name="correct_option" id="correctOption"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>

                <!-- Matching Game -->
                <div id="matchingField" class="hidden">
                    <label class="block mb-1 font-medium text-gray-300">Jawaban</label>
                    <input type="text" name="matching_answer" id="matchingAnswer" placeholder="Jawaban matching"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2">
                </div>

                <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-500 text-white py-2 rounded-lg shadow transition">
                    Simpan Quiz
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
     MODAL: Konfirmasi Hapus
══════════════════════════════════════════════════════ -->
    <div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="bg-gray-900 border border-red-700 shadow-xl rounded-2xl w-full max-w-sm p-6">
            <h2 class="text-xl font-semibold text-red-400 mb-2">Konfirmasi Hapus</h2>
            <p class="text-gray-300 mb-6">Data yang dihapus tidak dapat dikembalikan. Lanjutkan?</p>
            <div class="flex gap-3">
                <button id="confirmDeleteBtn"
                    class="flex-1 bg-red-600 hover:bg-red-500 text-white py-2 rounded-lg transition">Ya, Hapus</button>
                <button onclick="closeModal('deleteModal')"
                    class="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-2 rounded-lg transition">Batal</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════
     JAVASCRIPT
    ════════════════════════════════════════ -->
    <script>
        // Semua request AJAX mengarah ke file yang sama (konten.php) dengan parameter ?action=
        const BASE = 'konten.php';

        // ── MODAL ────────────────────────────────────────────────────
        const overlay = document.getElementById('globalOverlay');
        const mainContent = document.getElementById('mainContent');

        function openModal(id, mode = 'tambah') {
            mainContent.classList.add('pointer-events-none', 'opacity-40');
            overlay.classList.remove('hidden');
            document.getElementById(id).classList.remove('hidden');
        }
        function closeModal(id) {
            mainContent.classList.remove('pointer-events-none', 'opacity-40');
            overlay.classList.add('hidden');
            document.getElementById(id).classList.add('hidden');
        }
        overlay.onclick = () => ['moduleModal', 'newsModal', 'quizModal', 'deleteModal'].forEach(closeModal);

        // ── TOAST ─────────────────────────────────────────────────────
        function toast(msg, ok = true) {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.className = `fixed top-6 right-6 z-[999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium transition-all
        ${ok ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 3000);
        }

        // ── DELETE CONFIRM ────────────────────────────────────────────
        let _deleteCb = null;
        function confirmDelete(cb) { _deleteCb = cb; openModal('deleteModal'); }
        document.getElementById('confirmDeleteBtn').onclick = () => {
            closeModal('deleteModal');
            if (_deleteCb) { _deleteCb(); _deleteCb = null; }
        };

        // ── API HELPER ────────────────────────────────────────────────
        async function api(action, params = '', opts = {}) {
            const url = `${BASE}?action=${action}${params}`;
            const res = await fetch(url, opts).catch(() => null);
            if (!res) return { success: false, message: 'Koneksi gagal.', data: [] };
            return res.json();
        }

        function esc(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        function fmtDate(iso) {
            if (!iso) return '-';
            return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        function showImgPreview(imgEl, wrapperEl, src) {
            if (src) {
                imgEl.src = src;
                wrapperEl.classList.remove('hidden');
            } else {
                imgEl.src = '';
                wrapperEl.classList.add('hidden');
            }
        }

        document.getElementById('moduleImageInput').addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => showImgPreview(
                    document.getElementById('moduleImgPreview'),
                    document.getElementById('moduleImgWrapper'),
                    e.target.result
                );
                reader.readAsDataURL(this.files[0]);
            }
        });

        document.getElementById('newsImageInput').addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => showImgPreview(
                    document.getElementById('newsImgPreview'),
                    document.getElementById('newsImgWrapper'),
                    e.target.result
                );
                reader.readAsDataURL(this.files[0]);
            }
        });

        // ══════════════════════════════════════════════════════════
        //  MODULE
        // ══════════════════════════════════════════════════════════
        async function loadModules() {
            const tb = document.getElementById('moduleTbody');
            const r = await api('modules');
            if (!r.success) { tb.innerHTML = `<tr><td colspan="3" class="py-4 px-4 text-red-400">Gagal memuat data.</td></tr>`; return; }
            if (!r.data.length) { tb.innerHTML = `<tr><td colspan="3" class="py-4 px-4 text-gray-400">Belum ada data.</td></tr>`; return; }

            tb.innerHTML = r.data.map(m => `
        <tr class="border-b border-gray-700 hover:bg-gray-700">
            <td class="py-3 px-4 font-medium">${esc(m.title)}</td>
            <td class="py-3 px-4 text-sm text-gray-400">${esc(m.short_desc)}</td>
            <td class="py-3 px-4">
                <div class="flex gap-3">
                    <button onclick="editModule(${m.id})" class="text-blue-400 hover:text-blue-300 hover:underline text-sm">Edit</button>
                    <button onclick="deleteModule(${m.id})" class="text-red-400 hover:text-red-300 hover:underline text-sm">Delete</button>
                </div>
            </td>
        </tr>`).join('');
        }

        async function editModule(id) {
            const r = await api('modules', `&id=${id}`);
            if (!r.success) { toast('Gagal memuat data.', false); return; }
            const m = r.data;

            document.getElementById('moduleTitleLabel').textContent = 'Edit Module Edukasi';
            document.getElementById('moduleMethod').value = 'PUT';
            document.getElementById('moduleId').value = id;
            document.getElementById('moduleTitle').value = m.title ?? '';
            document.getElementById('moduleShortDesc').value = m.short_desc ?? '';
            document.getElementById('moduleLongDesc').value = m.long_desc ?? '';
            document.getElementById('moduleBenefits').value = m.benefits ?? '';
            document.getElementById('modulePlantingSteps').value = m.planting_steps ?? '';
            document.getElementById('moduleCareTips').value = m.care_tips ?? '';

            showImgPreview(
                document.getElementById('moduleImgPreview'),
                document.getElementById('moduleImgWrapper'),
                m.image || null
            );
            // Reset input file supaya tidak kirim file kosong sebagai "upload baru"
            document.getElementById('moduleImageInput').value = '';

            openModal('moduleModal');
        }

        function resetModuleForm() {
            document.getElementById('moduleTitleLabel').textContent = 'Tambah Module Edukasi';
            document.getElementById('moduleMethod').value = '';
            document.getElementById('moduleId').value = '';
            document.getElementById('moduleForm').reset();
            // Sembunyikan preview gambar saat reset
            document.getElementById('moduleImgWrapper').classList.add('hidden');
            document.getElementById('moduleImgPreview').src = '';
        }

        document.getElementById('moduleForm').onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const id = document.getElementById('moduleId').value;
            const r = await api('modules', id ? `&id=${id}` : '', { method: 'POST', body: fd });
            toast(r.message, r.success);
            if (r.success) { closeModal('moduleModal'); resetModuleForm(); loadModules(); }
        };

        function deleteModule(id) {
            confirmDelete(async () => {
                const fd = new FormData(); fd.append('_method', 'DELETE');
                const r = await api('modules', `&id=${id}`, { method: 'POST', body: fd });
                toast(r.message, r.success);
                if (r.success) loadModules();
            });
        }

        // ══════════════════════════════════════════════════════════
        //  NEWS
        // ══════════════════════════════════════════════════════════
        async function loadNews() {
            const tb = document.getElementById('newsTbody');
            const r = await api('news');
            if (!r.success) { tb.innerHTML = `<tr><td colspan="4" class="py-4 px-4 text-red-400">Gagal memuat data.</td></tr>`; return; }
            if (!r.data.length) { tb.innerHTML = `<tr><td colspan="4" class="py-4 px-4 text-gray-400">Belum ada data.</td></tr>`; return; }

            tb.innerHTML = r.data.map(n => `
        <tr class="border-b border-gray-700 hover:bg-gray-700">
            <td class="py-3 px-4 font-medium">${esc(n.title)}</td>
            <td class="py-3 px-4">${esc(n.category)}</td>
            <td class="py-3 px-4">${fmtDate(n.post_date)}</td>
            <td class="py-3 px-4">
                <div class="flex gap-3">
                    <button onclick="editNews(${n.id})" class="text-blue-400 hover:text-blue-300 hover:underline text-sm">Edit</button>
                    <button onclick="deleteNews(${n.id})" class="text-red-400 hover:text-red-300 hover:underline text-sm">Delete</button>
                </div>
            </td>
        </tr>`).join('');
        }

        async function editNews(id) {
            const r = await api('news', `&id=${id}`);
            if (!r.success) { toast('Gagal memuat data.', false); return; }
            const n = r.data;

            document.getElementById('newsTitleLabel').textContent = 'Edit News';
            document.getElementById('newsMethod').value = 'PUT';
            document.getElementById('newsId').value = id;
            document.getElementById('newsTitle').value = n.title ?? '';
            document.getElementById('newsCategory').value = n.category ?? '';
            document.getElementById('newsPostDate').value = n.post_date ?? '';
            document.getElementById('newsShortDesc').value = n.short_desc ?? '';
            document.getElementById('newsLongDesc').value = n.long_desc ?? '';

            showImgPreview(
                document.getElementById('newsImgPreview'),
                document.getElementById('newsImgWrapper'),
                n.image || null
            );
            // Reset input file supaya tidak kirim file kosong sebagai "upload baru"
            document.getElementById('newsImageInput').value = '';

            openModal('newsModal');
        }

        function resetNewsForm() {
            document.getElementById('newsTitleLabel').textContent = 'Tambah News';
            document.getElementById('newsMethod').value = '';
            document.getElementById('newsId').value = '';
            document.getElementById('newsForm').reset();
            // Sembunyikan preview gambar saat reset
            document.getElementById('newsImgWrapper').classList.add('hidden');
            document.getElementById('newsImgPreview').src = '';
        }

        document.getElementById('newsForm').onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const id = document.getElementById('newsId').value;
            const r = await api('news', id ? `&id=${id}` : '', { method: 'POST', body: fd });
            toast(r.message, r.success);
            if (r.success) { closeModal('newsModal'); resetNewsForm(); loadNews(); }
        };

        function deleteNews(id) {
            confirmDelete(async () => {
                const fd = new FormData(); fd.append('_method', 'DELETE');
                const r = await api('news', `&id=${id}`, { method: 'POST', body: fd });
                toast(r.message, r.success);
                if (r.success) loadNews();
            });
        }

        // ══════════════════════════════════════════════════════════
        //  QUIZ
        // ══════════════════════════════════════════════════════════
        document.getElementById('quizType').onchange = function () {
            document.getElementById('pgFields').classList.toggle('hidden', this.value !== 'pg');
            document.getElementById('matchingField').classList.toggle('hidden', this.value !== 'matching');
        };

        async function loadQuizzes() {
            const tb = document.getElementById('quizTbody');
            const r = await api('quizzes');
            if (!r.success) { tb.innerHTML = `<tr><td colspan="3" class="py-4 px-4 text-red-400">Gagal memuat data.</td></tr>`; return; }
            if (!r.data.length) { tb.innerHTML = `<tr><td colspan="3" class="py-4 px-4 text-gray-400">Belum ada data.</td></tr>`; return; }

            tb.innerHTML = r.data.map(q => {
                const badge = q.game_type === 'pg'
                    ? `<span class="bg-yellow-500/20 text-yellow-400 text-xs font-semibold px-2.5 py-0.5 rounded-full">Pilihan Ganda</span>`
                    : `<span class="bg-green-500/20 text-green-400 text-xs font-semibold px-2.5 py-0.5 rounded-full">Matching Game</span>`;
                return `
        <tr class="border-b border-gray-700 hover:bg-gray-700">
            <td class="py-3 px-4">${esc(q.question)}</td>
            <td class="py-3 px-4">${badge}</td>
            <td class="py-3 px-4">
                <div class="flex gap-3">
                    <button onclick="editQuiz(${q.id})" class="text-blue-400 hover:text-blue-300 hover:underline text-sm">Edit</button>
                    <button onclick="deleteQuiz(${q.id})" class="text-red-400 hover:text-red-300 hover:underline text-sm">Delete</button>
                </div>
            </td>
        </tr>`;
            }).join('');
        }

        async function editQuiz(id) {
            const r = await api('quizzes', `&id=${id}`);
            if (!r.success) { toast('Gagal memuat data.', false); return; }
            const q = r.data;

            document.getElementById('quizTitleLabel').textContent = 'Edit Quiz';
            document.getElementById('quizMethod').value = 'PUT';
            document.getElementById('quizId').value = id;
            document.getElementById('quizQuestion').value = q.question;

            const sel = document.getElementById('quizType');
            sel.value = q.game_type;
            sel.dispatchEvent(new Event('change'));

            if (q.game_type === 'pg' && q.options) {
                q.options.forEach(o => {
                    const el = document.getElementById('opt' + o.option_key);
                    if (el) el.value = o.option_text;
                });
                const correct = q.options.find(o => o.is_correct == 1);
                if (correct) document.getElementById('correctOption').value = correct.option_key;
            } else if (q.game_type === 'matching' && q.matching_answers?.length) {
                document.getElementById('matchingAnswer').value = q.matching_answers[0].answer_text;
            }

            openModal('quizModal');
        }

        function resetQuizForm() {
            document.getElementById('quizTitleLabel').textContent = 'Tambah Quiz';
            document.getElementById('quizMethod').value = '';
            document.getElementById('quizId').value = '';
            document.getElementById('quizForm').reset();
            document.getElementById('pgFields').classList.add('hidden');
            document.getElementById('matchingField').classList.add('hidden');
        }

        document.getElementById('quizForm').onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const id = document.getElementById('quizId').value;
            const r = await api('quizzes', id ? `&id=${id}` : '', { method: 'POST', body: fd });
            toast(r.message, r.success);
            if (r.success) { closeModal('quizModal'); resetQuizForm(); loadQuizzes(); }
        };

        function deleteQuiz(id) {
            confirmDelete(async () => {
                const fd = new FormData(); fd.append('_method', 'DELETE');
                const r = await api('quizzes', `&id=${id}`, { method: 'POST', body: fd });
                toast(r.message, r.success);
                if (r.success) loadQuizzes();
            });
        }

        // ── INIT ─────────────────────────────────────────────────────
        loadModules();
        loadNews();
        loadQuizzes();
    </script>

    <style>
        @keyframes scaleIn {
            from {
                transform: scale(.97);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        #moduleModal>div,
        #newsModal>div,
        #quizModal>div,
        #deleteModal>div {
            animation: scaleIn .15s ease;
        }
    </style>

</body>

</html>
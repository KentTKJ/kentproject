<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
const DB_HOST = 'localhost';
const DB_NAME = 'mbg_sekolah';
const DB_USER = 'root';
const DB_PASS = '';

function db() {
    static $p;
    if (!$p) {
        $p = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $p;
}
// Samakan nama kelas: huruf besar, spasi dan tanda hubung dianggap sama.
function norm_kelas($s) { return preg_replace('/[\s-]+/', ' ', strtoupper(trim($s))); }
// Daftar kelas. Contoh penamaan dengan jurusan ada di README.txt (maksimal 10 karakter per nama).
function kelas_list() {
    return [
        'X AK 1', 'X AK 2', 'X MP 1', 'X MP 2',
        'X TKJ 1', 'X TKJ 2', 'X TKJ 3', 'X TKJ 4', 'X DKV', 'X PM',
        'XI AK 1', 'XI AK 2', 'XI MP 1', 'XI MP 2',
        'XI TKJ 1', 'XI TKJ 2', 'XI TKJ 3', 'XI TKJ 4', 'XI DKV', 'XI PM',
        'XII AK 1', 'XII AK 2', 'XII MP 1', 'XII MP 2',
        'XII TKJ 1', 'XII TKJ 2', 'XII TKJ 3', 'XII DKV', 'XII PM',
    ];
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function isi_ikat() {
    $r = db()->query("SELECT v FROM pengaturan WHERE k='isi_ikat'")->fetchColumn();
    return max(1, (int)($r ?: 7));
}
function ambil_txt($n) {
    $p = isi_ikat();
    if ($n <= 0) return '0';
    $f = intdiv($n, $p);
    $l = $n % $p;
    if ($f && $l) return "$f ikat + $l ompreng";
    return $f ? "$f ikat" : "$l ompreng";
}
function user() { return $_SESSION['u'] ?? null; }
function require_role(array $roles) {
    $u = user();
    if (!$u) { header('Location: login.php'); exit; }
    if (!in_array($u['role'], $roles, true) && $u['role'] !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak');
    }
}
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function check_csrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token tidak valid, muat ulang halaman.');
    }
}
function tgl_indo() {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bln = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $hari[(int)date('w')] . ', ' . date('j') . ' ' . $bln[(int)date('n')] . ' ' . date('Y');
}
function head($title) {
    $u = user();
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . ' - MBG Sekolah</title><style>' . <<<CSS
:root{--bg:#f6f7f4;--card:#fff;--tx:#1f2a24;--mut:#66726b;--bd:#dde3dd;--ac:#2e7d4f;--acx:#fff;--warn:#b26a00;--ok:#2e7d4f;--err:#b3261e}
@media (prefers-color-scheme:dark){:root{--bg:#151a17;--card:#1e2521;--tx:#e8eee9;--mut:#9aa8a0;--bd:#2f3a34;--ac:#4cb87c;--acx:#0d1a12;--warn:#e0a040;--ok:#4cb87c;--err:#f2b8b5}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:780px;margin:0 auto;padding:16px}
h1{font-size:1.35rem;margin:0}h2{font-size:1rem;margin:0 0 6px}
.sub{color:var(--mut);font-size:.9rem;margin:2px 0 14px}
nav{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
nav a{padding:8px 14px;border:1px solid var(--bd);background:var(--card);color:var(--tx);border-radius:10px;text-decoration:none;font-size:.9rem}
nav a.on{background:var(--ac);color:var(--acx);border-color:var(--ac);font-weight:600}
nav span{margin-left:auto;align-self:center;color:var(--mut);font-size:.85rem}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:14px}
label.f{display:block;font-size:.85rem;color:var(--mut);margin:10px 0 4px}
select,input[type=text],input[type=password],input[type=number],textarea{width:100%;padding:10px;border:1px solid var(--bd);border-radius:10px;background:var(--bg);color:var(--tx);font:inherit}
textarea{min-height:140px;resize:vertical}
.btn{margin-top:12px;width:100%;padding:12px;border:0;border-radius:10px;background:var(--ac);color:var(--acx);font:inherit;font-weight:600;cursor:pointer}
.btn.sm{width:auto;margin:0;padding:6px 12px;font-size:.85rem}
.btn.ghost{background:transparent;color:var(--ac);border:1px solid var(--ac)}
.hero{text-align:center;padding:18px}
.hero b{display:block;font-size:2.4rem;line-height:1.2;color:var(--ac)}
.hero span{color:var(--mut);font-size:.9rem}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}
.stat{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:12px;text-align:center}
.stat b{display:block;font-size:1.5rem}.stat span{font-size:.78rem;color:var(--mut)}
.wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.95rem}
th,td{padding:9px 6px;text-align:left;border-bottom:1px solid var(--bd);white-space:nowrap}
th{color:var(--mut);font-weight:500;font-size:.8rem}
tr.g td{background:var(--bg);font-weight:600;font-size:.85rem;color:var(--mut)}
.tag{padding:2px 8px;border-radius:99px;font-size:.78rem;border:1px solid var(--bd)}
.tag.ok{color:var(--ok);border-color:var(--ok)}.tag.w{color:var(--warn);border-color:var(--warn)}
.msg{font-size:.9rem;margin:10px 0;color:var(--ok)}.msg.err{color:var(--err)}
.cb{display:flex;align-items:center;gap:10px;padding:8px 2px;border-bottom:1px solid var(--bd)}
.cb input{width:20px;height:20px;flex:none}
.cnt{font-weight:600;margin:10px 0 4px}
.mut{color:var(--mut);font-size:.88rem}
CSS . '</style></head><body><main>';
    echo '<h1>Sistem MBG Sekolah</h1><p class="sub">' . h(tgl_indo()) . '</p>';
    if ($u) {
        $cur = basename($_SERVER['SCRIPT_NAME']);
        $links = [];
        if ($u['role'] === 'admin') $links['admin.php'] = 'Admin';
        if (in_array($u['role'], ['admin', 'piket'], true)) $links['piket.php'] = 'Guru Piket';
        if (in_array($u['role'], ['admin', 'pembagi'], true)) $links['pembagi.php'] = 'Guru Pembagi';
        echo '<nav>';
        foreach ($links as $f => $t) echo '<a href="' . $f . '"' . ($f === $cur ? ' class="on"' : '') . '>' . $t . '</a>';
        echo '<span>' . h($u['username']) . ' · <a href="logout.php" style="padding:0;border:0;background:none;color:var(--ac)">Keluar</a></span></nav>';
    }
}
function foot() { echo '</main></body></html>'; }

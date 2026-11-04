<?php
require 'lib.php';
require_role(['admin']);
$kelas = kelas_list();
$cur = $_REQUEST['kelas'] ?? $kelas[0];
if (!in_array($cur, $kelas, true)) $cur = $kelas[0];
$msg = ''; $err = '';

if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template-siswa.csv"');
    echo "\xEF\xBB\xBF" . "Kelas;Nama\r\n" . $kelas[0] . ";Contoh Nama Siswa Satu\r\n" . $kelas[0] . ";Contoh Nama Siswa Dua\r\n" . ($kelas[1] ?? $kelas[0]) . ";Contoh Nama Siswa Tiga\r\n";
    exit;
}

function kolom_idx($huruf) {
    $n = 0;
    foreach (str_split(strtoupper($huruf)) as $c) $n = $n * 26 + (ord($c) - 64);
    return $n - 1;
}
function baca_csv($path) {
    $txt = file_get_contents($path);
    $txt = preg_replace('/^\xEF\xBB\xBF/', '', $txt);
    $lines = preg_split('/\R/', $txt);
    $first = $lines[0] ?? '';
    $delim = ',';
    if (substr_count($first, ';') > substr_count($first, ',')) $delim = ';';
    if (substr_count($first, "\t") > substr_count($first, $delim)) $delim = "\t";
    $rows = [];
    foreach ($lines as $ln) {
        if (trim($ln) === '') continue;
        $c = str_getcsv($ln, $delim);
        $rows[] = [trim($c[0] ?? ''), trim($c[1] ?? '')];
    }
    return $rows;
}
function baca_xlsx($path) {
    if (!class_exists('ZipArchive')) throw new Exception('Ekstensi PHP zip belum aktif. Gunakan file CSV, atau aktifkan extension=zip di php.ini.');
    $z = new ZipArchive;
    if ($z->open($path) !== true) throw new Exception('File xlsx tidak bisa dibuka.');
    $ss = [];
    $x = $z->getFromName('xl/sharedStrings.xml');
    if ($x !== false) {
        $sx = simplexml_load_string($x);
        foreach ($sx->si as $si) {
            if (isset($si->t)) $t = (string)$si->t;
            else { $t = ''; foreach ($si->r as $r) $t .= (string)$r->t; }
            $ss[] = $t;
        }
    }
    $names = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n)) $names[] = $n;
    }
    sort($names, SORT_NATURAL);
    if (!$names) throw new Exception('Sheet tidak ditemukan di file xlsx.');
    $d = simplexml_load_string($z->getFromName($names[0]));
    $rows = [];
    foreach ($d->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $col = kolom_idx(preg_replace('/\d+/', '', (string)$c['r']));
            $t = (string)$c['t'];
            if ($t === 's') $v = $ss[(int)$c->v] ?? '';
            elseif ($t === 'inlineStr') $v = (string)$c->is->t;
            else $v = (string)$c->v;
            $r[$col] = trim($v);
        }
        $rows[] = [$r[0] ?? '', $r[1] ?? ''];
    }
    $z->close();
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['aksi'] ?? '';
    if ($a === 'siswa') {
        $names = [];
        foreach (preg_split('/\R/', $_POST['daftar'] ?? '') as $n) {
            $n = trim($n);
            if ($n !== '' && !in_array($n, $names, true)) $names[] = mb_substr($n, 0, 100);
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM siswa WHERE kelas = ?')->execute([$cur]);
        $ins = $pdo->prepare('INSERT INTO siswa (kelas, nama) VALUES (?, ?)');
        foreach ($names as $n) $ins->execute([$cur, $n]);
        $pdo->commit();
        $msg = "Daftar kelas $cur tersimpan (" . count($names) . ' siswa).';
    } elseif ($a === 'upload') {
        try {
            $f = $_FILES['berkas'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new Exception('Pilih file terlebih dahulu (atau ukuran file terlalu besar).');
            if ($f['size'] > 5 * 1024 * 1024) throw new Exception('Ukuran file maksimal 5 MB.');
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($ext === 'xlsx') $pairs = baca_xlsx($f['tmp_name']);
            elseif ($ext === 'csv') $pairs = baca_csv($f['tmp_name']);
            else throw new Exception('Format file harus .xlsx atau .csv.');
            $valid = [];
            foreach ($kelas as $kk) $valid[norm_kelas($kk)] = $kk;
            $per = [];
            $skip = [];
            foreach ($pairs as $i => $pr) {
                $k = norm_kelas($pr[0]);
                $n = trim($pr[1]);
                if ($i === 0 && $k === 'KELAS') continue;
                if ($k === '' && $n === '') continue;
                if (!isset($valid[$k])) { $skip[$k] = true; continue; }
                $k = $valid[$k];
                if ($n === '') continue;
                $n = mb_substr($n, 0, 100);
                if (!isset($per[$k]) || !in_array($n, $per[$k], true)) $per[$k][] = $n;
            }
            if (!$per) throw new Exception('Tidak ada data siswa yang terbaca. Pastikan kolom A berisi Kelas (misalnya X-1) dan kolom B berisi Nama.');
            $pdo = db();
            $pdo->beginTransaction();
            $del = $pdo->prepare('DELETE FROM siswa WHERE kelas = ?');
            $ins = $pdo->prepare('INSERT INTO siswa (kelas, nama) VALUES (?, ?)');
            $jumlah = 0;
            foreach ($per as $k => $list) {
                $del->execute([$k]);
                foreach ($list as $n) { $ins->execute([$k, $n]); $jumlah++; }
            }
            $pdo->commit();
            $msg = "Berhasil: $jumlah siswa di " . count($per) . ' kelas.';
            if ($skip) $msg .= ' Baris dilewati karena nama kelas tidak dikenal: ' . implode(', ', array_slice(array_map(function ($x) { return $x === '' ? '(kosong)' : $x; }, array_keys($skip)), 0, 5)) . '.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $err = $e->getMessage();
        }
    } elseif ($a === 'isi') {
        $v = (int)($_POST['isi'] ?? 0);
        if ($v > 0) {
            db()->prepare("REPLACE INTO pengaturan (k, v) VALUES ('isi_ikat', ?)")->execute([(string)$v]);
            $msg = 'Isi per ikat tersimpan.';
        } else $err = 'Isi per ikat harus lebih dari 0.';
    } elseif ($a === 'pw') {
        $un = $_POST['username'] ?? '';
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) < 6) $err = 'Password minimal 6 karakter.';
        else {
            $u = db()->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
            $u->execute([password_hash($pw, PASSWORD_DEFAULT), $un]);
            $msg = 'Password ' . $un . ' diperbarui.';
        }
    }
}

$st = db()->prepare('SELECT nama FROM siswa WHERE kelas = ? ORDER BY id');
$st->execute([$cur]);
$daftar = implode("\n", $st->fetchAll(PDO::FETCH_COLUMN));
$users = db()->query('SELECT username, role FROM users ORDER BY id')->fetchAll();

head('Admin');
if ($msg) echo '<p class="msg">' . h($msg) . '</p>';
if ($err) echo '<p class="msg err">' . h($err) . '</p>';
?>
<div class="card">
  <h2>Daftar siswa</h2>
  <form method="get">
    <label class="f">Kelas</label>
    <select name="kelas" onchange="this.form.submit()">
      <?php foreach ($kelas as $k) echo '<option' . ($k === $cur ? ' selected' : '') . '>' . h($k) . '</option>'; ?>
    </select>
  </form>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="aksi" value="siswa">
    <input type="hidden" name="kelas" value="<?= h($cur) ?>">
    <label class="f">Satu nama per baris (menyimpan akan menggantikan daftar kelas ini)</label>
    <textarea name="daftar"><?= h($daftar) ?></textarea>
    <button class="btn">Simpan daftar siswa</button>
  </form>
</div>
<div class="card">
  <h2>Unggah daftar siswa dari Excel</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="aksi" value="upload">
    <label class="f">File .xlsx atau .csv</label>
    <input type="file" name="berkas" accept=".xlsx,.csv" required>
    <p class="mut">Kolom A berisi <b>Kelas</b> (misalnya <?= h($kelas[0]) ?>) dan kolom B berisi <b>Nama</b>, satu siswa per baris. Baris judul boleh ada. Kelas yang ada di file akan <b>menggantikan</b> daftar kelas tersebut, kelas lain tidak berubah.</p>
    <p class="mut"><a href="admin.php?template=1">Unduh contoh template</a></p>
    <button class="btn">Unggah dan simpan</button>
  </form>
</div>
<div class="card">
  <h2>Isi per ikat</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="aksi" value="isi">
    <label class="f">Jumlah ompreng dalam 1 ikat</label>
    <input type="number" name="isi" min="1" value="<?= isi_ikat() ?>">
    <p class="mut">Contoh: 40 siswa masuk = <?= h(ambil_txt(40)) ?>.</p>
    <button class="btn">Simpan</button>
  </form>
</div>
<div class="card">
  <h2>Ganti password</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="aksi" value="pw">
    <label class="f">Akun</label>
    <select name="username">
      <?php foreach ($users as $u) echo '<option value="' . h($u['username']) . '">' . h($u['username']) . ' (' . h($u['role']) . ')</option>'; ?>
    </select>
    <label class="f">Password baru</label>
    <input type="password" name="password" autocomplete="new-password">
    <button class="btn">Ganti password</button>
  </form>
</div>
<?php foot();

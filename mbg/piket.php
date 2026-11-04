<?php
require 'lib.php';
require_role(['piket']);
$today = date('Y-m-d');
$kelas = kelas_list();
$cur = $_REQUEST['kelas'] ?? $kelas[0];
if (!in_array($cur, $kelas, true)) $cur = $kelas[0];

$st = db()->prepare('SELECT nama FROM siswa WHERE kelas = ? ORDER BY nama');
$st->execute([$cur]);
$siswa = $st->fetchAll(PDO::FETCH_COLUMN);
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $json = null;
    if ($siswa) {
        $idx = array_map('intval', $_POST['hadir'] ?? []);
        $names = [];
        foreach ($siswa as $i => $n) if (in_array($i, $idx, true)) $names[] = $n;
        $hadir = count($names);
        $total = count($siswa);
        $json = json_encode($names, JSON_UNESCAPED_UNICODE);
    } else {
        $hadir = max(0, (int)($_POST['hadir_manual'] ?? 0));
        $total = max(0, (int)($_POST['total_manual'] ?? 0));
        if ($total && $hadir > $total) $err = 'Siswa masuk tidak boleh melebihi total siswa.';
    }
    if (!$err) {
        $up = db()->prepare('INSERT INTO kehadiran (tanggal, kelas, hadir, total, hadir_nama, dibagikan)
            VALUES (?, ?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE hadir = VALUES(hadir), total = VALUES(total), hadir_nama = VALUES(hadir_nama), dibagikan = 0');
        $up->execute([$today, $cur, $hadir, $total, $json]);
        $msg = "Kelas $cur: $hadir siswa masuk terkirim. Ambil " . ambil_txt($hadir) . '.';
    }
}

$st = db()->prepare('SELECT * FROM kehadiran WHERE tanggal = ? AND kelas = ?');
$st->execute([$today, $cur]);
$rec = $st->fetch();
$present = ($rec && $rec['hadir_nama'] !== null) ? json_decode($rec['hadir_nama'], true) : null;

$all = db()->prepare('SELECT kelas FROM kehadiran WHERE tanggal = ?');
$all->execute([$today]);
$sudah = $all->fetchAll(PDO::FETCH_COLUMN);
$belum = array_values(array_diff($kelas, $sudah));

head('Guru Piket');
?>
<div class="card">
  <form method="get">
    <label class="f">Kelas</label>
    <select name="kelas" onchange="this.form.submit()">
      <?php foreach ($kelas as $k) echo '<option' . ($k === $cur ? ' selected' : '') . '>' . h($k) . '</option>'; ?>
    </select>
  </form>
  <?php if ($msg) echo '<p class="msg">' . h($msg) . '</p>'; ?>
  <?php if ($err) echo '<p class="msg err">' . h($err) . '</p>'; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="kelas" value="<?= h($cur) ?>">
    <?php if ($siswa): ?>
      <div class="cnt" id="cnt"></div>
      <div class="mut">Hilangkan centang untuk siswa yang tidak masuk. Kelas tanpa siswa absen tetap dikirim dengan semua tercentang.</div>
      <div id="list">
      <?php foreach ($siswa as $i => $n):
          $on = ($present === null) || in_array($n, $present, true); ?>
        <label class="cb"><input type="checkbox" name="hadir[]" value="<?= $i ?>" <?= $on ? 'checked' : '' ?>><span><?= h($n) ?></span></label>
      <?php endforeach; ?>
      </div>
    <?php else: ?>
      <label class="f">Siswa masuk hari ini</label>
      <input type="number" name="hadir_manual" min="0" value="<?= $rec ? (int)$rec['hadir'] : '' ?>">
      <label class="f">Total siswa kelas (opsional)</label>
      <input type="number" name="total_manual" min="0" value="<?= ($rec && $rec['total']) ? (int)$rec['total'] : '' ?>">
      <p class="mut">Daftar siswa kelas ini belum diisi oleh admin, jadi jumlah diisi manual.</p>
    <?php endif; ?>
    <button class="btn"><?= $rec ? 'Perbarui kehadiran' : 'Kirim ke guru pembagi' ?></button>
  </form>
</div>
<div class="card">
  <h2>Kelas belum lapor hari ini (<?= count($belum) ?>)</h2>
  <p class="mut"><?= $belum ? h(implode(', ', $belum)) : 'Semua kelas sudah lapor.' ?></p>
</div>
<script>
var isi = <?= isi_ikat() ?>;
function ambil(n){ if(n<=0) return '0'; var f=Math.floor(n/isi), l=n%isi; if(f&&l) return f+' ikat + '+l+' ompreng'; return f? f+' ikat' : l+' ompreng'; }
function upd(){
  var boxes=document.querySelectorAll('#list input'); if(!boxes.length) return;
  var c=document.querySelectorAll('#list input:checked').length;
  document.getElementById('cnt').textContent='Masuk: '+c+' dari '+boxes.length+' siswa. Ambil '+ambil(c);
}
document.querySelectorAll('#list input').forEach(function(b){ b.onchange=upd; });
upd();
</script>
<?php foot();

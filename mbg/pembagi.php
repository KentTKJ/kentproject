<?php
require 'lib.php';
require_role(['pembagi']);
$today = date('Y-m-d');
$kelas = kelas_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $k = $_POST['kelas'] ?? '';
    if (in_array($k, $kelas, true)) {
        $up = db()->prepare('UPDATE kehadiran SET dibagikan = 1 - dibagikan WHERE tanggal = ? AND kelas = ?');
        $up->execute([$today, $k]);
    }
    header('Location: pembagi.php');
    exit;
}

$st = db()->prepare('SELECT * FROM kehadiran WHERE tanggal = ?');
$st->execute([$today]);
$rows = [];
foreach ($st->fetchAll() as $r) $rows[$r['kelas']] = $r;
$hadir = 0; $lapor = 0; $bagi = 0;
foreach ($rows as $r) { $hadir += $r['hadir']; $lapor++; if ($r['dibagikan']) $bagi++; }
$p = isi_ikat();

head('Guru Pembagi');
?>
<meta http-equiv="refresh" content="30">
<div class="card hero">
  <span>Ikat ompreng yang diambil</span>
  <b><?= h(ambil_txt($hadir)) ?></b>
  <span>Total <?= $hadir ?> ompreng untuk <?= $hadir ?> siswa masuk. 1 ikat = <?= $p ?> ompreng.
  <?php if ($hadir % $p) echo ' Jika diambil ikat penuh saja: ' . ceil($hadir / $p) . ' ikat, kelebihan ' . ($p - $hadir % $p) . ' ompreng.'; ?>
  <?php if ($lapor < count($kelas)) echo ' Masih ' . (count($kelas) - $lapor) . ' kelas belum lapor, angka bisa bertambah.'; ?></span>
</div>
<div class="stats">
  <div class="stat"><b><?= $hadir ?></b><span>Siswa masuk</span></div>
  <div class="stat"><b><?= $lapor ?>/<?= count($kelas) ?></b><span>Kelas lapor</span></div>
  <div class="stat"><b><?= $bagi ?></b><span>Sudah dibagikan</span></div>
</div>
<div class="card wrap">
  <table>
    <thead><tr><th>Kelas</th><th>Masuk</th><th>Ambil</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php $last = '';
    foreach ($kelas as $k):
        $tk = preg_split('/[\s-]+/', $k)[0];
        if ($tk !== $last) { $last = $tk; echo '<tr class="g"><td colspan="5">Kelas ' . h($tk) . '</td></tr>'; }
        $r = $rows[$k] ?? null;
        if (!$r) { echo '<tr><td>' . h($k) . '</td><td>-</td><td>-</td><td><span class="tag w">Belum lapor</span></td><td></td></tr>'; continue; }
    ?>
      <tr>
        <td><?= h($k) ?></td>
        <td><?= (int)$r['hadir'] ?><?= $r['total'] ? ' / ' . (int)$r['total'] : '' ?></td>
        <td><?= h(ambil_txt((int)$r['hadir'])) ?></td>
        <td><?= $r['dibagikan'] ? '<span class="tag ok">Sudah dibagikan</span>' : '<span class="tag w">Menunggu</span>' ?></td>
        <td>
          <form method="post" style="margin:0">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="kelas" value="<?= h($k) ?>">
            <button class="btn sm <?= $r['dibagikan'] ? 'ghost' : '' ?>"><?= $r['dibagikan'] ? 'Batalkan' : 'Tandai dibagikan' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php foot();

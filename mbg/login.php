<?php
require 'lib.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([trim($_POST['username'] ?? '')]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['u'] = ['id' => $u['id'], 'username' => $u['username'], 'role' => $u['role']];
        header('Location: index.php');
        exit;
    }
    $err = 'Username atau password salah.';
}
head('Masuk');
?>
<div class="card">
  <h2>Masuk</h2>
  <?php if ($err) echo '<p class="msg err">' . h($err) . '</p>'; ?>
  <form method="post">
    <label class="f">Username</label><input type="text" name="username" autocomplete="username" required>
    <label class="f">Password</label><input type="password" name="password" autocomplete="current-password" required>
    <button class="btn">Masuk</button>
  </form>
</div>
<?php foot();

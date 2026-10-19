<?php
require_once 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = q("SELECT * FROM usuarios WHERE correo_usr=? AND est_usr='A'", [trim($_POST['correo'] ?? '')])->fetch();
    if ($u && password_verify($_POST['clave'] ?? '', $u['clave_usr'])) {
        session_regenerate_id(true);
        $_SESSION['id_usr'] = $u['id_usr']; $_SESSION['nom_usr'] = $u['nom_usr']; $_SESSION['rol_usr'] = $u['rol_usr'];
        $tieneEmp = q('SELECT 1 FROM empresa_usuarios WHERE id_usr=? LIMIT 1', [$u['id_usr']])->fetch();
        $volver = $_GET['volver'] ?? '';
        if ($volver !== '' && str_starts_with($volver, '/') && !str_starts_with($volver, '//')) $destino = $volver;
        elseif ($u['rol_usr'] === 'admin') $destino = 'admin_empresas.php';
        elseif ($tieneEmp)                 $destino = 'panel_empresa.php';
        else                               $destino = 'index.php';
        header('Location: ' . $destino); exit;
    }
    $err = 'Correo o contraseña incorrectos.';
}
$titulo = 'Iniciar sesión — Central Apícola del Usumacinta'; require 'includes/header.php';
?>
<div class="auth-wrap"><form class="auth-card" method="post">
  <h2>Iniciar sesión</h2><p class="sub">Accede a tu cuenta para ver tu carrito y realizar compras.</p>
  <div class="auth-field"><label>Correo electrónico</label><input type="email" name="correo" placeholder="tu@correo.com" required></div>
  <div class="auth-field"><label>Contraseña</label><input type="password" name="clave" placeholder="Tu contraseña" required></div>
  <div class="olv-link"><a href="recuperar.php">¿Olvidaste tu contraseña?</a></div>
  <button class="btn-auth">Entrar</button>
  <div class="auth-msg" style="color:#c0392b"><?= h($err) ?></div>
  <?php if (isset($_GET['ok'])): ?><div class="auth-msg" style="color:#1e8449">✓ Contraseña actualizada. Ya puedes iniciar sesión.</div><?php endif; ?>
  <div class="auth-switch">¿No tienes cuenta? <a href="registro.php">Crea una gratis</a></div>
</form></div>
<?php require 'includes/footer.php'; ?>

<?php
require_once 'config.php';
require_once 'includes/mexico.php';
requiere_login();
$u = (int)$_SESSION['id_usr'];

/* ---------- CONFIRMACIÓN ---------- */
if (isset($_GET['ok'])) {
    $ped = q('SELECT * FROM pedidos WHERE id_ped=? AND id_usr=?', [(int)$_GET['ok'], $u])->fetch();
    if (!$ped) { header('Location: mis_pedidos.php'); exit; }
    $pago = q('SELECT * FROM pagos WHERE id_ped=? ORDER BY id_pag DESC LIMIT 1', [$ped['id_ped']])->fetch();
    $titulo = 'Pedido confirmado — Central Apícola del Usumacinta'; require 'includes/header.php';
    ?>
    <div class="carrito-wrap" style="text-align:center"><div class="carrito-vacio"><div class="icon">✅</div>
      <h2 class="carrito-titulo">¡Gracias por tu compra!</h2>
      <p>Tu pedido <b>#<?= (int)$ped['id_ped'] ?></b> fue registrado por <b><?= dinero($ped['total_ped']) ?></b>.</p>
      <?php if ($pago && $pago['metodo_pag'] === 'transferencia'): ?>
        <p style="max-width:520px;margin:12px auto;background:#fff6e0;padding:14px;border-radius:6px">Realiza tu transferencia por <b><?= dinero($ped['total_ped']) ?></b> y escribe <b>Pedido #<?= (int)$ped['id_ped'] ?></b> como concepto.<br><small><?= h(DATOS_TRANSFERENCIA) ?></small></p>
      <?php elseif ($pago): ?><p>Pagarás en efectivo al recibir o recoger tu pedido.</p><?php endif; ?>
      <p style="font-size:13px;color:#666">Envío a: <?= h($ped['dir_envio_ped']) ?></p>
      <a href="mis_pedidos.php" class="btn-link" style="background:var(--naranja);color:#fff;padding:12px 28px;border-radius:8px;font-weight:700">Ver mis pedidos</a></div></div>
    <?php require 'includes/footer.php'; exit;
}

/* ---------- RESUMEN DEL CARRITO (solo lectura) ---------- */
$SQL_ITEMS = "SELECT c.id_ofe, c.cant_car, o.id_emp, o.stock_ofe, o.est_ofe, e.est_emp, e.nom_emp,
      COALESCE(o.precio_desc_ofe,o.precio_ofe) AS precio, p.nom_prod,
      (SELECT GROUP_CONCAT(av.valor_atv SEPARATOR ' / ') FROM variante_valores vv JOIN atributo_valores av ON av.id_atv=vv.id_atv WHERE vv.id_var=v.id_var) AS variante
    FROM carrito c JOIN ofertas o ON o.id_ofe=c.id_ofe JOIN producto_variantes v ON v.id_var=o.id_var
    JOIN productos p ON p.id_prod=v.id_prod JOIN empresas e ON e.id_emp=o.id_emp WHERE c.id_usr=?";

// Agrupa por empresa y calcula descuento, envío, comisión y total de cada una
function armar_pedido(array $items, ?array $cup, float $desc): array {
    $emps = [];
    foreach ($items as $i) {
        $e = &$emps[$i['id_emp']];
        $e['nom'] = $i['nom_emp']; $e['items'][] = $i;
        $e['sub'] = ($e['sub'] ?? 0) + $i['precio'] * $i['cant_car']; $e['desc'] = 0; unset($e);
    }
    if ($cup && $desc > 0) {
        if ($cup['id_emp'] !== null) $emps[$cup['id_emp']]['desc'] = $desc;
        else {                                               // cupón de plataforma: se reparte proporcional
            $tot = array_sum(array_column($emps, 'sub')); $acc = 0; $ids = array_keys($emps); $ult = end($ids);
            foreach ($emps as $id => &$e) { $e['desc'] = $id === $ult ? round($desc - $acc, 2) : round($desc * $e['sub'] / $tot, 2); $acc += $e['desc']; }
            unset($e);
        }
    }
    foreach ($emps as &$e) {
        $e['neto'] = $e['sub'] - $e['desc']; $e['envio'] = envio_empresa($e['sub']);
        $e['total'] = $e['neto'] + $e['envio'];
    }
    return $emps;
}

$err = '';
$dirs = q("SELECT * FROM direcciones WHERE id_usr=? AND est_dir='A' ORDER BY principal_dir DESC, id_dir", [$u])->fetchAll();

/* ---------- PROCESAR PEDIDO ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $metodo = $_POST['metodo'] ?? '';
        if (!in_array($metodo, ['transferencia', 'efectivo'], true)) throw new RuntimeException('Elige un método de pago.');
        $idDir = (int)($_POST['id_dir'] ?? 0); $nueva = null;
        if ($idDir > 0) {
            $d = q("SELECT * FROM direcciones WHERE id_dir=? AND id_usr=? AND est_dir='A'", [$idDir, $u])->fetch();
            if (!$d) throw new RuntimeException('Dirección no válida.');
        } else {
            $d = ['nom_dest_dir' => trim($_POST['nom'] ?? ''), 'tel_dir' => preg_replace('/\D/', '', $_POST['tel'] ?? ''), 'calle_dir' => trim($_POST['calle'] ?? ''),
                  'ciudad_dir' => mb_substr(trim($_POST['ciudad'] ?? ''), 0, 60), 'estado_dir' => trim($_POST['estado'] ?? ''), 'cp_dir' => preg_replace('/\D/', '', $_POST['cp'] ?? ''), 'alias_dir' => trim($_POST['alias'] ?? '') ?: null];
            $colonia = trim($_POST['colonia'] ?? '');
            if ($d['nom_dest_dir'] === '' || $d['calle_dir'] === '' || $d['ciudad_dir'] === '') throw new RuntimeException('Completa todos los datos de la dirección.');
            if (strlen($d['cp_dir']) !== 5) throw new RuntimeException('El código postal debe tener 5 dígitos.');
            if (strlen($d['tel_dir']) !== 10) throw new RuntimeException('El teléfono debe tener 10 dígitos.');
            if (!in_array($d['estado_dir'], ESTADOS_MX, true)) throw new RuntimeException('Elige tu estado de la lista.');
            $info = cp_buscar($d['cp_dir']);                           // si el CP está en el catálogo, el estado debe coincidir
            if ($info && $info['estado'] !== $d['estado_dir']) throw new RuntimeException('El código postal ' . $d['cp_dir'] . ' pertenece a ' . $info['estado'] . ', no a ' . $d['estado_dir'] . '.');
            if ($colonia === '') throw new RuntimeException('Escribe o elige tu colonia.');
            $d['calle_dir'] .= ', Col. ' . $colonia;
            if (mb_strlen($d['calle_dir']) > 150) throw new RuntimeException('La calle y la colonia son demasiado largas (máx. 150 caracteres en total).');
            $nueva = $d;
        }
        $pdo->beginTransaction();
        if (!empty($_SESSION['cupon'])) q('SELECT id_cup FROM cupones WHERE cod_cup=? FOR UPDATE', [$_SESSION['cupon']]);   // evita doble uso simultáneo
        $items = q($SQL_ITEMS . ' FOR UPDATE OF o', [$u])->fetchAll();
        if (!$items) throw new RuntimeException('Tu carrito está vacío.');
        foreach ($items as $i) {
            if ($i['est_ofe'] !== 'A' || $i['est_emp'] !== 'A') throw new RuntimeException('«' . $i['nom_prod'] . '» ya no está disponible. Quítalo del carrito.');
            if ($i['stock_ofe'] < $i['cant_car']) throw new RuntimeException('«' . $i['nom_prod'] . '» solo tiene ' . $i['stock_ofe'] . ' en stock. Ajusta la cantidad en tu carrito.');
        }
        $cup = null; $desc = 0;
        if (!empty($_SESSION['cupon'])) { [$cup, $desc, $m] = cupon_evaluar($_SESSION['cupon'], $u, $items); if (!$cup) throw new RuntimeException($m); }
        $emps = armar_pedido($items, $cup, $desc);

        if ($nueva) {
            q("INSERT INTO direcciones (id_usr,alias_dir,nom_dest_dir,tel_dir,calle_dir,ciudad_dir,estado_dir,cp_dir,principal_dir) VALUES (?,?,?,?,?,?,?,?,?)",
              [$u, $nueva['alias_dir'], $nueva['nom_dest_dir'], $nueva['tel_dir'], $nueva['calle_dir'], $nueva['ciudad_dir'], $nueva['estado_dir'], $nueva['cp_dir'], $dirs ? 'N' : 'S']);
            $idDir = (int)$pdo->lastInsertId();
        }
        $dirTxt = $d['nom_dest_dir'] . ' · ' . $d['calle_dir'] . ', ' . $d['ciudad_dir'] . ', ' . $d['estado_dir'] . ' CP ' . $d['cp_dir'] . ($d['tel_dir'] ? ' · Tel ' . $d['tel_dir'] : '');
        $sub = array_sum(array_column($emps, 'sub')); $envio = array_sum(array_column($emps, 'envio')); $total = array_sum(array_column($emps, 'total'));
        $est = $total <= 0 ? 'pagado' : 'pendiente';
        q('INSERT INTO pedidos (id_usr,id_dir,dir_envio_ped,subtotal_ped,desc_ped,envio_ped,total_ped,estado_ped) VALUES (?,?,?,?,?,?,?,?)',
          [$u, $idDir, mb_substr($dirTxt, 0, 400), $sub, $desc, $envio, $total, $est]);
        $idPed = (int)$pdo->lastInsertId();

        foreach ($emps as $idEmp => $e) {
            q('INSERT INTO pedidos_empresa (id_ped,id_emp,subtotal_pem,envio_pem,total_pem,estado_pem) VALUES (?,?,?,?,?,?)',
              [$idPed, $idEmp, $e['sub'], $e['envio'], $e['total'], $est]);
            $idPem = (int)$pdo->lastInsertId();
            foreach ($e['items'] as $i) {
                $nom = mb_substr($i['nom_prod'] . ($i['variante'] ? ' - ' . $i['variante'] : ''), 0, 200);
                q('INSERT INTO pedido_items (id_pem,id_ofe,nom_pei,cant_pei,precio_pei) VALUES (?,?,?,?,?)', [$idPem, $i['id_ofe'], $nom, $i['cant_car'], $i['precio']]);
                q('UPDATE ofertas SET stock_ofe = stock_ofe - ? WHERE id_ofe=?', [$i['cant_car'], $i['id_ofe']]);
                q("INSERT INTO inventario_movimientos (id_ofe,id_usr,tipo_mov,cant_mov,stock_res_mov,nota_mov) VALUES (?,?, 'venta',?,?,?)",
                  [$i['id_ofe'], $u, -$i['cant_car'], $i['stock_ofe'] - $i['cant_car'], 'Pedido #' . $idPed]);
            }
        }
        if ($cup) {
            q('INSERT INTO cupones_usos (id_cup,id_usr,id_ped,monto_desc_cpu) VALUES (?,?,?,?)', [$cup['id_cup'], $u, $idPed, $desc]);
            q('UPDATE cupones SET usos_act_cup = usos_act_cup + 1 WHERE id_cup=?', [$cup['id_cup']]);
        }
        if ($total > 0) q('INSERT INTO pagos (id_ped,metodo_pag,monto_pag) VALUES (?,?,?)', [$idPed, $metodo, $total]);
        q('DELETE FROM carrito WHERE id_usr=?', [$u]);
        $pdo->commit(); unset($_SESSION['cupon']);
        header('Location: checkout.php?ok=' . $idPed); exit;
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($x->getMessage()); $err = 'No se pudo procesar tu pedido. Intenta de nuevo.'; }
}

/* ---------- PANTALLA ---------- */
$items = q($SQL_ITEMS, [$u])->fetchAll();
if (!$items) { header('Location: carrito.php'); exit; }
$cup = null; $desc = 0; $msgCup = '';
if (!empty($_SESSION['cupon'])) [$cup, $desc, $msgCup] = cupon_evaluar($_SESSION['cupon'], $u, $items);
$emps = armar_pedido($items, $cup, $desc);
$total = array_sum(array_column($emps, 'total'));
$titulo = 'Finalizar compra — Central Apícola del Usumacinta'; require 'includes/header.php';
?>
<div class="carrito-wrap"><h2 class="carrito-titulo">Finalizar compra</h2>
<?php if ($err): ?><p class="error"><?= h($err) ?></p><?php endif; ?>
<?php if ($msgCup): ?><p class="error"><?= h($msgCup) ?> <a href="carrito.php">Revisar cupón</a></p><?php endif; ?>
<form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:28px;align-items:start"><?= csrf() ?>
  <div>
    <h3 style="margin-bottom:12px">1. Dirección de envío</h3>
    <?php if ($dirs): ?><div class="auth-field"><label>Elige una dirección</label>
      <select name="id_dir" id="selDir" style="width:100%;padding:10px;border:1px solid var(--borde)">
        <?php foreach ($dirs as $d) echo '<option value="' . $d['id_dir'] . '">' . h(($d['alias_dir'] ? $d['alias_dir'] . ' — ' : '') . $d['calle_dir'] . ', ' . $d['ciudad_dir'] . ' CP ' . $d['cp_dir']) . '</option>'; ?>
        <option value="0">+ Usar una dirección nueva</option></select></div>
    <?php else: ?><input type="hidden" name="id_dir" value="0"><?php endif; ?>
    <div id="nuevaDir" style="display:<?= $dirs ? 'none' : 'block' ?>">
      <div class="auth-field"><label>Nombre de quien recibe</label><input name="nom" value="<?= h($_POST['nom'] ?? $_SESSION['nom_usr']) ?>"></div>
      <div class="auth-field"><label>Teléfono (10 dígitos)</label><input name="tel" maxlength="10" inputmode="numeric" value="<?= h($_POST['tel'] ?? '') ?>"></div>
      <div class="auth-field"><label>Código postal</label><input name="cp" id="cp" maxlength="5" inputmode="numeric" autocomplete="postal-code" value="<?= h($_POST['cp'] ?? '') ?>">
        <small id="cpMsg" style="color:#7a7a6a;display:block;min-height:16px"></small></div>
      <div class="auth-field"><label>Estado</label><select name="estado" id="estado" style="width:100%;padding:10px;border:1px solid var(--borde)">
        <option value="">— Elige tu estado —</option>
        <?php foreach (ESTADOS_MX as $est) echo '<option' . (($_POST['estado'] ?? '') === $est ? ' selected' : '') . '>' . h($est) . '</option>'; ?></select></div>
      <div class="auth-field"><label>Ciudad o municipio</label><input name="ciudad" id="ciudad" maxlength="60" value="<?= h($_POST['ciudad'] ?? '') ?>"></div>
      <div class="auth-field"><label>Colonia</label><input name="colonia" id="colonia" maxlength="60" list="colonias" autocomplete="off" placeholder="Elige de la lista o escríbela" value="<?= h($_POST['colonia'] ?? '') ?>"><datalist id="colonias"></datalist></div>
      <div class="auth-field"><label>Calle y número</label><input name="calle" value="<?= h($_POST['calle'] ?? '') ?>"></div>
      <div class="auth-field"><label>Alias (opcional: Casa, Oficina)</label><input name="alias" maxlength="30"></div>
    </div>
    <h3 style="margin:22px 0 12px">2. Método de pago</h3>
    <label style="display:block;margin-bottom:8px"><input type="radio" name="metodo" value="transferencia" checked> Transferencia o depósito</label>
    <label style="display:block"><input type="radio" name="metodo" value="efectivo"> Efectivo al recibir / recoger</label>
  </div>
  <div class="resumen-card">
    <h3 style="margin-bottom:12px">Resumen</h3>
    <?php foreach ($emps as $e): ?>
      <p style="font-weight:700;margin-top:10px">🏪 <?= h($e['nom']) ?></p>
      <?php foreach ($e['items'] as $i): ?><div class="resumen-row"><span><?= (int)$i['cant_car'] ?> × <?= h($i['nom_prod'] . ($i['variante'] ? ' (' . $i['variante'] . ')' : '')) ?></span><span><?= dinero($i['precio'] * $i['cant_car']) ?></span></div><?php endforeach; ?>
      <?php if ($e['desc'] > 0): ?><div class="resumen-row" style="color:#1e8449"><span>Descuento</span><span>-<?= dinero($e['desc']) ?></span></div><?php endif; ?>
      <div class="resumen-row"><span>Envío</span><span><?= $e['envio'] > 0 ? dinero($e['envio']) : 'Gratis' ?></span></div>
    <?php endforeach; ?>
    <?php if ($cup): ?><p style="font-size:12px;color:#1e8449;margin-top:8px">✓ Cupón <?= h($cup['cod_cup']) ?> aplicado</p><?php endif; ?>
    <div class="resumen-row resumen-total"><span>Total</span><span><?= dinero($total) ?></span></div>
    <button class="btn-auth" style="margin-top:14px">Confirmar pedido</button>
    <p style="font-size:12px;color:#7a7a6a;margin-top:8px">Envío: <?= dinero(ENVIO_POR_EMPRESA) ?> por empresa, gratis desde <?= dinero(ENVIO_GRATIS_DESDE) ?> de compra en esa empresa.</p>
  </div>
</form></div>
<script>const sd=document.getElementById('selDir'),nd=document.getElementById('nuevaDir');
if(sd)sd.onchange=()=>nd.style.display=sd.value==='0'?'block':'none';

// Código postal -> estado, ciudad y colonias (datos locales, sin servicios externos)
(function () {
  const cp = document.getElementById('cp'), est = document.getElementById('estado'), ciu = document.getElementById('ciudad'),
        col = document.getElementById('colonia'), dl = document.getElementById('colonias'), msg = document.getElementById('cpMsg');
  if (!cp) return;
  let ultimo = '';
  function buscar(soloColonias) {
    cp.value = cp.value.replace(/\D/g, '').slice(0, 5);
    if (cp.value.length < 5) { ultimo = ''; msg.textContent = ''; return; }
    if (cp.value === ultimo) return;
    const pedido = ultimo = cp.value;
    fetch('cp.php?cp=' + pedido).then(r => r.json()).then(d => {
      if (pedido !== cp.value) return;                       // llegó una respuesta vieja
      dl.innerHTML = '';
      if (!d.ok) { msg.textContent = 'No encontramos ese código postal: elige tu estado y escribe tu ciudad y colonia.'; return; }
      d.colonias.forEach(c => { const o = document.createElement('option'); o.value = c; dl.appendChild(o); });
      const ciudad = d.ciudad || d.municipio;
      if (!soloColonias) {
        est.value = d.estado; ciu.value = ciudad;
        col.value = d.colonias.length === 1 ? d.colonias[0] : '';
      }
      msg.textContent = '📍 ' + ciudad + ', ' + d.estado + (d.colonias.length > 1 ? ' · elige tu colonia de la lista' : '');
    }).catch(() => { msg.textContent = ''; });
  }
  cp.addEventListener('input', () => buscar(false));
  if (cp.value.length === 5) buscar(true);                   // si la página se recarga con un error, solo vuelve a cargar las colonias
})();
</script>
<?php require 'includes/footer.php'; ?>

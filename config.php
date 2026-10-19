<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';          // define $pdo, h() y dinero()

// Alias para que las páginas anteriores sigan funcionando
function e($s)     { return h($s); }
function money($n) { return dinero($n); }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s; }

function requiere_login() {
    if (empty($_SESSION['id_usr'])) { header('Location: login.php?volver=' . urlencode($_SERVER['REQUEST_URI'])); exit; }
}
function es_admin(): bool { return ($_SESSION['rol_usr'] ?? '') === 'admin'; }

// Protección CSRF para formularios POST
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function slugify(string $t): string {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-');
}
function get_or_create(string $sel, array $sp, string $ins, array $ip): int {
    global $pdo;
    $id = q($sel, $sp)->fetchColumn();
    if ($id) return (int)$id;
    q($ins, $ip);
    return (int)$pdo->lastInsertId();
}

function tarjeta($r) {
    $img = $r['img'] ?: 'imagen/logo_centro.png';
    return '<div class="prod-card"><a href="producto.php?slug=' . h($r['slug_prod']) . '" style="text-decoration:none;color:inherit;display:block">'
        . '<div class="prod-img-wrap"><img src="' . h($img) . '" alt="' . h($r['nom_prod']) . '" onerror="this.style.background=\'#f5f0e8\'"></div>'
        . '<div class="prod-info"><div class="prod-name">' . h($r['nom_prod']) . '</div>'
        . '<div class="prod-price">' . ($r['num_vendedores'] > 1 ? 'Desde ' : '') . dinero($r['precio_desde']) . '</div></div></a>'
        . '<form method="post" action="carrito.php" style="padding:0 16px 16px">' . csrf()
        . '<input type="hidden" name="accion" value="agregar"><input type="hidden" name="id_ofe" value="' . (int)$r['id_ofe_min'] . '">'
        . '<input type="hidden" name="cant" value="1"><button class="btn-add">Agregar al carrito</button></form></div>';
}
const SQL_TARJETAS = "SELECT d.*, p.slug_prod, p.id_cat,
    (SELECT url_img FROM producto_imagenes i WHERE i.id_prod = d.id_prod ORDER BY orden_img LIMIT 1) AS img,
    (SELECT o.id_ofe FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var AND v.est_var='A'
       JOIN empresas e ON e.id_emp=o.id_emp AND e.est_emp='A'
      WHERE v.id_prod=d.id_prod AND o.est_ofe='A' AND o.stock_ofe>0
      ORDER BY COALESCE(o.precio_desc_ofe,o.precio_ofe) LIMIT 1) AS id_ofe_min
    FROM v_producto_desde d JOIN productos p ON p.id_prod = d.id_prod";

// ===== CORREO (recuperar contraseña) =====
// En WampServer mail() normalmente NO envía. Con MODO_DEV = true el código se muestra en pantalla
// para que puedas probar. En producción pon false y configura SMTP (o PHPMailer).
const MODO_DEV = true;
function enviar_correo(string $para, string $asunto, string $html): bool {
    $cab = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Central Apicola <no-responder@localhost>\r\n";
    return @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $html, $cab);
}

// ===== CUPONES =====
// $items = filas del carrito con 'id_emp', 'precio' y 'cant_car'. Devuelve [cupon|null, descuento, mensaje_error]
function cupon_evaluar(string $codigo, int $idUsr, array $items): array {
    $c = q("SELECT c.*, e.nom_emp FROM cupones c LEFT JOIN empresas e ON e.id_emp=c.id_emp
            WHERE c.cod_cup=? AND c.est_cup='A'", [strtoupper(trim($codigo))])->fetch();
    if (!$c) return [null, 0, 'Cupón no válido.'];
    $correo = strtolower((string)q('SELECT correo_usr FROM usuarios WHERE id_usr=?', [$idUsr])->fetchColumn());
    if ($c['correo_dest_cup'] && strtolower($c['correo_dest_cup']) !== $correo) return [null, 0, 'Este cupón es exclusivo para otro usuario.'];
    $ahora = q('SELECT NOW()')->fetchColumn();
    if ($ahora < $c['fecha_ini_cup']) return [null, 0, 'Este cupón aún no está disponible.'];
    if ($ahora > $c['fecha_fin_cup']) return [null, 0, 'Este cupón ya expiró.'];
    if ($c['usos_max_cup'] !== null && $c['usos_act_cup'] >= $c['usos_max_cup']) return [null, 0, 'Este cupón ya alcanzó su límite de usos.'];
    if (q('SELECT 1 FROM cupones_usos WHERE id_cup=? AND id_usr=?', [$c['id_cup'], $idUsr])->fetch()) return [null, 0, 'Ya usaste este cupón anteriormente.'];
    $base = 0;                                   // cupón de empresa: solo descuenta sobre sus productos
    foreach ($items as $i) if ($c['id_emp'] === null || (int)$i['id_emp'] === (int)$c['id_emp']) $base += $i['precio'] * $i['cant_car'];
    if ($base <= 0) return [null, 0, 'Este cupón aplica solo a productos de ' . $c['nom_emp'] . '.'];
    if ($base < $c['monto_min_cup']) return [null, 0, 'Este cupón requiere una compra mínima de ' . dinero($c['monto_min_cup']) . '.'];
    $d = $c['tipo_desc_cup'] === 'porcentaje' ? $base * $c['valor_cup'] / 100 : (float)$c['valor_cup'];
    return [$c, round(min($d, $base), 2), ''];
}

// ===== CHECKOUT (ajusta estos valores a tu negocio) =====
const ENVIO_POR_EMPRESA   = 80.00;    // costo de envío por cada empresa en el pedido
const ENVIO_GRATIS_DESDE  = 800.00;   // envío gratis si el subtotal de esa empresa llega a esta cantidad
const DATOS_TRANSFERENCIA = 'Banco: ________ · CLABE: ________________ · Titular: ________________';
function envio_empresa(float $subtotal): float { return $subtotal >= ENVIO_GRATIS_DESDE ? 0.0 : ENVIO_POR_EMPRESA; }

// ===== GESTIÓN DE PEDIDOS (llamar dentro de una transacción) =====
function cancelar_pem(int $idPem): void {
    $pem = q('SELECT * FROM pedidos_empresa WHERE id_pem=? FOR UPDATE', [$idPem])->fetch();
    if (!$pem || in_array($pem['estado_pem'], ['cancelado', 'enviado', 'entregado'], true)) throw new RuntimeException('Este pedido ya no se puede cancelar.');
    foreach (q('SELECT id_ofe, cant_pei FROM pedido_items WHERE id_pem=?', [$idPem])->fetchAll() as $it) {   // regresa el stock
        q('UPDATE ofertas SET stock_ofe = stock_ofe + ? WHERE id_ofe=?', [$it['cant_pei'], $it['id_ofe']]);
        $st = (int)q('SELECT stock_ofe FROM ofertas WHERE id_ofe=?', [$it['id_ofe']])->fetchColumn();
        q("INSERT INTO inventario_movimientos (id_ofe,id_usr,tipo_mov,cant_mov,stock_res_mov,nota_mov) VALUES (?,?, 'devolucion',?,?,?)",
          [$it['id_ofe'], $_SESSION['id_usr'] ?? null, $it['cant_pei'], $st, 'Cancelación pedido #' . $pem['id_ped']]);
    }
    q("UPDATE pedidos_empresa SET estado_pem='cancelado' WHERE id_pem=?", [$idPem]);
    sincronizar_pedido((int)$pem['id_ped']);
}
function sincronizar_pedido(int $idPed): void {
    $est = q('SELECT estado_pem FROM pedidos_empresa WHERE id_ped=?', [$idPed])->fetchAll(PDO::FETCH_COLUMN);
    if (!$est) return;
    if (!array_diff($est, ['cancelado'])) {                              // todo cancelado: libera cupón y pago pendiente
        q("UPDATE pedidos SET estado_ped='cancelado' WHERE id_ped=?", [$idPed]);
        foreach (q('SELECT id_cup FROM cupones_usos WHERE id_ped=?', [$idPed])->fetchAll(PDO::FETCH_COLUMN) as $idCup)
            q('UPDATE cupones SET usos_act_cup = GREATEST(usos_act_cup - 1, 0) WHERE id_cup=?', [$idCup]);
        q('DELETE FROM cupones_usos WHERE id_ped=?', [$idPed]);
        q("UPDATE pagos SET estado_pag='rechazado' WHERE id_ped=? AND estado_pag='pendiente'", [$idPed]);
    } elseif (!array_diff($est, ['entregado', 'cancelado'])) {
        q("UPDATE pedidos SET estado_ped='completado' WHERE id_ped=?", [$idPed]);
    }
}

function etq_estado(string $e): string {
    return ['pendiente' => '⏳ Pendiente', 'pagado' => '💳 Pagado', 'preparando' => '📦 Preparando', 'enviado' => '🚚 Enviado',
            'entregado' => '✅ Entregado', 'completado' => '✅ Completado', 'cancelado' => '⛔ Cancelado',
            'aprobado' => '✅ Aprobado', 'rechazado' => '⛔ Rechazado'][$e] ?? $e;
}

const WHATSAPP_ADMIN = '529211234567';   // número del administrador (con lada 52), para que las empresas pidan categorías u opciones nuevas

// ===== FOTOS POR VARIANTE (ej. fotos del color Azul vs. del color Rosa) =====
// Guarda las fotos de una variante (producto_imagenes.id_var). El nombre del archivo lleva e{id_empresa}_ para saber quién la subió.
function guardar_fotos(int $idProd, int $idVar, int $idEmp, array $f, int $max = 5): int {
    if (empty($f['name'])) return 0;
    $dir = __DIR__ . '/imagen/productos/'; if (!is_dir($dir)) mkdir($dir, 0775, true);
    $actual = (int)q('SELECT COUNT(*) FROM producto_imagenes WHERE id_prod=? AND id_var=?', [$idProd, $idVar])->fetchColumn();
    $orden  = (int)q('SELECT COALESCE(MAX(orden_img)+1,0) FROM producto_imagenes WHERE id_prod=? AND id_var=?', [$idProd, $idVar])->fetchColumn();
    $n = 0;
    foreach ((array)$f['name'] as $i => $nombre) {
        $tmp = ((array)$f['tmp_name'])[$i]; $err = ((array)$f['error'])[$i]; $size = ((array)$f['size'])[$i];
        if ($err === UPLOAD_ERR_NO_FILE || $tmp === '') continue;
        if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir una de las imágenes (revisa que pese menos de 2 MB).');
        if ($actual + $n >= $max) throw new RuntimeException("Máximo $max fotos por variante.");
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || $size > 2 * 1024 * 1024 || !@getimagesize($tmp)) throw new RuntimeException('Imagen inválida (jpg, png o webp, máx. 2 MB).');
        $arch = 'e' . $idEmp . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . $arch)) throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
        q('INSERT INTO producto_imagenes (id_prod,id_var,url_img,orden_img) VALUES (?,?,?,?)', [$idProd, $idVar, 'imagen/productos/' . $arch, $orden + $n]);
        $n++;
    }
    return $n;
}

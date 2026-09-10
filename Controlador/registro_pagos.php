<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireLogin();

$es_gestor = tieneAccesoTotal();
$cuota_preseleccionada = (int)($_GET['cuota_id'] ?? 0);
$aviso_unidad_id = (int)($_GET['aviso_unidad_id'] ?? 0);
$usuario = $_SESSION['usuario'] ?? '';
$nombre_usuario = $_SESSION['nombre'] ?? $usuario;
$tasa_bs = obtenerTasaBCV();

// Si viene aviso_unidad_id (desde el listado de avisos), seleccionar la primera
// cuota abierta de esa unidad (solo si es unidad permitida para el usuario)
if ($aviso_unidad_id > 0) {
    if ($es_gestor) {
        $stmt = $connect->prepare(
            "SELECT id FROM cuotas_emitidas
             WHERE unidad_id = ? AND estado IN ('pendiente','vencida','parcial')
               AND monto - monto_pagado > 0.001
             ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1"
        );
        $stmt->bind_param("i", $aviso_unidad_id);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fila) $cuota_preseleccionada = (int)$fila['id'];
    } else {
        $ids = getUnidadesPermitidas($connect);
        if (in_array($aviso_unidad_id, array_map('intval', $ids ?: []))) {
            $stmt = $connect->prepare(
                "SELECT id FROM cuotas_emitidas
                 WHERE unidad_id = ? AND estado IN ('pendiente','vencida','parcial')
                   AND monto - monto_pagado > 0.001
                 ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1"
            );
            $stmt->bind_param("i", $aviso_unidad_id);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($fila) $cuota_preseleccionada = (int)$fila['id'];
        }
    }
}

// ------------------------------------------------------------------
// Cargar cuotas según permisos
// ------------------------------------------------------------------
$cuotas = [];
if ($es_gestor) {
    $cuotas = $connect->query(
        "SELECT c.id, c.periodo_mes, c.periodo_anio, c.monto, c.monto_pagado,
                (c.monto - c.monto_pagado) AS saldo, u.torre, u.numero, co.nombre AS concepto
         FROM cuotas_emitidas c
         JOIN unidades u ON c.unidad_id = u.id
         JOIN conceptos_cobro co ON c.concepto_id = co.id
         WHERE c.estado IN ('pendiente', 'vencida', 'parcial')
           AND c.monto - c.monto_pagado > 0.001
         ORDER BY c.periodo_anio DESC, c.periodo_mes DESC, u.torre, u.numero"
    )->fetch_all(MYSQLI_ASSOC);
} else {
    $ids = getUnidadesPermitidas($connect);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $connect->prepare(
            "SELECT c.id, c.periodo_mes, c.periodo_anio, c.monto, c.monto_pagado,
                    (c.monto - c.monto_pagado) AS saldo, u.torre, u.numero, co.nombre AS concepto
             FROM cuotas_emitidas c
             JOIN unidades u ON c.unidad_id = u.id
             JOIN conceptos_cobro co ON c.concepto_id = co.id
             WHERE c.unidad_id IN ($placeholders)
               AND c.estado IN ('pendiente', 'vencida', 'parcial')
               AND c.monto - c.monto_pagado > 0.001
             ORDER BY c.periodo_anio DESC, c.periodo_mes DESC, u.torre, u.numero"
        );
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $cuotas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// ------------------------------------------------------------------
// REGISTRAR PAGO DIRECTO (gestor) o DECLARAR PAGO CON COMPROBANTE (consumidor)
// ------------------------------------------------------------------
if (isset($_POST['pagar-btn'])) {
    $cuota_id = (int)($_POST['cuota_id'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);
    $metodo_pago = trim($_POST['metodo_pago'] ?? 'efectivo');
    $referencia = trim($_POST['referencia'] ?? '');
    $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
    $nota = trim($_POST['nota'] ?? '');

    if (!isset(METODOS_PAGO[$metodo_pago])) $metodo_pago = 'efectivo';

    try {
        if ($cuota_id <= 0 || $monto <= 0.001) {
            throw new Exception("Debe seleccionar un aviso y un monto mayor a cero");
        }

        $stmt = $connect->prepare("SELECT id, monto, monto_pagado, unidad_id FROM cuotas_emitidas WHERE id = ?");
        $stmt->bind_param("i", $cuota_id);
        $stmt->execute();
        $cu = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cu) throw new Exception("El aviso seleccionado no existe");

        // Verificar permisos del consumidor
        if (!$es_gestor) {
            $ids = getUnidadesPermitidas($connect);
            if (!in_array((int)$cu['unidad_id'], array_map('intval', $ids ?: []))) {
                throw new Exception("No tiene permiso para pagar esta cuota");
            }
        }

        $saldo = (float)$cu['monto'] - (float)$cu['monto_pagado'];
        if ($monto > $saldo + 0.001) {
            throw new Exception("El monto excede el saldo pendiente ($" . number_format($saldo, 2) . ")");
        }

        if ($es_gestor) {
            // Registro directo del pago (flujo actual del admin)
            $connect->begin_transaction();

            $insert = $connect->prepare(
                "INSERT INTO pagos (cuota_id, monto, metodo_pago, referencia, fecha_pago, registrado_por, nota, tasa_bs, monto_bs)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $monto_bs_pago = convertirABolivares($monto, $tasa_bs);
            $insert->bind_param("idsssssss", $cuota_id, $monto, $metodo_pago, $referencia, $fecha_pago, $usuario, $nota, $tasa_bs, $monto_bs_pago);
            if (!$insert->execute()) throw new Exception("Error al registrar el pago: " . $insert->error);
            $insert->close();

            $nuevo_pagado = (float)$cu['monto_pagado'] + $monto;
            $nuevo_estado = ($nuevo_pagado >= (float)$cu['monto'] - 0.001) ? 'pagada' : 'parcial';
            $upd = $connect->prepare("UPDATE cuotas_emitidas SET monto_pagado = ?, estado = ? WHERE id = ?");
            $upd->bind_param("dsi", $nuevo_pagado, $nuevo_estado, $cuota_id);
            if (!$upd->execute()) throw new Exception("Error al actualizar el aviso: " . $upd->error);
            $upd->close();

            $connect->commit();
            $_SESSION['tipo_mensaje'] = 'success';
            $msg_bs = $monto_bs_pago !== null ? " (Bs " . number_format($monto_bs_pago, 2, ',', '.') . ")" : "";
            $_SESSION['mensaje'] = "✅ Pago de $" . number_format($monto, 2) . $msg_bs . " registrado correctamente";
            header("Location: ../Vista/consulta_pagos.php");
            exit();

        } else {
            // Declaración de pago con comprobante (consumidor)
            // Validar comprobante (obligatorio)
            $comprobante = null;
            if (!empty($_FILES['comprobante_foto']['tmp_name']) && $_FILES['comprobante_foto']['error'] === UPLOAD_ERR_OK) {
                $comprobante = $_FILES['comprobante_foto'];
            } elseif (!empty($_FILES['comprobante_archivo']['tmp_name']) && $_FILES['comprobante_archivo']['error'] === UPLOAD_ERR_OK) {
                $comprobante = $_FILES['comprobante_archivo'];
            }

            if (!$comprobante) {
                throw new Exception("Debe adjuntar un comprobante (foto o archivo). Si usa la cámara, permita el acceso a la misma.");
            }

            // Validar extensión
            $ext = strtolower(pathinfo($comprobante['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, COMPROBANTE_EXT)) {
                throw new Exception("Tipo de archivo no permitido. Formatos válidos: " . implode(', ', COMPROBANTE_EXT));
            }

            // Validar tamaño
            if ($comprobante['size'] > COMPROBANTE_MAX_BYTES) {
                throw new Exception("El archivo supera el tamaño máximo permitido (" . (COMPROBANTE_MAX_BYTES / 1024 / 1024) . " MB)");
            }

            // Validar mime real
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $comprobante['tmp_name']);
            finfo_close($finfo);
            $mime_permitidos = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($mime, $mime_permitidos)) {
                throw new Exception("Contenido del archivo no válido (tipo detectado: $mime)");
            }

            // Guardar archivo
            $upload_dir = __DIR__ . '/../uploads/comprobantes/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Error al crear la carpeta de comprobantes");
                }
            }
            $nombre_archivo = bin2hex(random_bytes(16)) . '.' . $ext;
            $ruta_guardada = $upload_dir . $nombre_archivo;
            if (!move_uploaded_file($comprobante['tmp_name'], $ruta_guardada)) {
                throw new Exception("Error al guardar el comprobante");
            }

            // Insertar comprobante pendiente
            $monto_bs_comp = convertirABolivares($monto, $tasa_bs);
            $stmt = $connect->prepare(
                "INSERT INTO comprobantes_pago
                   (cuota_id, monto, metodo_pago, referencia, fecha_pago,
                    archivo, archivo_original, mime, tamano,
                    solicitado_por_usuario, solicitado_por_nombre, estado,
                    tasa_bs, monto_bs)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)"
            );
            $stmt->bind_param("idssssssissdd",
                $cuota_id, $monto, $metodo_pago, $referencia, $fecha_pago,
                $nombre_archivo, $comprobante['name'], $mime, $comprobante['size'],
                $usuario, $nombre_usuario,
                $tasa_bs, $monto_bs_comp
            );
            if (!$stmt->execute()) {
                if (file_exists($ruta_guardada)) @unlink($ruta_guardada);
                throw new Exception("Error al registrar la declaración: " . $stmt->error);
            }
            $stmt->close();

            $_SESSION['tipo_mensaje'] = 'success';
            $msg_bs = $monto_bs_comp !== null ? " (Bs " . number_format($monto_bs_comp, 2, ',', '.') . ")" : "";
            $_SESSION['mensaje'] = "✅ Su declaración de pago de $" . number_format($monto, 2) . $msg_bs
                . " ha sido enviada. Queda a la espera de verificación por la administración.";
            header("Location: ../Vista/consulta_pagos.php");
            exit();
        }

    } catch (Exception $e) {
        if ($connect->errno) $connect->rollback();
        if (!empty($ruta_guardada) && file_exists($ruta_guardada)) @unlink($ruta_guardada);
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ " . $e->getMessage();
        $back = $cuota_preseleccionada ? "?cuota_id=$cuota_preseleccionada" : ($aviso_unidad_id ? "?aviso_unidad_id=$aviso_unidad_id" : '');
        header("Location: ../Vista/registro_pagos.php" . $back);
        exit();
    }
}

$connect->close();
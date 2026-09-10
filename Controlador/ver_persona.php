<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin', 'junta']);

$cedula = trim($_GET['cedula'] ?? '');
$persona = null;
$tenencias = [];
$junta = null;
$resumen = null;

if (!empty($cedula)) {
    $stmt = $connect->prepare("SELECT cedula, nombre, correo, telefono, direccion, fecha_nacimiento, fecha_creacion
                               FROM personas WHERE cedula = ?");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    $persona = $result->fetch_assoc();
    $stmt->close();

    if ($persona) {
        $stmt = $connect->prepare(
            "SELECT t.id, t.rol, t.fecha_inicio, t.estado, u.id AS unidad_id, u.torre, u.numero, u.piso, u.tipo
             FROM tenencia t JOIN unidades u ON t.unidad_id = u.id
             WHERE t.persona_cedula = ? ORDER BY u.torre, u.numero"
        );
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $tenencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $connect->prepare(
            "SELECT * FROM junta_condominio WHERE persona_cedula = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $junta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $connect->prepare(
            "SELECT c.estado,
                    COALESCE(SUM(c.monto), 0) AS total_emitido,
                    COALESCE(SUM(c.monto_pagado), 0) AS total_pagado
             FROM cuotas_emitidas c
             JOIN tenencia t ON c.unidad_id = t.unidad_id
             WHERE t.persona_cedula = ? AND c.estado IN ('pendiente','vencida','parcial','pagada')
             GROUP BY c.estado"
        );
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total_emitido = 0;
        $total_pagado = 0;
        foreach ($rows as $r) {
            $total_emitido += (float)$r['total_emitido'];
            $total_pagado += (float)$r['total_pagado'];
        }
        $resumen = [
            'total_emitido' => $total_emitido,
            'total_pagado' => $total_pagado,
            'saldo' => $total_emitido - $total_pagado
        ];
    }
}

$connect->close();
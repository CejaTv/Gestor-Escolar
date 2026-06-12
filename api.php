<?php
// ============================================================
//  API CENTRAL  –  api.php
//  Maneja todas las peticiones AJAX del sistema
// ============================================================
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

try {
    match ($action) {
        // ── ALUMNOS ──────────────────────────────────────────
        'alumnos_list'      => alumnosList(),
        'alumno_save'       => alumnoSave(),
        'alumno_delete'     => alumnoDelete(),

        // ── DOCENTES ─────────────────────────────────────────
        'docentes_list'     => docentesList(),
        'docente_save'      => docenteSave(),

        // ── MATERIAS ─────────────────────────────────────────
        'materias_list'     => materiasList(),
        'materia_save'      => materiaSave(),

        // ── GRUPOS ───────────────────────────────────────────
        'grupos_list'       => gruposList(),
        'grupo_save'        => grupoSave(),

        // ── INSCRIPCIONES ─────────────────────────────────────
        'inscripciones_list'  => inscripcionesList(),
        'inscripcion_save'    => inscripcionSave(),
        'inscripcion_delete'  => inscripcionDelete(),

        // ── CALIFICACIONES ────────────────────────────────────
        'calificaciones_list' => calificacionesList(),
        'calificacion_save'   => calificacionSave(),

        // ── FINANCIERO ────────────────────────────────────────
        'estado_cuenta_list'  => estadoCuentaList(),
        'registrar_pago'      => registrarPago(),
        'generar_mensualidades' => generarMensualidades(),
        'conceptos_list'      => conceptosList(),
        'concepto_save'       => conceptoSave(),

        // ── REPORTES ──────────────────────────────────────────
        'reporte_aprovechamiento' => reporteAprovechamiento(),
        'reporte_morosidad'       => reporteMorosidad(),
        'reporte_horarios'        => reporteHorarios(),
        'calcular_promedio'       => calcularPromedio(),

        // ── CATÁLOGOS ─────────────────────────────────────────
        'catalogo_docentes'    => catalogoDocentes(),
        'catalogo_materias'    => catalogoMaterias(),
        'catalogo_grupos'      => catalogoGrupos(),
        'catalogo_alumnos'     => catalogoAlumnos(),
        'catalogo_conceptos'   => catalogoConceptos(),

        default => jsonResponse(['error' => 'Acción no reconocida: ' . $action], 400)
    };
} catch (PDOException $e) {
    // Detectar errores de triggers de PostgreSQL
    $msg = $e->getMessage();
    preg_match('/ERROR:  (.+)/i', $msg, $matches);
    $userMsg = $matches[1] ?? $msg;
    jsonResponse(['error' => $userMsg], 422);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

// ============================================================
//  ALUMNOS
// ============================================================
function alumnosList(): void {
    $db  = getDB();
    $sql = "SELECT alumno_id, matricula, nombre, apellido_p, apellido_m,
                   carrera, telefono, correo, estatus
            FROM alumnos ORDER BY apellido_p, nombre";
    $rows = $db->query($sql)->fetchAll();
    jsonResponse($rows);
}

function alumnoSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['alumno_id'] ?? null;

    $fields = ['matricula','nombre','apellido_p','apellido_m','carrera','telefono','correo','estatus'];

    if ($id) {
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
        $stmt = $db->prepare("UPDATE alumnos SET $sets WHERE alumno_id = :alumno_id");
        $stmt->execute(array_merge(array_intersect_key($data, array_flip($fields)), ['alumno_id' => $id]));
        jsonResponse(['ok' => true, 'message' => 'Alumno actualizado']);
    } else {
        $cols = implode(', ', $fields);
        $vals = implode(', ', array_map(fn($f) => ":$f", $fields));
        $stmt = $db->prepare("INSERT INTO alumnos ($cols) VALUES ($vals) RETURNING alumno_id");
        $stmt->execute(array_intersect_key($data, array_flip($fields)));
        $row = $stmt->fetch();
        jsonResponse(['ok' => true, 'alumno_id' => $row['alumno_id'], 'message' => 'Alumno registrado']);
    }
}

function alumnoDelete(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['alumno_id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $db->prepare("DELETE FROM alumnos WHERE alumno_id = ?")->execute([$id]);
    jsonResponse(['ok' => true, 'message' => 'Alumno eliminado']);
}

// ============================================================
//  DOCENTES
// ============================================================
function docentesList(): void {
    $rows = getDB()->query(
        "SELECT * FROM docentes ORDER BY apellido_p, nombre"
    )->fetchAll();
    jsonResponse($rows);
}

function docenteSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['docente_id'] ?? null;
    $fields = ['nombre','apellido_p','apellido_m','correo','telefono'];

    if ($id) {
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
        $stmt = $db->prepare("UPDATE docentes SET $sets WHERE docente_id = :docente_id");
        $stmt->execute(array_merge(array_intersect_key($data, array_flip($fields)), ['docente_id' => $id]));
        jsonResponse(['ok' => true, 'message' => 'Docente actualizado']);
    } else {
        $cols = implode(', ', $fields);
        $vals = implode(', ', array_map(fn($f) => ":$f", $fields));
        $stmt = $db->prepare("INSERT INTO docentes ($cols) VALUES ($vals) RETURNING docente_id");
        $stmt->execute(array_intersect_key($data, array_flip($fields)));
        jsonResponse(['ok' => true, 'docente_id' => $stmt->fetch()['docente_id'], 'message' => 'Docente registrado']);
    }
}

// ============================================================
//  MATERIAS
// ============================================================
function materiasList(): void {
    $rows = getDB()->query("SELECT * FROM materias ORDER BY nombre")->fetchAll();
    jsonResponse($rows);
}

function materiaSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['materia_id'] ?? null;
    $fields = ['clave','nombre','creditos','carrera'];

    if ($id) {
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
        $stmt = $db->prepare("UPDATE materias SET $sets WHERE materia_id = :materia_id");
        $stmt->execute(array_merge(array_intersect_key($data, array_flip($fields)), ['materia_id' => $id]));
        jsonResponse(['ok' => true, 'message' => 'Materia actualizada']);
    } else {
        $cols = implode(', ', $fields);
        $vals = implode(', ', array_map(fn($f) => ":$f", $fields));
        $stmt = $db->prepare("INSERT INTO materias ($cols) VALUES ($vals) RETURNING materia_id");
        $stmt->execute(array_intersect_key($data, array_flip($fields)));
        jsonResponse(['ok' => true, 'materia_id' => $stmt->fetch()['materia_id'], 'message' => 'Materia registrada']);
    }
}

// ============================================================
//  GRUPOS
// ============================================================
function gruposList(): void {
    $rows = getDB()->query(
        "SELECT g.grupo_id, m.nombre AS materia, d.nombre || ' ' || d.apellido_p AS docente,
                g.ciclo, g.cupo_maximo, g.aula, g.horario, g.materia_id, g.docente_id,
                (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.grupo_id) AS inscritos
         FROM grupos g
         JOIN materias m ON g.materia_id = m.materia_id
         JOIN docentes d ON g.docente_id = d.docente_id
         ORDER BY g.ciclo DESC, m.nombre"
    )->fetchAll();
    jsonResponse($rows);
}

function grupoSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['grupo_id'] ?? null;
    $fields = ['materia_id','docente_id','ciclo','cupo_maximo','aula','horario'];

    if ($id) {
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
        $stmt = $db->prepare("UPDATE grupos SET $sets WHERE grupo_id = :grupo_id");
        $stmt->execute(array_merge(array_intersect_key($data, array_flip($fields)), ['grupo_id' => $id]));
        jsonResponse(['ok' => true, 'message' => 'Grupo actualizado']);
    } else {
        $cols = implode(', ', $fields);
        $vals = implode(', ', array_map(fn($f) => ":$f", $fields));
        $stmt = $db->prepare("INSERT INTO grupos ($cols) VALUES ($vals) RETURNING grupo_id");
        $stmt->execute(array_intersect_key($data, array_flip($fields)));
        jsonResponse(['ok' => true, 'grupo_id' => $stmt->fetch()['grupo_id'], 'message' => 'Grupo creado']);
    }
}

// ============================================================
//  INSCRIPCIONES
// ============================================================
function inscripcionesList(): void {
    $alumnoId = $_GET['alumno_id'] ?? null;
    $sql = "SELECT i.inscripcion_id, a.matricula, a.nombre || ' ' || a.apellido_p AS alumno,
                   m.nombre AS materia, d.nombre || ' ' || d.apellido_p AS docente,
                   g.ciclo, g.aula, g.horario, i.fecha_inscr, i.alumno_id, i.grupo_id
            FROM inscripciones i
            JOIN alumnos  a ON i.alumno_id  = a.alumno_id
            JOIN grupos   g ON i.grupo_id   = g.grupo_id
            JOIN materias m ON g.materia_id = m.materia_id
            JOIN docentes d ON g.docente_id = d.docente_id";
    if ($alumnoId) $sql .= " WHERE i.alumno_id = " . (int)$alumnoId;
    $sql .= " ORDER BY g.ciclo DESC, m.nombre";
    jsonResponse(getDB()->query($sql)->fetchAll());
}

function inscripcionSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $stmt = $db->prepare(
        "INSERT INTO inscripciones (alumno_id, grupo_id, fecha_inscr)
         VALUES (:alumno_id, :grupo_id, CURRENT_DATE)"
    );
    $stmt->execute(['alumno_id' => $data['alumno_id'], 'grupo_id' => $data['grupo_id']]);
    jsonResponse(['ok' => true, 'message' => 'Inscripción realizada correctamente']);
}

function inscripcionDelete(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['inscripcion_id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $db->prepare("DELETE FROM inscripciones WHERE inscripcion_id = ?")->execute([$id]);
    jsonResponse(['ok' => true, 'message' => 'Inscripción cancelada']);
}

// ============================================================
//  CALIFICACIONES
// ============================================================
function calificacionesList(): void {
    $inscId = $_GET['inscripcion_id'] ?? null;
    $alumnoId = $_GET['alumno_id'] ?? null;

    $sql = "SELECT c.calificacion_id, c.inscripcion_id, c.parcial, c.calificacion,
                   c.fecha_registro,
                   a.nombre || ' ' || a.apellido_p AS alumno, a.matricula,
                   m.nombre AS materia, g.ciclo
            FROM calificaciones c
            JOIN inscripciones i ON c.inscripcion_id = i.inscripcion_id
            JOIN alumnos       a ON i.alumno_id       = a.alumno_id
            JOIN grupos        g ON i.grupo_id         = g.grupo_id
            JOIN materias      m ON g.materia_id       = m.materia_id
            WHERE 1=1";
    $params = [];
    if ($inscId)   { $sql .= " AND c.inscripcion_id = :inscripcion_id"; $params['inscripcion_id'] = $inscId; }
    if ($alumnoId) { $sql .= " AND i.alumno_id = :alumno_id";           $params['alumno_id']      = $alumnoId; }
    $sql .= " ORDER BY a.apellido_p, m.nombre, c.parcial";
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

function calificacionSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Verificar si ya existe
    $stmt = $db->prepare(
        "SELECT calificacion_id FROM calificaciones
         WHERE inscripcion_id = :inscripcion_id AND parcial = :parcial"
    );
    $stmt->execute(['inscripcion_id' => $data['inscripcion_id'], 'parcial' => $data['parcial']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upd = $db->prepare(
            "UPDATE calificaciones SET calificacion = :calificacion
             WHERE inscripcion_id = :inscripcion_id AND parcial = :parcial"
        );
        $upd->execute([
            'calificacion'    => $data['calificacion'],
            'inscripcion_id'  => $data['inscripcion_id'],
            'parcial'         => $data['parcial'],
        ]);
        jsonResponse(['ok' => true, 'message' => 'Calificación actualizada (bitácora registrada)']);
    } else {
        $ins = $db->prepare(
            "INSERT INTO calificaciones (inscripcion_id, parcial, calificacion)
             VALUES (:inscripcion_id, :parcial, :calificacion)"
        );
        $ins->execute([
            'inscripcion_id' => $data['inscripcion_id'],
            'parcial'        => $data['parcial'],
            'calificacion'   => $data['calificacion'],
        ]);
        jsonResponse(['ok' => true, 'message' => 'Calificación registrada']);
    }
}

// ============================================================
//  FINANCIERO
// ============================================================
function estadoCuentaList(): void {
    $alumnoId = $_GET['alumno_id'] ?? null;
    $sql = "SELECT ec.estado_id, a.matricula, a.nombre || ' ' || a.apellido_p AS alumno,
                   cp.nombre AS concepto, ec.ciclo, ec.mes, ec.anio,
                   ec.monto, ec.saldo, ec.fecha_limite, ec.pagado, ec.alumno_id
            FROM estado_cuenta ec
            JOIN alumnos        a  ON ec.alumno_id  = a.alumno_id
            JOIN conceptos_pago cp ON ec.concepto_id = cp.concepto_id
            WHERE 1=1";
    $params = [];
    if ($alumnoId) { $sql .= " AND ec.alumno_id = :alumno_id"; $params['alumno_id'] = $alumnoId; }
    $sql .= " ORDER BY ec.anio DESC, ec.mes DESC, a.apellido_p";
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

function registrarPago(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $db->beginTransaction();
    try {
        // Insertar pago
        $ins = $db->prepare(
            "INSERT INTO pagos_colegiatura (alumno_id, estado_id, monto_pagado, metodo_pago, fecha_pago)
             VALUES (:alumno_id, :estado_id, :monto_pagado, :metodo_pago, CURRENT_DATE)"
        );
        $ins->execute([
            'alumno_id'   => $data['alumno_id'],
            'estado_id'   => $data['estado_id'],
            'monto_pagado'=> $data['monto_pagado'],
            'metodo_pago' => $data['metodo_pago'] ?? 'efectivo',
        ]);

        // Actualizar saldo en estado_cuenta
        $nuevoSaldo = $db->prepare(
            "UPDATE estado_cuenta
             SET saldo  = GREATEST(saldo - :monto, 0),
                 pagado = CASE WHEN saldo - :monto2 <= 0 THEN TRUE ELSE FALSE END
             WHERE estado_id = :estado_id"
        );
        $nuevoSaldo->execute([
            'monto'     => $data['monto_pagado'],
            'monto2'    => $data['monto_pagado'],
            'estado_id' => $data['estado_id'],
        ]);

        $db->commit();
        jsonResponse(['ok' => true, 'message' => 'Pago registrado correctamente']);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function generarMensualidades(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $stmt = $db->prepare(
        "CALL sp_generar_mensualidades(:ciclo, :mes_inicio, :anio, :meses, :concepto_id)"
    );
    $stmt->execute([
        'ciclo'       => $data['ciclo'],
        'mes_inicio'  => (int)$data['mes_inicio'],
        'anio'        => (int)$data['anio'],
        'meses'       => (int)$data['meses'],
        'concepto_id' => (int)$data['concepto_id'],
    ]);
    jsonResponse(['ok' => true, 'message' => 'Mensualidades generadas para el ciclo ' . $data['ciclo']]);
}

function conceptosList(): void {
    jsonResponse(getDB()->query("SELECT * FROM conceptos_pago ORDER BY nombre")->fetchAll());
}

function conceptoSave(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = $data['concepto_id'] ?? null;

    if ($id) {
        $db->prepare("UPDATE conceptos_pago SET nombre=:nombre, monto=:monto WHERE concepto_id=:id")
           ->execute(['nombre' => $data['nombre'], 'monto' => $data['monto'], 'id' => $id]);
        jsonResponse(['ok' => true, 'message' => 'Concepto actualizado']);
    } else {
        $db->prepare("INSERT INTO conceptos_pago (nombre, monto) VALUES (:nombre, :monto)")
           ->execute(['nombre' => $data['nombre'], 'monto' => $data['monto']]);
        jsonResponse(['ok' => true, 'message' => 'Concepto creado']);
    }
}

// ============================================================
//  REPORTES
// ============================================================
function reporteAprovechamiento(): void {
    $rows = getDB()->query("SELECT * FROM vw_reporte_aprovechamiento")->fetchAll();
    jsonResponse($rows);
}

function reporteMorosidad(): void {
    $rows = getDB()->query("SELECT * FROM vw_morosidad_pagos")->fetchAll();
    jsonResponse($rows);
}

function reporteHorarios(): void {
    $rows = getDB()->query("SELECT * FROM vw_disponibilidad_horarios")->fetchAll();
    jsonResponse($rows);
}

function calcularPromedio(): void {
    $db   = getDB();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $stmt = $db->prepare("CALL sp_calcular_promedio_final(:alumno_id, :ciclo)");
    $stmt->execute(['alumno_id' => $data['alumno_id'], 'ciclo' => $data['ciclo']]);
    jsonResponse(['ok' => true, 'message' => "Promedio calculado para el alumno {$data['alumno_id']} en ciclo {$data['ciclo']}"]);
}

// ============================================================
//  CATÁLOGOS (para selects)
// ============================================================
function catalogoDocentes(): void {
    $rows = getDB()->query(
        "SELECT docente_id AS id, nombre || ' ' || apellido_p AS label FROM docentes ORDER BY label"
    )->fetchAll();
    jsonResponse($rows);
}

function catalogoMaterias(): void {
    $rows = getDB()->query(
        "SELECT materia_id AS id, nombre || ' (' || clave || ')' AS label FROM materias ORDER BY label"
    )->fetchAll();
    jsonResponse($rows);
}

function catalogoGrupos(): void {
    $rows = getDB()->query(
        "SELECT g.grupo_id AS id,
                m.nombre || ' – ' || g.ciclo || ' | ' || g.horario AS label
         FROM grupos g JOIN materias m ON g.materia_id = m.materia_id
         ORDER BY label"
    )->fetchAll();
    jsonResponse($rows);
}

function catalogoAlumnos(): void {
    $rows = getDB()->query(
        "SELECT alumno_id AS id,
                matricula || ' – ' || nombre || ' ' || apellido_p AS label
         FROM alumnos WHERE estatus = 'activo' ORDER BY label"
    )->fetchAll();
    jsonResponse($rows);
}

function catalogoConceptos(): void {
    $rows = getDB()->query(
        "SELECT concepto_id AS id, nombre || ' ($' || monto || ')' AS label
         FROM conceptos_pago ORDER BY label"
    )->fetchAll();
    jsonResponse($rows);
}

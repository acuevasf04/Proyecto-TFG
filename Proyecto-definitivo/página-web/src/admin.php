<?php
session_start();
require_once 'config.php';

// ── Protección: solo usuarios logueados ──────────────────────────────────────
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php#login');
    exit;
}

// ── Tablas disponibles ────────────────────────────────────────────────────────
$tablas_permitidas = ['Usuarios', 'Productos', 'Pedidos', 'Detalles_Pedidos'];

// ── Estado ────────────────────────────────────────────────────────────────────
$tabla_activa = $_GET['tabla'] ?? 'Usuarios';
if (!in_array($tabla_activa, $tablas_permitidas)) $tabla_activa = 'Usuarios';

$accion   = $_GET['accion']   ?? 'browse';   // browse | structure | sql | insert | edit | delete
$mensaje  = '';
$tipo_msg = '';
$sql_result = null;
$sql_error  = '';
$sql_query  = '';
$columnas   = [];
$filas      = [];

// ──────────────────────────────────────────────────────────────────────────────
//  PROCESAMIENTO DE FORMULARIOS
// ──────────────────────────────────────────────────────────────────────────────
try {
    $db = getDB();

    // Obtener columnas de la tabla activa
    $stmt = $db->query("DESCRIBE `$tabla_activa`");
    $columnas = $stmt->fetchAll();

    // ---------- EJECUTAR SQL LIBRE ----------
    if ($accion === 'sql' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql_query = trim($_POST['query'] ?? '');
        if ($sql_query !== '') {
            try {
                $stmt = $db->query($sql_query);
                // SELECT → mostrar resultados
                if ($stmt && $stmt->columnCount() > 0) {
                    $sql_result = $stmt->fetchAll();
                } else {
                    $afectadas  = $stmt ? $stmt->rowCount() : 0;
                    $mensaje    = "Consulta ejecutada correctamente. Filas afectadas: $afectadas";
                    $tipo_msg   = 'success';
                }
            } catch (PDOException $e) {
                $sql_error = $e->getMessage();
            }
        }
    }

    // ---------- INSERT ----------
    if ($accion === 'insert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $campos = $_POST['campos'] ?? [];
        $cols   = array_keys($campos);
        $vals   = array_values($campos);

        // Filtrar campos vacíos (auto_increment)
        $cols_f = [];
        $vals_f = [];
        foreach ($cols as $i => $c) {
            if ($vals[$i] !== '') {
                $cols_f[] = "`$c`";
                $vals_f[] = $vals[$i];
            }
        }
        if ($cols_f) {
            $placeholders = implode(',', array_fill(0, count($cols_f), '?'));
            $sql = "INSERT INTO `$tabla_activa` (" . implode(',', $cols_f) . ") VALUES ($placeholders)";
            $db->prepare($sql)->execute($vals_f);
            $mensaje  = 'Registro insertado correctamente.';
            $tipo_msg = 'success';
            $accion   = 'browse';
        }
    }

    // ---------- UPDATE ----------
    if ($accion === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pk_col = $_POST['pk_col'] ?? 'id';
        $pk_val = $_POST['pk_val'] ?? '';
        $campos = $_POST['campos'] ?? [];
        $sets   = [];
        $vals   = [];
        foreach ($campos as $col => $val) {
            if ($col !== $pk_col) {
                $sets[] = "`$col` = ?";
                $vals[] = $val;
            }
        }
        if ($sets && $pk_val !== '') {
            $vals[] = $pk_val;
            $sql = "UPDATE `$tabla_activa` SET " . implode(', ', $sets) . " WHERE `$pk_col` = ?";
            $db->prepare($sql)->execute($vals);
            $mensaje  = 'Registro actualizado correctamente.';
            $tipo_msg = 'success';
            $accion   = 'browse';
        }
    }

    // ---------- DELETE ----------
    if ($accion === 'delete' && isset($_GET['pk_col'], $_GET['pk_val'])) {
        $pk_col = $_GET['pk_col'];
        $pk_val = $_GET['pk_val'];
        $db->prepare("DELETE FROM `$tabla_activa` WHERE `$pk_col` = ?")->execute([$pk_val]);
        $mensaje  = 'Registro eliminado.';
        $tipo_msg = 'warning';
        $accion   = 'browse';
    }

    // ---------- BROWSE: obtener filas ----------
    if ($accion === 'browse' || $accion === 'delete') {
        $limite  = 50;
        $pagina  = max(1, (int)($_GET['p'] ?? 1));
        $offset  = ($pagina - 1) * $limite;
        $total   = $db->query("SELECT COUNT(*) FROM `$tabla_activa`")->fetchColumn();
        $filas   = $db->query("SELECT * FROM `$tabla_activa` LIMIT $limite OFFSET $offset")->fetchAll();
        $paginas = (int)ceil($total / $limite);
    }

    // ---------- EDIT: cargar fila por PK ----------
    $fila_edit = null;
    if ($accion === 'edit' && isset($_GET['pk_col'], $_GET['pk_val'])) {
        $fila_edit = $db->prepare("SELECT * FROM `$tabla_activa` WHERE `{$_GET['pk_col']}` = ?");
        $fila_edit->execute([$_GET['pk_val']]);
        $fila_edit = $fila_edit->fetch();
    }

    // ---------- STRUCTURE ----------
    $estructura = [];
    if ($accion === 'structure') {
        $estructura = $columnas;
    }

    // Estadísticas del sidebar
    $stats = [];
    foreach ($tablas_permitidas as $t) {
        $stats[$t] = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    }

} catch (PDOException $e) {
    $sql_error = 'Error de base de datos: ' . $e->getMessage();
}

// Columnas clave primaria (primer campo de la tabla generalmente)
$pk_col = $columnas[0]['Field'] ?? 'id';

// Consulta SQL de ejemplo pre-cargada
$sql_ejemplos = [
    "SELECT * FROM `$tabla_activa`",
    "SELECT COUNT(*) AS total FROM `$tabla_activa`",
    "SELECT * FROM `$tabla_activa` LIMIT 10",
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aromaris Admin – <?= htmlspecialchars($tabla_activa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --a-green:      #4a7c59;
            --a-green-dark: #2d4f38;
            --a-green-lt:   #e8f3ec;
            --sidebar-w:    260px;
        }
        body { font-size: .875rem; background: #f3f4f6; }

        /* ── Sidebar ── */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--a-green-dark);
            color: #fff;
            position: fixed;
            top: 0; left: 0;
            overflow-y: auto;
            z-index: 1040;
        }
        #sidebar .sidebar-brand {
            background: rgba(0,0,0,.25);
            padding: 1rem 1.25rem;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: .02em;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        #sidebar .nav-section {
            padding: .5rem 1rem;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            opacity: .5;
            margin-top: 1rem;
        }
        #sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: .45rem 1.25rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: .5rem;
            transition: background .15s;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            background: rgba(255,255,255,.12);
            color: #fff;
        }
        #sidebar .badge-count {
            margin-left: auto;
            font-size: .7rem;
            background: rgba(255,255,255,.15);
            border-radius: 999px;
            padding: .1em .55em;
        }
        #sidebar .db-info {
            font-size: .75rem;
            opacity: .6;
            padding: .5rem 1.25rem 1rem;
        }

        /* ── Main ── */
        #main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
        }

        /* ── Topbar ── */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: .65rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        /* ── Action tabs ── */
        .action-tabs .nav-link {
            color: #6b7280;
            border-radius: 0;
            border-bottom: 2px solid transparent;
            padding: .5rem 1rem;
        }
        .action-tabs .nav-link.active {
            color: var(--a-green);
            border-bottom-color: var(--a-green);
            background: transparent;
        }
        .action-tabs .nav-link:hover:not(.active) {
            color: var(--a-green-dark);
            background: var(--a-green-lt);
        }

        /* ── Table ── */
        .table-admin thead { background: var(--a-green); color: #fff; }
        .table-admin thead th { font-weight: 500; white-space: nowrap; }
        .table-admin tbody tr:hover { background: #f0faf4; }
        .table-admin td { vertical-align: middle; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── SQL editor ── */
        #sql-editor {
            font-family: 'Courier New', monospace;
            font-size: .85rem;
            background: #1e1e1e;
            color: #d4d4d4;
            border: none;
            border-radius: .5rem;
            resize: vertical;
            min-height: 130px;
        }

        /* ── Form ── */
        .form-label { font-weight: 500; }

        /* ── Badge tipo ── */
        .type-badge { font-size: .7rem; font-weight: 400; opacity: .8; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            #sidebar { position: relative; width: 100%; min-height: auto; }
            #main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- ══════════════ SIDEBAR ══════════════ -->
<aside id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-flower1 me-2"></i>Aromaris
        <div class="db-info mt-1">Base de datos: <strong>MiTienda</strong></div>
    </div>

    <div class="nav-section">Tablas</div>
    <nav class="nav flex-column">
        <?php foreach ($tablas_permitidas as $t): ?>
        <a href="?tabla=<?= $t ?>&accion=browse"
           class="nav-link <?= $t === $tabla_activa ? 'active' : '' ?>">
            <i class="bi bi-table"></i> <?= $t ?>
            <span class="badge-count"><?= $stats[$t] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="nav-section mt-3">Acciones</div>
    <nav class="nav flex-column">
        <a href="?tabla=<?= $tabla_activa ?>&accion=sql" class="nav-link <?= $accion==='sql' ? 'active':'' ?>">
            <i class="bi bi-terminal"></i> Ejecutar SQL
        </a>
        <a href="index.php" class="nav-link">
            <i class="bi bi-house"></i> Volver al sitio
        </a>
        <a href="index.php?logout=1" class="nav-link text-danger-emphasis">
            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
        </a>
    </nav>

    <div class="db-info mt-4">
        <i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem"></i>
        Conectado · <?= DB_HOST ?>
    </div>
</aside>

<!-- ══════════════ CONTENIDO PRINCIPAL ══════════════ -->
<div id="main-content">

    <!-- Topbar -->
    <div class="topbar d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-database text-success"></i>
            <span class="fw-semibold">MiTienda</span>
            <i class="bi bi-chevron-right text-muted" style="font-size:.7rem"></i>
            <span class="text-muted"><?= htmlspecialchars($tabla_activa) ?></span>
        </div>
        <div class="text-muted">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['usuario']) ?>
        </div>
    </div>

    <div class="p-4">

        <!-- Alertas -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'warning' ?> alert-dismissible fade show d-flex align-items-center gap-2">
            <i class="bi bi-<?= $tipo_msg === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($sql_error): ?>
        <div class="alert alert-danger d-flex gap-2">
            <i class="bi bi-x-octagon-fill flex-shrink-0 mt-1"></i>
            <div><strong>Error SQL:</strong><br><code><?= htmlspecialchars($sql_error) ?></code></div>
        </div>
        <?php endif; ?>

        <!-- ══ TABS de acción ══ -->
        <?php if ($accion !== 'sql'): ?>
        <ul class="nav action-tabs border-bottom mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $accion==='browse'?'active':'' ?>" href="?tabla=<?= $tabla_activa ?>&accion=browse">
                    <i class="bi bi-grid me-1"></i>Examinar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $accion==='structure'?'active':'' ?>" href="?tabla=<?= $tabla_activa ?>&accion=structure">
                    <i class="bi bi-list-columns me-1"></i>Estructura
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $accion==='insert'?'active':'' ?>" href="?tabla=<?= $tabla_activa ?>&accion=insert">
                    <i class="bi bi-plus-lg me-1"></i>Insertar
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- ══════════ BROWSE ══════════ -->
        <?php if ($accion === 'browse'): ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold">
                    <i class="bi bi-table me-1 text-success"></i>
                    <?= htmlspecialchars($tabla_activa) ?>
                    <span class="badge bg-light text-muted ms-1"><?= $total ?? 0 ?> filas</span>
                </span>
                <a href="?tabla=<?= $tabla_activa ?>&accion=insert" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo registro
                </a>
            </div>
            <div class="card-body p-0">
                <?php if ($filas): ?>
                <div class="table-responsive">
                    <table class="table table-admin table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px">Acciones</th>
                                <?php foreach (array_keys($filas[0]) as $col): ?>
                                    <th><?= htmlspecialchars($col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <tr>
                                <td>
                                    <a href="?tabla=<?= $tabla_activa ?>&accion=edit&pk_col=<?= urlencode($pk_col) ?>&pk_val=<?= urlencode($fila[$pk_col]) ?>"
                                       class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:.15rem .4rem"
                                       title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="?tabla=<?= $tabla_activa ?>&accion=delete&pk_col=<?= urlencode($pk_col) ?>&pk_val=<?= urlencode($fila[$pk_col]) ?>"
                                       class="btn btn-xs btn-outline-danger ms-1" style="font-size:.75rem;padding:.15rem .4rem"
                                       title="Eliminar"
                                       onclick="return confirm('¿Eliminar este registro?')"><i class="bi bi-trash"></i></a>
                                </td>
                                <?php foreach ($fila as $val): ?>
                                    <td title="<?= htmlspecialchars((string)$val) ?>">
                                        <?= $val === null
                                            ? '<span class="text-muted fst-italic">NULL</span>'
                                            : htmlspecialchars(mb_strimwidth((string)$val, 0, 60, '…')) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if (($paginas ?? 1) > 1): ?>
                <div class="d-flex justify-content-center py-3 border-top">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $paginas; $i++): ?>
                            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?tabla=<?= $tabla_activa ?>&accion=browse&p=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-4 d-block mb-3 opacity-50"></i>
                    La tabla está vacía.
                    <a href="?tabla=<?= $tabla_activa ?>&accion=insert" class="d-block mt-2">Insertar primer registro</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>


        <!-- ══════════ STRUCTURE ══════════ -->
        <?php if ($accion === 'structure'): ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-list-columns me-1 text-primary"></i>
                Estructura de <strong><?= htmlspecialchars($tabla_activa) ?></strong>
            </div>
            <div class="table-responsive">
                <table class="table table-admin table-sm mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Predeterminado</th><th>Extra</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($estructura as $i => $col): ?>
                        <tr>
                            <td class="text-muted"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars($col['Field']) ?></strong></td>
                            <td><code class="text-primary"><?= htmlspecialchars($col['Type']) ?></code></td>
                            <td><?= $col['Null'] === 'YES' ? '<span class="badge bg-warning text-dark">YES</span>' : '<span class="badge bg-secondary">NO</span>' ?></td>
                            <td><?php
                                if ($col['Key'] === 'PRI') echo '<span class="badge bg-danger">PRI</span>';
                                elseif ($col['Key'] === 'UNI') echo '<span class="badge bg-info text-dark">UNI</span>';
                                elseif ($col['Key'] === 'MUL') echo '<span class="badge bg-success">MUL</span>';
                                else echo '<span class="text-muted">—</span>';
                            ?></td>
                            <td class="text-muted"><?= $col['Default'] !== null ? htmlspecialchars($col['Default']) : '<em>NULL</em>' ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($col['Extra']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


        <!-- ══════════ INSERT ══════════ -->
        <?php if ($accion === 'insert'): ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-plus-circle me-1 text-success"></i>
                Insertar en <strong><?= htmlspecialchars($tabla_activa) ?></strong>
            </div>
            <div class="card-body">
                <form method="POST" action="?tabla=<?= $tabla_activa ?>&accion=insert">
                    <div class="row g-3">
                    <?php foreach ($columnas as $col): ?>
                        <?php
                            $es_auto = stripos($col['Extra'], 'auto_increment') !== false;
                            $tipo    = $col['Type'];
                        ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                <?= htmlspecialchars($col['Field']) ?>
                                <span class="type-badge text-muted"><?= htmlspecialchars($tipo) ?></span>
                                <?php if ($col['Key'] === 'PRI'): ?><span class="badge bg-danger ms-1" style="font-size:.65rem">PK</span><?php endif; ?>
                            </label>
                            <?php if ($es_auto): ?>
                                <input type="text" class="form-control bg-light text-muted"
                                       name="campos[<?= htmlspecialchars($col['Field']) ?>]"
                                       placeholder="Auto generado" disabled>
                                <input type="hidden" name="campos[<?= htmlspecialchars($col['Field']) ?>]" value="">
                            <?php elseif (stripos($tipo, 'text') !== false): ?>
                                <textarea class="form-control" name="campos[<?= htmlspecialchars($col['Field']) ?>]"
                                          rows="3" <?= $col['Null']==='NO' && !$es_auto ? 'required' : '' ?>></textarea>
                            <?php else: ?>
                                <input type="<?= stripos($tipo,'int')!==false || stripos($tipo,'decimal')!==false ? 'number' : 'text' ?>"
                                       class="form-control"
                                       name="campos[<?= htmlspecialchars($col['Field']) ?>]"
                                       step="<?= stripos($tipo,'decimal')!==false ? '0.01' : '1' ?>"
                                       <?= $col['Null']==='NO' && !$es_auto ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg me-1"></i>Insertar registro
                        </button>
                        <a href="?tabla=<?= $tabla_activa ?>&accion=browse" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>


        <!-- ══════════ EDIT ══════════ -->
        <?php if ($accion === 'edit' && $fila_edit): ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-pencil me-1 text-primary"></i>
                Editar registro en <strong><?= htmlspecialchars($tabla_activa) ?></strong>
                <span class="text-muted fw-normal">· <?= htmlspecialchars($pk_col) ?> = <?= htmlspecialchars($_GET['pk_val']) ?></span>
            </div>
            <div class="card-body">
                <form method="POST" action="?tabla=<?= $tabla_activa ?>&accion=update">
                    <input type="hidden" name="pk_col" value="<?= htmlspecialchars($pk_col) ?>">
                    <input type="hidden" name="pk_val" value="<?= htmlspecialchars($_GET['pk_val'] ?? '') ?>">
                    <div class="row g-3">
                    <?php foreach ($columnas as $col): ?>
                        <?php
                            $field   = $col['Field'];
                            $tipo    = $col['Type'];
                            $es_pk   = $col['Key'] === 'PRI';
                            $val_act = htmlspecialchars((string)($fila_edit[$field] ?? ''));
                        ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                <?= htmlspecialchars($field) ?>
                                <span class="type-badge text-muted"><?= htmlspecialchars($tipo) ?></span>
                                <?php if ($es_pk): ?><span class="badge bg-danger ms-1" style="font-size:.65rem">PK</span><?php endif; ?>
                            </label>
                            <?php if ($es_pk): ?>
                                <input type="text" class="form-control bg-light text-muted"
                                       name="campos[<?= htmlspecialchars($field) ?>]"
                                       value="<?= $val_act ?>" readonly>
                            <?php elseif (stripos($tipo,'text') !== false): ?>
                                <textarea class="form-control" name="campos[<?= htmlspecialchars($field) ?>]"
                                          rows="3"><?= $val_act ?></textarea>
                            <?php else: ?>
                                <input type="<?= stripos($tipo,'int')!==false || stripos($tipo,'decimal')!==false ? 'number' : 'text' ?>"
                                       class="form-control"
                                       name="campos[<?= htmlspecialchars($field) ?>]"
                                       step="<?= stripos($tipo,'decimal')!==false ? '0.01' : '1' ?>"
                                       value="<?= $val_act ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Guardar cambios
                        </button>
                        <a href="?tabla=<?= $tabla_activa ?>&accion=browse" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>


        <!-- ══════════ SQL ══════════ -->
        <?php if ($accion === 'sql'): ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-1"><i class="bi bi-terminal me-2"></i>Consola SQL</h5>
            <p class="text-muted">Ejecuta cualquier sentencia SQL sobre la base de datos <strong>MiTienda</strong>.</p>
        </div>

        <!-- Ejemplos rápidos -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($sql_ejemplos as $ej): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-sql-example"
                    data-query="<?= htmlspecialchars($ej) ?>">
                <?= htmlspecialchars($ej) ?>
            </button>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-sql-example"
                    data-query="SELECT u.nombre, COUNT(p.id) AS pedidos FROM Usuarios u LEFT JOIN Pedidos p ON u.id = p.usuario_id GROUP BY u.id">
                JOIN Usuarios + Pedidos
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body">
                <form method="POST" action="?tabla=<?= $tabla_activa ?>&accion=sql">
                    <label class="form-label fw-semibold mb-2">Sentencia SQL</label>
                    <textarea id="sql-editor" class="form-control mb-3" name="query"
                              rows="6" placeholder="SELECT * FROM Usuarios LIMIT 10;"><?= htmlspecialchars($sql_query) ?></textarea>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-play-fill me-1"></i>Ejecutar
                        </button>
                        <button type="button" id="btn-clear-sql" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultados -->
        <?php if ($sql_result !== null && count($sql_result) > 0): ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white d-flex justify-content-between py-3">
                <span class="fw-semibold"><i class="bi bi-table me-1 text-success"></i>Resultados</span>
                <span class="badge bg-light text-muted"><?= count($sql_result) ?> filas</span>
            </div>
            <div class="table-responsive">
                <table class="table table-admin table-sm mb-0">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($sql_result[0]) as $col): ?>
                                <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sql_result as $fila): ?>
                        <tr>
                            <?php foreach ($fila as $val): ?>
                                <td><?= $val === null
                                    ? '<span class="text-muted fst-italic">NULL</span>'
                                    : htmlspecialchars((string)$val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif ($sql_result !== null && count($sql_result) === 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>La consulta no devolvió resultados.
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div><!-- /p-4 -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Ejemplos SQL rápidos
    document.querySelectorAll('.btn-sql-example').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('sql-editor').value = btn.dataset.query;
        });
    });
    // Limpiar SQL
    document.getElementById('btn-clear-sql')?.addEventListener('click', () => {
        document.getElementById('sql-editor').value = '';
        document.getElementById('sql-editor').focus();
    });
</script>
</body>
</html>

<?php
/**
 * Reporte de Avance y Cumplimiento para Certificación
 * Vista apta para pantalla e impresión formal.
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Reporte de Avance y Certificación';
$activeMenu = 'reportes';
$db = Database::getConnection();

// Filtro por pasante específico
$id_pasante = isset($_GET['id_pasante']) ? (int)$_GET['id_pasante'] : 0;

$sql = "SELECT v.*, pas.telefono, pas.correo, pas.semestre
        FROM vista_horas_proceso v
        INNER JOIN pasantes pas ON v.id_pasante = pas.id_pasante
        WHERE 1=1 ";

if ($id_pasante > 0) {
    $sql .= " AND v.id_pasante = " . $id_pasante;
}

$sql .= " ORDER BY v.porcentaje_completado DESC";
$reportes = $db->query($sql)->fetchAll();

$listaPasantes = $db->query("SELECT id_pasante, nombres, apellidos, ci FROM pasantes ORDER BY apellidos ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Informe de Avance y Estado de Horas Reglamentarias</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Documento oficial de control de cumplimiento con miras a la emisión de certificados CCDB.
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fa-solid fa-print"></i> Imprimir Informe
                    </button>
                </div>
            </div>

            <!-- Selector de pasante para filtrar reporte -->
            <form method="GET" action="reporte_avance.php" style="display: flex; gap: 10px; align-items: center;" class="no-print">
                <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Filtrar por estudiante:</label>
                <select name="id_pasante" class="form-control" style="max-width: 320px;" onchange="this.form.submit()">
                    <option value="0">-- Todos los Estudiantes en Proceso --</option>
                    <?php foreach ($listaPasantes as $pas): ?>
                        <option value="<?= $pas['id_pasante'] ?>" <?= ($id_pasante === (int)$pas['id_pasante']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pas['apellidos'] . ' ' . $pas['nombres']) ?> (CI: <?= htmlspecialchars($pas['ci']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id_pasante > 0): ?>
                    <a href="reporte_avance.php" class="btn btn-outline btn-sm">Ver Todos</a>
                <?php endif; ?>
            </form>
        </div>

        <?php foreach ($reportes as $rep): ?>
            <?php 
                $porc = (float)$rep['porcentaje_completado'];
                $cumplido = $porc >= 100;
            ?>
            <div class="card" style="border-left: 6px solid <?= $cumplido ? '#10b981' : 'var(--primary-blue)' ?>; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <div>
                        <span class="badge <?= $cumplido ? 'badge-success' : 'badge-info' ?>" style="font-size: 0.8rem; margin-bottom: 6px;">
                            <?= $cumplido ? 'HABILITADO PARA CERTIFICACIÓN' : 'EN DESARROLLO' ?>
                        </span>
                        <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--primary-blue);">
                            <?= htmlspecialchars($rep['nombres'] . ' ' . $rep['apellidos']) ?>
                        </h2>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            CI: <strong><?= htmlspecialchars($rep['ci']) ?></strong> &bull; <?= htmlspecialchars($rep['carrera']) ?> &bull; <?= htmlspecialchars($rep['institucion']) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.8rem; font-weight: 900; color: <?= $cumplido ? '#15803d' : 'var(--primary-blue)' ?>;">
                            <?= $porc ?>%
                        </div>
                        <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            Cumplimiento
                        </span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                    <div style="background: #f8fafc; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #64748b; font-weight: 600;">MODALIDAD</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 4px;"><?= htmlspecialchars($rep['modalidad']) ?></div>
                    </div>

                    <div style="background: #f8fafc; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #64748b; font-weight: 600;">HORAS REQUERIDAS</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 4px;"><?= $rep['horas_requeridas'] ?> hrs</div>
                    </div>

                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #15803d; font-weight: 600;">HORAS ACUMULADAS</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #15803d; margin-top: 4px;"><?= $rep['horas_acumuladas'] ?> hrs</div>
                    </div>

                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #b91c1c; font-weight: 600;">HORAS FALTANTES</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #dc2626; margin-top: 4px;"><?= $rep['horas_faltantes'] ?> hrs</div>
                    </div>
                </div>

                <!-- Barra de progreso -->
                <div class="progress-container" style="height: 16px; margin-bottom: 12px; position: relative; background: #e2e8f0; border-radius: 999px;">
                    <div class="progress-bar <?= ($porc < 50) ? 'progress-bar-warning' : '' ?>" style="width: <?= min($porc, 100) ?>%; border-radius: 999px;"></div>
                    <div style="position: absolute; top: 50%; left: calc(<?= min(max($porc, 2), 100) ?>% - 7px); transform: translateY(-50%); width: 14px; height: 14px; border-radius: 50%; background: #f59e0b; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                </div>

                <?php if ((float)$rep['horas_descontadas'] > 0): ?>
                    <p style="font-size: 0.82rem; color: #dc2626; margin-top: 4px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Cuenta con sanciones activas que descontaron <strong><?= $rep['horas_descontadas'] ?> horas</strong> del tiempo acumulado.
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>

<style>
@media print {
    .sidebar, .top-navbar, .no-print, .btn, .main-footer {
        display: none !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
        page-break-inside: avoid;
    }
}
</style>

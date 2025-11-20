<?php
require_once __DIR__ . '/../functions/functions.php';

header('Content-Type: application/json');

try {
    // Filters
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $buildingType = isset($_GET['building_type']) ? trim((string)$_GET['building_type']) : '';
    $startDate = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $endDate = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

    // Base: only unassigned devices for this grid
    $baseDevices = array_filter($devices ?? [], function($d){ return empty($d['building_id']); });

    // Apply search filter
    if ($q !== '') {
        $qLower = mb_strtolower($q);
        $baseDevices = array_filter($baseDevices, function($d) use ($qLower){
            $haystack = mb_strtolower(
                ($d['device_name'] ?? '') . ' ' .
                ($d['serial_number'] ?? '') . ' ' .
                ($d['device_number'] ?? '') . ' ' .
                ($d['device_type'] ?? '')
            );
            return strpos($haystack, $qLower) !== false;
        });
    }

    // Apply date range filter against updated_at or created_at if present
    if ($startDate !== '' || $endDate !== '') {
        $startTs = $startDate !== '' ? strtotime($startDate . ' 00:00:00') : null;
        $endTs = $endDate !== '' ? strtotime($endDate . ' 23:59:59') : null;
        $baseDevices = array_filter($baseDevices, function($d) use ($startTs, $endTs){
            $dateStr = $d['updated_at'] ?? ($d['created_at'] ?? null);
            if (!$dateStr) return false; // no timestamp, exclude when filtering by date
            $ts = strtotime($dateStr);
            if ($startTs && $ts < $startTs) return false;
            if ($endTs && $ts > $endTs) return false;
            return true;
        });
    }

    // building_type filter is not applicable to unassigned devices (no building). If provided, result is empty.
    if ($buildingType !== '') {
        $baseDevices = []; // keep semantics explicit
    }

    ob_start();
    if (empty($baseDevices)) {
        echo '<div class="col-12">';
        echo '<div class="alert alert-light border" style="border-radius:10px;">No devices match your filters.</div>';
        echo '</div>';
    } else {
        foreach ($baseDevices as $device) {
            ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100 device-card orange-accent">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-router me-2 text-primary"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($device['device_name']); ?></span>
                            </div>
                            <span class="badge bg-<?php echo $device['status'] === 'online' ? 'success' : ($device['status'] === 'offline' ? 'secondary' : 'danger'); ?>">
                                <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i><?php echo ucfirst($device['status']); ?>
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            <i class="bi bi-hash me-1"></i><code><?php echo htmlspecialchars($device['serial_number']); ?></code>
                        </div>
                        <div class="mb-2">
                            <span class="badge text-bg-light"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($device['device_type'] ?: 'FIREGUARD DEVICE'); ?></span>
                            <?php if ((int)$device['is_active'] === 1): ?>
                                <span class="badge bg-success ms-1"><i class="bi bi-check2-circle me-1"></i>Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-1"><i class="bi bi-slash-circle me-1"></i>Inactive</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-warning mb-3 d-flex align-items-center">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <span>Unassigned</span>
                        </div>
                        <div class="mt-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-orange assign-btn"
                                data-device-id="<?php echo $device['device_id']; ?>"
                                data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                data-serial="<?php echo htmlspecialchars($device['serial_number']); ?>"
                                data-status="<?php echo htmlspecialchars($device['status']); ?>"
                                data-location="Unassigned"
                                title="Assign">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-orange update-device-btn"
                                data-device-id="<?php echo $device['device_id']; ?>"
                                data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                data-device-number="<?php echo htmlspecialchars($device['device_number'] ?? ''); ?>"
                                data-serial="<?php echo htmlspecialchars($device['serial_number']); ?>"
                                data-device-type="<?php echo htmlspecialchars($device['device_type'] ?: ''); ?>"
                                data-status="<?php echo htmlspecialchars($device['status']); ?>"
                                data-is-active="<?php echo (int)$device['is_active']; ?>"
                                data-building-id=""
                                title="Update">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-device-btn"
                                data-device-id="<?php echo $device['device_id']; ?>"
                                data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    $html = ob_get_clean();
    echo json_encode(['ok' => true, 'html' => $html]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>



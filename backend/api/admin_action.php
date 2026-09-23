<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/Security.php';

use App\Services\Security;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Ensure user is admin
if (empty($_SESSION['uid']) || empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
if (!Security::isValidCsrfToken($_POST['csrf'] ?? null, $_SESSION['csrf'] ?? null)) {
    echo json_encode(['ok' => false, 'message' => 'CSRF Token mismatch']);
    exit;
}

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    if ($action === 'approve_order') {
        $st = $pdo->prepare("UPDATE orders SET status = 'approved' WHERE id = ? AND status = 'pending'");
        $st->execute([$id]);
        if ($st->rowCount() > 0) {
            echo json_encode(['ok' => true, 'message' => 'อนุมัติคำสั่งซื้อสำเร็จ']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'ไม่สามารถอนุมัติได้ หรือสถานะเปลี่ยนไปแล้ว']);
        }
    }
    elseif ($action === 'reject_order') {
        $st = $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        $st->execute([$id]);
        if ($st->rowCount() > 0) {
            echo json_encode(['ok' => true, 'message' => 'ปฏิเสธคำสั่งซื้อสำเร็จ']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'ไม่สามารถปฏิเสธได้ หรือสถานะเปลี่ยนไปแล้ว']);
        }
    }
    elseif ($action === 'delete_user') {
        // Prevent deleting oneself
        if ($id === (int)$_SESSION['uid']) {
            echo json_encode(['ok' => false, 'message' => 'ไม่สามารถลบบัญชีของตนเองได้']);
            exit;
        }

        $pdo->beginTransaction();

        // Find user's models to delete files
        $st = $pdo->prepare("SELECT filename, file_gltf, file_glb, file_usdz, file_obj, thumb FROM models WHERE user_id = ?");
        $st->execute([$id]);
        $models = $st->fetchAll();

        foreach ($models as $m) {
            $files = [
                $m['filename'], $m['file_gltf'], $m['file_glb'], $m['file_usdz'], $m['file_obj'], $m['thumb']
            ];
            foreach ($files as $f) {
                if ($f) {
                    $path = dirname(__DIR__, 2) . '/public/uploads/' . $f;
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
        }

        // The DB should ideally have CASCADE constraints, but we can do manual deletes if needed.
        // Assuming CASCADE is set up or we try to delete.
        // We will just try deleting the user. If foreign keys fail, it means we need to manually clean up.
        // Let's manually clean up typical tables to be safe.
        $pdo->prepare("DELETE FROM likes WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM comments WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM collections WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM models WHERE user_id = ?")->execute([$id]);

        $st = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $st->execute([$id]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'message' => 'ลบผู้ใช้งานสำเร็จ']);
    }
    elseif ($action === 'delete_model') {
        $st = $pdo->prepare("SELECT filename, file_gltf, file_glb, file_usdz, file_obj, thumb FROM models WHERE id = ?");
        $st->execute([$id]);
        $m = $st->fetch();

        if ($m) {
            $files = [
                $m['filename'], $m['file_gltf'], $m['file_glb'], $m['file_usdz'], $m['file_obj'], $m['thumb']
            ];
            foreach ($files as $f) {
                if ($f) {
                    $path = __DIR__ . '/uploads/' . $f;
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
            $pdo->prepare("DELETE FROM models WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'message' => 'ลบโมเดลสำเร็จ']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'ไม่พบโมเดลนี้']);
        }
    }
    else {
        echo json_encode(['ok' => false, 'message' => 'Unknown action']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

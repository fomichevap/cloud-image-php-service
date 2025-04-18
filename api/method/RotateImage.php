<?php
// api/method/RotateImage.php

require_once __DIR__ . '/BaseMethod.php';

class RotateImageMethod extends BaseMethod {
    /**
     * Rotates an image by 90° right or left and updates 'updated'.
     * Expects POST JSON: { image_id: <int>, direction: 'R'|'L' }
     */
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['image_id'], $input['direction'])) {
            $this->sendJson(['error' => 'Invalid payload, image_id and direction required'], 400);
        }
        $imageId = (int)$input['image_id'];
        $dir = strtoupper($input['direction']);
        if (!in_array($dir, ['R', 'L'])) {
            $this->sendJson(['error' => 'Direction must be "R" or "L"'], 400);
        }
        $angle = ($dir === 'R') ? 90 : -90;

        $db = new DbWrapper();
        $img = $db->getRow(
            "SELECT i.name, p.folder
               FROM images i
               JOIN partitions p ON p.id = i.partition_id
              WHERE i.id = ?i AND i.removed IS NULL",
            $imageId
        );
        if (!$img) {
            $this->sendJson(['error' => 'Image not found'], 404);
        }

        $filePath = rtrim(UPLOAD_DIR, '/') . '/' . $img['folder'] . '/' . $img['name'];
        if (!file_exists($filePath)) {
            $this->sendJson(['error' => 'File not found on disk'], 500);
        }

        try {
            $im = new Imagick($filePath);
            $im->rotateImage(new ImagickPixel('none'), $angle);
            $im->writeImage($filePath);
            $im->destroy();
        } catch (Exception $e) {
            $this->sendJson(['error' => 'Image processing error: ' . $e->getMessage()], 500);
        }

        $now = time();
        $db->query("UPDATE images SET updated = ?i WHERE id = ?i", $now, $imageId);
        $this->sendJson(['success' => true]);
    }
}

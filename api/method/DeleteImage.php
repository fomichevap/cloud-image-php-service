<?php
// api/method/DeleteImage.php

require_once __DIR__ . '/BaseMethod.php';

class DeleteImageMethod extends BaseMethod {
    /**
     * Marks an image as deleted by setting 'removed' and updating 'updated'.
     * Expects POST JSON: { image_id: <int> }
     */
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['image_id']) || !is_int($input['image_id'])) {
            $this->sendJson(['error' => 'Invalid payload, image_id required'], 400);
        }
        $imageId = $input['image_id'];

        $db = new DbWrapper();
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();
        try {
            // Ensure image exists and not already removed
            $row = $db->getRow("SELECT id FROM images WHERE id = ?i AND removed IS NULL", $imageId);
            if (!$row) {
                throw new Exception('Image not found or already deleted');
            }

            $now = time();
            $db->query(
                "UPDATE images SET removed = ?i, updated = ?i WHERE id = ?i",
                $now, $now, $imageId
            );

            $pdo->commit();
            $this->sendJson(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }
}
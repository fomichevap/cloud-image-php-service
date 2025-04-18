<?php
// api/method/GetTagListForImage.php

require_once __DIR__ . '/BaseMethod.php';

class GetTagListForImageMethod extends BaseMethod {
    /**
     * Returns the list of tags for a specific image by its ID.
     * Expects POST with JSON: { image_id: <int> }
     */
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }

        // Authenticate
        $this->requireAuth();

        // Parse JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['image_id']) || !is_int($input['image_id'])) {
            $this->sendJson(['error' => 'Invalid payload, image_id required'], 400);
        }
        $imageId = $input['image_id'];

        $db = new DbWrapper();
        // Verify image exists and not removed
        $row = $db->getRow("SELECT id FROM images WHERE id = ?i AND removed IS NULL", $imageId);
        if (!$row) {
            $this->sendJson(['error' => 'Image not found'], 404);
        }

        // Fetch tags
        $tags = $db->getAll(
            "SELECT t.title
               FROM tags t
               JOIN image_tag it ON t.id = it.tag_id
              WHERE it.image_id = ?i",
            $imageId
        );
        if ($tags === false) {
            $this->sendJson(['error' => 'Database error'], 500);
        }
        // Extract titles
        $titles = array_map(function($rec) { return $rec['title']; }, $tags);

        $this->sendJson($titles);
    }
}

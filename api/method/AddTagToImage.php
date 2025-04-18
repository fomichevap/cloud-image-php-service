<?php
// api/method/AddTagToImage.php

require_once __DIR__ . '/BaseMethod.php';

class AddTagToImageMethod extends BaseMethod {
    /**
     * Adds a tag to an existing image.
     * Expects POST JSON: { image_id: <int>, tag: <string> }
     */
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }

        // Authenticate
        $this->requireAuth();

        // Parse input JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['image_id']) || !is_int($input['image_id']) || empty($input['tag'])) {
            $this->sendJson(['error' => 'Invalid payload. image_id and tag are required.'], 400);
        }
        $imageId = $input['image_id'];
        $tagTitle = trim($input['tag']);
        if ($tagTitle === '') {
            $this->sendJson(['error' => 'Tag cannot be empty'], 400);
        }

        $db = new DbWrapper();
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();
        try {
            // Verify image exists and not removed
            $img = $db->getRow("SELECT id FROM images WHERE id = ?i AND removed IS NULL", $imageId);
            if (!$img) {
                throw new Exception('Image not found or deleted');
            }

            // Ensure tag exists (insert if new)
            $tag = $db->getRow("SELECT id FROM tags WHERE title = ?s", $tagTitle);
            if (!$tag) {
                $db->query("INSERT INTO tags (title) VALUES (?s)", $tagTitle);
                $tagId = $pdo->lastInsertId();
            } else {
                $tagId = $tag['id'];
            }

            // Insert into image_tag if not already linked
            $exists = $db->getRow(
                "SELECT 1 FROM image_tag WHERE image_id = ?i AND tag_id = ?i",
                $imageId, $tagId
            );
            if (!$exists) {
                $db->query("INSERT INTO image_tag (image_id, tag_id) VALUES (?i, ?i)", $imageId, $tagId);
            }

            $pdo->commit();
            $this->sendJson(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }
}
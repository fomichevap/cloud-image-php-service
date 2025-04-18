<?php
// api/method/GetCounterByTagList.php

require_once __DIR__ . '/BaseMethod.php';

class GetCounterByTagListMethod extends BaseMethod {
    public function process() {
        // Only POST allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }

        // Authenticate
        $this->requireAuth();

        // Parse JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null || !isset($input['tags']) || !is_array($input['tags'])) {
            $this->sendJson(['error' => 'Invalid payload','input'=>$input], 400);
        }
        $tags = array_filter(array_map('trim', $input['tags']), 'strlen');

        $db = new DbWrapper();

        if (empty($tags)) {
            // No tags → count ALL non-removed images
            $row = $db->getRow("SELECT COUNT(*) AS cnt FROM images WHERE removed IS NULL");
            $count = $row ? (int)$row['cnt'] : 0;
        } else {
            // Count images matching ALL given tags
            $tagCount = count($tags);
            $sql = "
                SELECT COUNT(*) AS cnt FROM (
                    SELECT i.id
                      FROM images i
                      JOIN image_tag it ON i.id = it.image_id
                      JOIN tags t       ON t.id = it.tag_id
                     WHERE t.title IN (?a)
                       AND i.removed IS NULL
                  GROUP BY i.id
                    HAVING COUNT(DISTINCT t.title) = ?i
                ) sub
            ";
            $row = $db->getRow($sql, $tags, $tagCount);
            $count = $row ? (int)$row['cnt'] : 0;
        }

        $this->sendJson(['counter' => $count]);
    }
}

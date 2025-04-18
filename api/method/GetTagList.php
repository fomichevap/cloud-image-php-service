<?php
// api/method/getTagList.php


class GetTagListMethod extends BaseMethod {
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }
        $this->requireAuth();
        $dbWrapper = new DbWrapper();
        $result = $dbWrapper->getAll(
            "SELECT t.title, COUNT(it.image_id) as count
             FROM tags t
             LEFT JOIN image_tag it ON t.id = it.tag_id
             LEFT JOIN images im ON im.id = it.image_id
             WHERE im.removed IS NULL or im.removed = 0
             GROUP BY t.id"
        );
        if ($result === false) {
            $this->sendJson(['error' => 'Database error'], 500);
        }
        $this->sendJson($result);
    }
}

<?php
// api/method/createApp.php

require_once __DIR__ . '/BaseMethod.php';

class CreateAppMethod extends BaseMethod {
    public function process() {
        // Authenticate and ensure root_flag = 1
        $access = $this->requireAuth();
        if (empty($access['root_flag'])) {
            $this->sendJson(['error' => 'Insufficient permissions'], 403);
        }

        // Only POST is allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }

        // Expect JSON body: {"title":"My New App"}
        $input = json_decode($_POST['payload'], true);
        if (!$input || empty(trim($input['title']))) {
            $this->sendJson(['error' => 'App title required'], 400);
        }
        $title = trim($input['title']);

        // Generate a new app token
        $appToken = bin2hex(random_bytes(16));
        $created  = time();

        $db     = new DbWrapper();
        $stmt   = $db->query(
            "INSERT INTO app (title, token, created) VALUES (?s, ?s, ?i)",
            $title, $appToken, $created
        );
        if (!$stmt) {
            $this->sendJson(['error' => 'Failed to create app'], 500);
        }

        $appId = Database::getInstance()->getConnection()->lastInsertId();
        $this->sendJson([
            'success' => true,
            'app_id'  => $appId,
            'token'   => $appToken
        ]);
    }
}
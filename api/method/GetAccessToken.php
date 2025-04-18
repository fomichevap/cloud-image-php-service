<?php
// api/method/getAccessToken.php

require_once __DIR__ . '/BaseMethod.php';

class GetAccessTokenMethod extends BaseMethod {
    public function process() {
        // Only POST is allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }

        // Expect JSON body: {"token":"<app_token>"}
        $input = json_decode($_POST['payload'],true);
        if (!$input || empty($input['token'])) {
            $this->sendJson(['error' => 'App token required','input'=>$input], 400);
        }

        $db = new DbWrapper();
        $app = $db->getRow("SELECT id FROM app WHERE token = ?s", $input['token']);
        if (!$app) {
            $this->sendJson(['error' => 'Invalid app token'], 403);
        }

        // Generate a new access token
        $accessToken = bin2hex(random_bytes(32));
        $expiry = time() + TOKEN_EXPIRED;

        $stmt = $db->query(
            "INSERT INTO app_access (app_id, access_token, expired) VALUES (?i, ?s, ?i)",
            $app['id'], $accessToken, $expiry
        );
        if (!$stmt) {
            $this->sendJson(['error' => 'Failed to create access token'], 500);
        }

        $this->sendJson([
            'access_token' => $accessToken,
            'expired'      => $expiry
        ]);
    }
}

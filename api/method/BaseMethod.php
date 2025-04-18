<?php
// api/method/BaseMethod.php

class BaseMethod {
    /**
     * Sends the data in JSON format and terminates the script.
     *
     * @param mixed $data The data to be sent.
     * @param int   $httpCode The HTTP response code (default is 200).
     */
    protected function sendJson($data, $httpCode = 200) {
        header('Content-Type: application/json');
        http_response_code($httpCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Validates an access_token for all protected endpoints.
     * On success, extends its expiry and returns the access row.
     *
     * @return array
     */
    protected function requireAuth() {
        $token = json_decode(file_get_contents('php://input'),true)['access_token'] ?? '';
        if (isset($_POST['access_token'])) {
            $token = $_POST['access_token'];
        }
        if (isset($_GET['access_token'])) {
            $token = $_GET['access_token'];
        }

        if (!$token) {
            $this->sendJson(['error' => 'Access token required'], 401);
        }

        $db = new DbWrapper();
        $access = $db->getRow("SELECT * FROM app_access 
            WHERE access_token = ?s", $token);
        if (!$access) {
            $this->sendJson(['error' => 'Invalid access token - '.$token], 403);
        }
        if ($access['expired'] < time()) {
            $this->sendJson(['error' => 'Access token expired'], 403);
        }

        $app = $db->getRow('SELECT * FROM app WHERE id =?i',$access['app_id']);
        $access['root_flag'] = $app['root_flag'];

        // Extend expiration
        $newExpiry = time() + TOKEN_EXPIRED;
        $db->query("UPDATE app_access SET expired = ?i WHERE id = ?i", $newExpiry, $access['id']);

        return $access;
    }
}

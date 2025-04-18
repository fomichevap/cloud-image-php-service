<?php
// api/method/receive.php

require_once __DIR__ . '/BaseMethod.php';

class ReceiveMethod extends BaseMethod {
    public function process() {
        // Allow only POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['error' => 'Method not allowed'], 405);
        }

        $this->requireAuth();
        
        // Check if a file was uploaded successfully
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->sendJson(['error' => 'File upload error'], 400);
        }
        
        // Check if the payload field is provided (expected JSON string e.g., {"tags": ["tag1", "tag2", ...]})
        if (!isset($_POST['payload'])) {
            $this->sendJson(['error' => 'Payload not provided'], 400);
        }
        $payload = json_decode($_POST['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendJson(['error' => 'Invalid JSON in payload'], 400);
        }
        if (!isset($payload['tags']) || !is_array($payload['tags'])) {
            $this->sendJson(['error' => 'Tags not provided or invalid'], 400);
        }
        
        // Use the partition limit (e.g., 512 files per partition)
        if (!defined('PARTITION_LIMIT')) {
            define('PARTITION_LIMIT', 512);
        }
        
        $dbWrapper = new DbWrapper();
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            // Compute the hash of the uploaded file content
            $fileTmpPath = $_FILES['file']['tmp_name'];
            $hash = md5_file($fileTmpPath);
            if ($hash === false) {
                throw new Exception("Failed to compute file hash");
            }
            
            // Check if an image with this hash already exists
            $existing = $dbWrapper->getRow("SELECT id FROM images WHERE hash = ?s", $hash);
            if ($existing) {
                throw new Exception("This image has already been uploaded");
            }
            
            // Determine the current partition: select the most recent partition if it contains fewer than PARTITION_LIMIT files.
            $partition = $dbWrapper->getRow("SELECT * FROM partitions ORDER BY id DESC LIMIT 1");
            $currentPartitionId = null;
            $partitionFolder = '';
            if ($partition) {
                $countRow = $dbWrapper->getRow("SELECT COUNT(*) as cnt FROM images WHERE partition_id = ?i", $partition['id']);
                if ($countRow && $countRow['cnt'] < PARTITION_LIMIT) {
                    $currentPartitionId = $partition['id'];
                    $partitionFolder = $partition['folder'];
                }
            }
            // If no partition is found or the limit is reached, create a new one.
            if (!$currentPartitionId) {
                $db->exec("INSERT INTO partitions DEFAULT VALUES");
                $currentPartitionId = $db->lastInsertId();
                $newPartition = $dbWrapper->getRow("SELECT * FROM partitions WHERE id = ?i", $currentPartitionId);
                if (!$newPartition) {
                    throw new Exception('Failed to create new partition');
                }
                $partitionFolder = $newPartition['folder'];
                // Create a directory for the new partition
                $newDir = rtrim(UPLOAD_DIR, '/') . '/' . $partitionFolder;
                if (!file_exists($newDir) && !mkdir($newDir, 0777, true)) {
                    throw new Exception('Failed to create directory for partition');
                }
            }
            
            // Process the uploaded file
            $originalName = $_FILES['file']['name'];
            
            // Determine the MIME type of the file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);
            
            // Generate a unique filename (UUID) – always save as .jpg
            $filename = strtolower(bin2hex(random_bytes(16)));
            $filenameWithExt = $filename . '.jpg';
            $destination = rtrim(UPLOAD_DIR, '/') . '/' . $partitionFolder . '/' . $filenameWithExt;
            
            // If the file is a PNG, convert it to JPEG; if it's already JPEG, simply move it.
            if ($mimeType === 'image/png') {
                $image = imagecreatefrompng($fileTmpPath);
                if (!$image) {
                    throw new Exception("Failed to process PNG file");
                }
                if (!imagejpeg($image, $destination, JPEG_QUALITY)) {
                    imagedestroy($image);
                    throw new Exception("Failed to save JPEG file");
                }
                imagedestroy($image);
            } elseif ($mimeType === 'image/jpeg') {
                if (!move_uploaded_file($fileTmpPath, $destination)) {
                    throw new Exception('Failed to move uploaded file');
                }
            } else {
                throw new Exception("Unsupported file format. Only PNG and JPEG allowed.");
            }
            
            // Insert a record into the images table with partition_id, name, title, hash, and creation timestamp.
            $created = time();
            $query = "INSERT INTO images (partition_id, name, title, hash, created) VALUES (?i, ?s, ?s, ?s, ?i)";
            $stmt = $dbWrapper->query($query, $currentPartitionId, $filenameWithExt, $originalName, $hash, $created);
            if (!$stmt) {
                throw new Exception("Failed to insert image record");
            }
            $imageId = $db->lastInsertId();

            $tagger = new ImageTagger();
            $autoTags = $tagger->getTags($destination);

            // Объединяем с пользовательскими тегами:
            $userTags = $payload['tags'];
            $allTags = array_unique(array_merge($userTags, $autoTags));
            
            // Process tags: check for existence, insert if necessary, and link them to the image.
            foreach ($allTags as $tagTitle) {
                $tagTitle = trim($tagTitle);
                if (empty($tagTitle)) {
                    continue;
                }
                $tag = $dbWrapper->getRow("SELECT * FROM tags WHERE title = ?s", $tagTitle);
                if (!$tag) {
                    $stmtTag = $dbWrapper->query("INSERT INTO tags (title) VALUES (?s)", $tagTitle);
                    if (!$stmtTag) {
                        throw new Exception("Failed to insert tag: {$tagTitle}");
                    }
                    $tagId = $db->lastInsertId();
                } else {
                    $tagId = $tag['id'];
                }
                $dbWrapper->query("INSERT INTO image_tag (image_id, tag_id) VALUES (?i, ?i)", $imageId, $tagId);
            }
            
            $db->commit();
            $this->sendJson(['success' => true, 'image_id' => $imageId]);
        } catch (Exception $e) {
            $db->rollBack();
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }
}

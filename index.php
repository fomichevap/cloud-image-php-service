<?php
/**
 * index.php
 *
 * Main entry point for image requests.
 *
 * Supports:
 *  - size: "WIDTHxHEIGHT" or "WIDTH" (square) or "original" (no resize)
 *  - tags: zero or more segments after size
 *  - index: numeric segment at end selects that image
 *  - random mode: last segment "random" or "random_N" picks a cached random image
 *
 * Caches random choice per client (IP+UA) for USER_SESSION_LONG seconds.
 */

require_once 'includes.php';  // config.php, autoloader, etc.

if (!defined('USER_SESSION_LONG')) {
    define('USER_SESSION_LONG', 3600); // e.g. 1 hour
}

// 1) Parse "path" parameter
if (empty($_GET['path'])) {
    http_response_code(400);
    exit("Parameter 'path' is missing");
}
$path = trim($_GET['path'], '/');
$segments = explode('/', $path);

// 2) Size / original
$dim = array_shift($segments);
$originalMode = false;
if (strtolower($dim) === 'original') {
    $originalMode = true;
} elseif (stripos($dim, 'x') !== false) {
    list($width, $height) = array_map('intval', explode('x', $dim, 2));
} else {
    $width = (int)$dim;
    $height = $width;
}

// 3) Detect random mode
$randomMode = false;
if (isset($segments[count($segments)-1]) && preg_match('/^random(_\d+)?$/i', $segments[count($segments)-1])) {
    $randomMode = true;
    array_pop($segments);
}

// 4) Determine explicit index (if not random)
$index = 1;
if (!$randomMode && !empty($segments) && ctype_digit(end($segments))) {
    $index = max(1, (int) array_pop($segments));
}

// 5) The remaining segments are tags
$tags = $segments;

// 6) Fetch matching images
$db = new DbWrapper();
if (empty($tags)) {
    $images = $db->getAll("SELECT * FROM images WHERE removed IS NULL ORDER BY created ASC");
} else {
    $images = $db->getAll(
        "SELECT i.* FROM images i
         JOIN image_tag it ON i.id = it.image_id
         JOIN tags t ON t.id = it.tag_id
         WHERE t.title IN (?a)
         GROUP BY i.id
         HAVING COUNT(DISTINCT t.title) = ?i
         ORDER BY i.created ASC",
         $tags, count($tags)
    );
}

// If no images found, fallback
if (empty($images)) {
    $source = __DIR__ . '/storage/noimage.jpg';
} else {
    // 7) Determine effective index
    $total = count($images);

    if ($randomMode) {
        // Build a session key: IP + UA + size + tags
        $sessionKey = md5($_SERVER['REMOTE_ADDR'] . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $dim . '|' . implode(',', $tags));

        // Use a dedicated SQLite table random_cache(session_key, random_index, expires)
        // CREATE TABLE IF NOT EXISTS random_cache (
        //   session_key TEXT PRIMARY KEY,
        //   random_index INTEGER,
        //   expires INTEGER
        // );
        $row = $db->getRow(
            "SELECT random_index, expires FROM random_cache WHERE session_key = ?s",
            $sessionKey
        );
        if ($row && $row['expires'] >= time()) {
            $effectiveIndex = $row['random_index'];
        } else {
            $effectiveIndex = rand(1, $total);
            $expires = time() + USER_SESSION_LONG;
            if ($row) {
                $db->query(
                    "UPDATE random_cache SET random_index = ?i, expires = ?i WHERE session_key = ?s",
                    $effectiveIndex, $expires, $sessionKey
                );
            } else {
                $db->query(
                    "INSERT INTO random_cache (session_key, random_index, expires) VALUES (?s, ?i, ?i)",
                    $sessionKey, $effectiveIndex, $expires
                );
            }
        }
    } else {
        // Normal rotation: wrap-around
        $effectiveIndex = (($index - 1) % $total) + 1;
    }

    $sel = $images[$effectiveIndex - 1];
    $part = $db->getRow("SELECT folder FROM partitions WHERE id = ?i", $sel['partition_id']);
    $source = rtrim(UPLOAD_DIR, '/') . '/' . $part['folder'] . '/' . $sel['name'];
    if (!file_exists($source)) {
        $source = __DIR__ . '/storage/noimage.jpg';
    }
}

// 8) Serve image (original or resized) with caching
define('CACHE_DIR', CACHE_DIR ?? (__DIR__ . '/cache'));
if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0777, true);

// Build cache key: include updated timestamp so rotated images bust cache
$updated = filemtime($source);
$cacheKeyName = md5($source . '_' . ($originalMode ? 'orig' : "{$width}x{$height}") . "_u{$updated}") . '.jpg';
$cacheFile = CACHE_DIR . '/' . $cacheKeyName;

if (!$originalMode && !file_exists($cacheFile)) {
    // Create resized/cropped cache
    try {
        $img = new Imagick($source);
        $img->cropThumbnailImage($width, $height);
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(JPEG_QUALITY);
        file_put_contents($cacheFile, $img->getImageBlob());
        $img->destroy();
    } catch (Exception $e) {
        http_response_code(500);
        exit("Image processing error: " . $e->getMessage());
    }
}

// Decide which file to send
$toSend = $originalMode ? $source : $cacheFile;

// HTTP caching headers
$lm = filemtime($toSend);
$etag = md5_file($toSend);
header("Content-Type: image/jpeg");
header("Content-Length: " . filesize($toSend));
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lm) . " GMT");
header("ETag: \"$etag\"");
header("Cache-Control: public, max-age=" . CACHE_LIFETIME);
if (
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lm)
 || (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === "\"$etag\"")
) {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

readfile($toSend);

<?php
/**
 * Configuration file for the image delivery microservice.
 * This file defines the global constants used in the application.
 */

// Base directory of the project.
define('BASE_DIR', __DIR__);

// Access‑token lifetime (in seconds)
define('TOKEN_EXPIRED', 3600);

// Directory where the original images are uploaded.
define('UPLOAD_DIR', BASE_DIR . '/uploads/');

// Directory for cached images.
define('CACHE_DIR', BASE_DIR . '/cache/');

// SQLite database file for storing image metadata and tag associations.
// Using built-in SQLite simplifies deployment without requiring a separate database server.
define('DB_FILE', BASE_DIR . '/data/dev.db');

// Base URL of the service (fill in if needed to generate absolute URLs).
define('BASE_URL', '');  #fill it!

// Cache lifetime (in seconds) for HTTP headers (e.g., 86400 seconds = 1 day).
define('CACHE_LIFETIME', 86400);

// JPEG quality for resizing (value from 0 to 100).
define('JPEG_QUALITY', 90);

// Partition size: maximum number of images per directory/partition.
define('PARTITION_SIZE', 512);

// Path to the "no image" file that is displayed when no matching image is found.
define('NOIMAGE', BASE_DIR . '/storage/noimage.jpg');

// Debug mode: true for development, false for production.
define('DEBUG', true);

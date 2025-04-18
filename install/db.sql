-- Table for storing partition (directory) information for images.
CREATE TABLE IF NOT EXISTS partitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    folder TEXT NOT NULL DEFAULT (lower(hex(randomblob(16)))) UNIQUE
);

-- Table for storing image metadata.
CREATE TABLE IF NOT EXISTS images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    partition_id INTEGER NOT NULL,       -- Reference to the partition (directory)
    name TEXT NOT NULL DEFAULT (lower(hex(randomblob(16)))) UNIQUE,  -- Unique file name (UUID)
    title VARCHAR(64),                   -- Description or title of the image
    hash TEXT UNIQUE,                    -- Hash of the file content; must be unique
    created INTEGER NOT NULL,            -- UNIX timestamp of the upload time
    removed INTEGER DEFAULT NULL,        -- UNIX timestamp of deletion (if applicable)
    FOREIGN KEY (partition_id) REFERENCES partitions(id)
);

-- Create a unique index on the "hash" column to enforce uniqueness.
CREATE UNIQUE INDEX idx_images_hash ON images(hash);

-- Table for storing unique tags.
CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(64) NOT NULL UNIQUE
);

-- Table for the many-to-many relationship between images and tags.
CREATE TABLE IF NOT EXISTS image_tag (
    image_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (image_id, tag_id),
    FOREIGN KEY (image_id) REFERENCES images(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
);

-- Table for storing applications
CREATE TABLE IF NOT EXISTS app (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    title   VARCHAR(64) NOT NULL,
    token   VARCHAR(64) NOT NULL UNIQUE,
    created INTEGER NOT NULL
);

-- Table for storing per‑app access tokens
CREATE TABLE IF NOT EXISTS app_access (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    app_id        INTEGER NOT NULL,
    access_token  VARCHAR(64) NOT NULL UNIQUE,
    expired       INTEGER NOT NULL,
    root_flag     INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (app_id) REFERENCES app(id)
);


CREATE TABLE IF NOT EXISTS random_cache (
  session_key TEXT PRIMARY KEY,
  random_index INTEGER NOT NULL,
  expires INTEGER NOT NULL
);
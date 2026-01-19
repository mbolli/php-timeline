-- Timeline Database Schema

-- Groups (tracks): Phones, Hardware, Cars, etc.
CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    icon TEXT DEFAULT '📁',
    color TEXT DEFAULT '#3498db',
    sort_order INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Timeline items
CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    start_date TEXT NOT NULL,  -- YYYY-MM format
    end_date TEXT,             -- NULL = ongoing
    color TEXT,
    metadata TEXT,             -- JSON for extra data
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_items_group_id ON items(group_id);
CREATE INDEX IF NOT EXISTS idx_items_start_date ON items(start_date);
CREATE INDEX IF NOT EXISTS idx_groups_sort_order ON groups(sort_order);

-- Trigger to update updated_at on items
CREATE TRIGGER IF NOT EXISTS update_items_timestamp 
AFTER UPDATE ON items
BEGIN
    UPDATE items SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Trigger to update updated_at on groups
CREATE TRIGGER IF NOT EXISTS update_groups_timestamp 
AFTER UPDATE ON groups
BEGIN
    UPDATE groups SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

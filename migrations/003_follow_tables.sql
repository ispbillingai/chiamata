-- Waiters can follow single tables too, besides whole zones (JSON list of venue_tables.id).
ALTER TABLE users ADD COLUMN table_ids TEXT NULL AFTER zones;

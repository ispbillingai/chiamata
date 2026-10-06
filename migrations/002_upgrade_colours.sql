-- Venues default to the Upgrade blue (palette of the logo); the old teal default follows.
ALTER TABLE venues ALTER color SET DEFAULT '#0786c4';
UPDATE venues SET color = '#0786c4' WHERE color = '#0f766e';

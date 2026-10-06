-- Unanswered calls: reminder to the same waiters, then an alert to all the venue's staff.
ALTER TABLE venues
    ADD COLUMN remind_after SMALLINT NOT NULL DEFAULT 60 AFTER bill_ask_payment,
    ADD COLUMN escalate_after SMALLINT NOT NULL DEFAULT 120 AFTER remind_after;
ALTER TABLE calls
    ADD COLUMN reminded_at DATETIME NULL AFTER last_call_at,
    ADD COLUMN escalated_at DATETIME NULL AFTER reminded_at,
    ADD COLUMN alerts INT NOT NULL DEFAULT 0 AFTER escalated_at;

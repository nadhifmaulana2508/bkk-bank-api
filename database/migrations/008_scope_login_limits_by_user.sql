-- Login failures must not lock every admin behind the same office/proxy IP.
-- Clear transient counters during this one-time migration to release current locks.
DELETE FROM sso_login_attempts;

ALTER TABLE sso_login_attempts
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (ip_address, username);


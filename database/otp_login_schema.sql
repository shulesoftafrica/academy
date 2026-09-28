-- ===========================================================================
-- OTP / SSO login schema requirements for the `academy` schema.
--
-- Run this on the LIVE database. It is idempotent (safe to run more than once):
-- every statement uses IF NOT EXISTS, so it only adds what is missing.
--
-- Symptom it fixes: OTP codes are delivered and can be entered, but the app
-- errors immediately AFTER submitting the code. Cause: verify_otp calls
-- User_model::provision_community_user(), which writes sid / community_source /
-- is_career_person to academy.users — columns that exist in dev but were never
-- added to the live academy.users table when the login code was deployed.
--
-- NOTE: assumes the Academy app's schema is named `academy` (see
-- application/config/database.php -> 'schema'). If your live schema has a
-- different name, replace `academy.` below (or run: SET search_path TO <schema>;).
-- ===========================================================================

-- Shadow-account columns written by provision_community_user() ---------------
ALTER TABLE academy.users ADD COLUMN IF NOT EXISTS sid              integer;
ALTER TABLE academy.users ADD COLUMN IF NOT EXISTS community_source varchar(120);
ALTER TABLE academy.users ADD COLUMN IF NOT EXISTS is_career_person integer DEFAULT 0;

-- One Academy account per durable ShuleSoft sid (partial index: many NULLs ok)
CREATE UNIQUE INDEX IF NOT EXISTS users_sid_unique
    ON academy.users (sid) WHERE sid IS NOT NULL;

-- OTP code store used by Otp_service (usually already present if codes send,
-- but created here for a clean/fresh environment) ----------------------------
CREATE TABLE IF NOT EXISTS academy.otps (
    id             serial PRIMARY KEY,
    phone_or_email varchar(191) NOT NULL DEFAULT '',
    code           varchar(12)  NOT NULL DEFAULT '',
    purpose        varchar(40)  NOT NULL DEFAULT 'login',
    channel        varchar(20)  NOT NULL DEFAULT '',
    expires_at     timestamp,
    verified_at    timestamp,
    attempts       smallint     NOT NULL DEFAULT 0,
    created_at     timestamp    DEFAULT now()
);
CREATE INDEX IF NOT EXISTS otps_lookup ON academy.otps (phone_or_email, created_at);

-- Verify (should return 3 rows: sid, community_source, is_career_person) ------
SELECT column_name
FROM information_schema.columns
WHERE table_schema = 'academy' AND table_name = 'users'
  AND column_name IN ('sid', 'community_source', 'is_career_person');

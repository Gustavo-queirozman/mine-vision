ALTER TABLE reports ADD COLUMN public_token TEXT;
CREATE UNIQUE INDEX reports_public_token_idx ON reports(public_token);

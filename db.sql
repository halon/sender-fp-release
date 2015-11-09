CREATE TABLE release (
	id          BIGSERIAL PRIMARY KEY,
	ts          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	ip          VARCHAR(100),
	comment     TEXT,
	found       INT DEFAULT -1,
	node        VARCHAR(100),
	msgid       VARCHAR(300),
	msgfrom     VARCHAR(300),
	msgsubject  TEXT,
	msgrpdrefid TEXT,
	msgrpdscore INT
);
CREATE INDEX msgid_idx ON release (msgid,node);
CREATE TABLE release_rcpt (
	id          BIGSERIAL PRIMARY KEY,
	release_id  BIGINT REFERENCES release (id),
	status      INT DEFAULT 0,
	queueid     BIGINT,
	msgto       VARCHAR(300),
	token       TEXT
);
CREATE INDEX queueid_idx ON release_rcpt (queueid);

CREATE TABLE release_sender (
	id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE PRIMARY KEY,
	ts          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	ip          VARCHAR(100),
	comment     TEXT,
	found       INT DEFAULT -1,
	node        VARCHAR(100),
	msgid       VARCHAR(36),
	msgfrom     VARCHAR(300),
	msgsubject  TEXT,
	msgrpdrefid TEXT,
	msgrpdscore INT
);
CREATE INDEX msgid_idx ON release_sender (msgid,node);
CREATE TABLE release_rcpt (
	id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE PRIMARY KEY,
	release_id  BIGINT REFERENCES release_sender (id),
	status      INT DEFAULT 0,
	queueid     BIGINT,
	msgto       VARCHAR(300),
	token       TEXT
);
CREATE INDEX queueid_idx ON release_rcpt (queueid);

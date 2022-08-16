#!/bin/sh

sqlite3 db.sqlite << EOF
CREATE TABLE IF NOT EXISTS users (
	uid INTEGER PRIMARY KEY,
	user STRING NOT NULL UNIQUE,
	pass STRING NOT NULL,
	auth STRING NOT NULL,
	creat INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS files (
	uid INTEGER SECONDARY KEY,
	file STRING NOT NULL,
	content STRING NOT NULL,
	creat INTEGER NOT NULL,
	UNIQUE(uid, file) ON CONFLICT ABORT,
	FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
);
INSERT OR IGNORE INTO users (user, pass, auth, creat)
	VALUES ("betatest", "betatest", "betatest", strftime('%s', 'now'));
INSERT OR IGNORE INTO files (uid, file, content, creat)
	VALUES (1, "challenge-hint", "try harder 🤡", strftime('%s', 'now'));
EOF
chmod 777 . files db.sqlite

/etc/init.d/cron start
/etc/init.d/nginx start
/etc/init.d/php7.4-fpm start

tail -f /var/log/nginx/error.log

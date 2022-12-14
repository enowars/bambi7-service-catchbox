CREATE TABLE IF NOT EXISTS users (
	uid INTEGER PRIMARY KEY,
	user STRING NOT NULL UNIQUE,
	pass STRING NOT NULL,
	auth STRING NOT NULL,
	creat INTEGER NOT NULL,
	delet INTEGER DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS files (
	uid INTEGER SECONDARY KEY,
	file STRING NOT NULL,
	dir STRING NOT NULL,
	creat INTEGER NOT NULL,
	UNIQUE(uid, file) ON CONFLICT ABORT,
	FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reports (
	uid INTEGER SECONDARY KEY,
	file STRING NOT NULL,
	creat INTEGER NOT NULL,
	FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS users_delete_by
AFTER INSERT ON users
BEGIN
    UPDATE users SET delet = strftime("%s", "now") + 780 WHERE delet IS NULL;
END;

INSERT OR IGNORE INTO users (uid, user, pass, auth, creat)
	VALUES (1, "catlover1998", "ILOVECATS123",
		hex(randomblob(16)), 9999999999);

INSERT OR IGNORE INTO files (uid, file, dir, creat)
	VALUES (1, "cat.jpg", "54b8617eca0e54c7d3c8e6732c6b687a", 9999999999);
INSERT OR IGNORE INTO files (uid, file, dir, creat)
	VALUES (1, "cat2.jpg", "4307ab44204de40235bad8c66cce0ae9", 9999999999);
INSERT OR IGNORE INTO files (uid, file, dir, creat)
	VALUES (1, "cat3.jpg", "6ed554592bbf418df53acb9317644d58", 9999999999);
INSERT OR IGNORE INTO files (uid, file, dir, creat)
	VALUES (1, "cat4.jpg", "bdd921efdb71adfc8c097a7ce0718eb3", 9999999999);

INSERT OR IGNORE INTO reports (uid, file, creat)
	VALUES (1, "2a4db6742253d94be4cf0e56c971fced", 9999999999);

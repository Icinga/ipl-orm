CREATE TABLE user (
  id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  username VARCHAR NOT NULL,
  password VARCHAR NOT NULL,
  ctime TIMESTAMP NOT NULL,
  mtime TIMESTAMP NOT NULL,
  UNIQUE(username)
);

CREATE TABLE profile (
  id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  given_name VARCHAR NOT NULL,
  surname VARCHAR NOT NULL,
  ctime TIMESTAMP NOT NULL,
  mtime TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES user (id)
);

INSERT INTO user (id, username, password, ctime, mtime) VALUES
(1, 'admin', 'admin', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'guest', 'guest', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO profile (id, user_id, given_name, surname, ctime, mtime) VALUES
(1, 1, 'John', 'Doe', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 2, 'Richard', 'Roe', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- Creates the replication user that mysql-replica connects with.
-- Runs once via docker-entrypoint-initdb.d on first boot of mysql-primary.
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED WITH mysql_native_password BY 'repl_pass';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
FLUSH PRIVILEGES;

#!/bin/bash
# Points mysql-replica at mysql-primary and starts replication.
# Runs once via docker-entrypoint-initdb.d on first boot of mysql-replica
# (GTID-based, so no binlog file/position bookkeeping is needed).
set -e

until mysql -h mysql-primary -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1" >/dev/null 2>&1; do
  echo "replica-init: waiting for mysql-primary to accept connections..."
  sleep 2
done

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
  CHANGE REPLICATION SOURCE TO
    SOURCE_HOST='mysql-primary',
    SOURCE_PORT=3306,
    SOURCE_USER='repl',
    SOURCE_PASSWORD='repl_pass',
    SOURCE_AUTO_POSITION=1;
  START REPLICA;
EOSQL

echo "replica-init: replication started."

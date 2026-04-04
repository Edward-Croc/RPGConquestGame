#!/bin/bash
# Replace {prefix} placeholder with game2_ and execute schema
sed 's/{prefix}/game2_/g' /var/sql/setupBDD.sql | mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"

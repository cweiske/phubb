#!/bin/sh
cd "$(dirname "$0")"
mysqldump --no-data -h127.0.0.1 -uphubb -pphubb phubb\
 | sed 's/ AUTO_INCREMENT=[0-9]*\b//'\
 > ../data/schema.sql

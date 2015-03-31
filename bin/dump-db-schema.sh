#!/bin/sh
cd "$(dirname "$0")"
mysqldump --no-data -hlocalhost -uphubb -pphubb phubb\
 | sed 's/ AUTO_INCREMENT=[0-9]*\b//'\
 > ../data/schema.sql

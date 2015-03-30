#!/bin/sh
cd "$(dirname "$0")"
mysqldump --no-data -hlocalhost -uphubb -pphubb phubb > ../data/schema.sql

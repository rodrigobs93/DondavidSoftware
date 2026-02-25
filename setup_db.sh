#!/bin/bash
PG_BIN="/c/Program Files/PostgreSQL/16/bin"
export PATH="$PG_BIN:$PATH"
export PGPASSWORD="postgres"

# Create user and database
psql -U postgres -c "CREATE USER don_david_user WITH PASSWORD 'don_david_pass' CREATEDB;" 2>&1 || true
psql -U postgres -c "CREATE DATABASE don_david OWNER don_david_user;" 2>&1 || true
psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE don_david TO don_david_user;" 2>&1 || true
echo "DB setup done"

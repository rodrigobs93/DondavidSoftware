#!/bin/bash
PG_BIN="/c/Program Files/PostgreSQL/16/bin"
export PATH="$PG_BIN:$PATH"
export PGPASSWORD="postgres"

# Create user and database
psql -U postgres -c "CREATE USER mi_pos_user WITH PASSWORD 'mi_pos_pass' CREATEDB;" 2>&1 || true
psql -U postgres -c "CREATE DATABASE mi_pos OWNER mi_pos_user;" 2>&1 || true
psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE mi_pos TO mi_pos_user;" 2>&1 || true
echo "DB setup done"

#!/bin/bash

# Genera la estructura del proyecto
find . -not -path "*/\.*" \
  -not -path "*/node_modules/*" \
  -not -path "*/vendor/*" \
  -not -path "*/database/backups/*" \
  -not -path "*/database/dumps/*" \
  -not -path "*/database/sqlite/*" \
  -not -path "*/database/sqlitedocker/*" \
  -not -path "*/docker/*" \
  -not -path "*/dumps/*" \
  -not -path "*/ssl/*" \
  -not -path "*/remote_database/*" \
  -not -path "*/packages/*" \
  -not -path "*/storage/*" \
  -not -path "*/notes/*" \
  -type f | sort > instructions/structure.txt

echo "Estructura del proyecto generada en instructions/structure.txt"

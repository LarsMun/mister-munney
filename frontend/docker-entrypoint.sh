#!/bin/sh
# Fix permissions for node_modules binaries (WSL2 issue)
chmod +x /app/node_modules/.bin/* 2>/dev/null || true

# Execute the command passed to the script
exec "$@"

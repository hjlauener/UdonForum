#!/usr/bin/env bash
# Doppelklick im Finder: neusten Stand von GitHub holen und Plugin im lokalen ILIAS neu laden.
cd "$(dirname "$0")/.." || exit 1
./dev/reload.sh --pull
status=$?
echo
read -r -n 1 -p "Taste drücken zum Schliessen …"
exit $status

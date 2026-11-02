#!/usr/bin/env bash
#
# Installiert den Terminal-Befehl "udf" (einmalig ausführen):
#
#   ./dev/install-command.sh
#
# Danach im Terminal, egal in welchem Ordner:
#   udf              neusten Stand von GitHub holen + Plugin im Docker-ILIAS neu laden
#   udf install      Plugin das erste Mal installieren
#   udf --watch      bei jeder Änderung automatisch neu laden
#   udf status       gefundenen Container anzeigen
#   udf logs | shell Container-Logs / Shell im Container
#
# Der Befehl wird nach ~/.local/bin/udf geschrieben; ~/.local/bin wird bei
# Bedarf in ~/.zshrc (und ~/.bash_profile) zum PATH hinzugefügt.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN_DIR="${HOME:?}/.local/bin"
CMD_NAME="udf"
CMD_PATH="$BIN_DIR/$CMD_NAME"
PATH_LINE='export PATH="$HOME/.local/bin:$PATH"'

mkdir -p "$BIN_DIR"

cat > "$CMD_PATH" <<EOF
#!/usr/bin/env bash
# UdonForum – neu laden (installiert von $REPO_DIR/dev/install-command.sh)
REPO_DIR="$REPO_DIR"
if [ ! -x "\$REPO_DIR/dev/reload.sh" ]; then
    echo "UdonForum-Repository nicht gefunden: \$REPO_DIR" >&2
    echo "Nach dem Verschieben des Ordners bitte dev/install-command.sh erneut ausführen." >&2
    exit 1
fi
if [ \$# -eq 0 ]; then
    exec "\$REPO_DIR/dev/reload.sh" --pull
fi
exec "\$REPO_DIR/dev/reload.sh" "\$@"
EOF
chmod +x "$CMD_PATH"
chmod +x "$REPO_DIR/dev/reload.sh" "$REPO_DIR/dev/UdonForum neu laden.command" 2>/dev/null || true

added=""
for rc in "$HOME/.zshrc" "$HOME/.bash_profile"; do
    # .bash_profile nur anpassen, wenn es schon existiert (macOS nutzt zsh)
    if [ "$rc" = "$HOME/.bash_profile" ] && [ ! -f "$rc" ]; then
        continue
    fi
    if ! grep -qsF '.local/bin' "$rc"; then
        printf '\n# UdonForum-Befehl "udf"\n%s\n' "$PATH_LINE" >> "$rc"
        added="$added $rc"
    fi
done

echo "✔ Befehl installiert: $CMD_PATH"
echo "  zeigt auf: $REPO_DIR"
if [ -n "$added" ]; then
    echo "✔ PATH ergänzt in:$added"
    echo
    echo "Jetzt einmal ein neues Terminal-Fenster öffnen (oder: source ~/.zshrc)."
fi
echo
echo "Danach:  udf install   (nur beim ersten Mal)"
echo "         udf           (neusten Stand holen und neu laden)"

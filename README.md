# paarfragen

Eine PWA mit Fragen für Paare. Geplant: zuerst als Progressive Web App,
später optional als native Cross-Platform-App (gleiches Frontend, in
einem nativen Container).

## Layout

```
api/         PHP-Backend auf Tempest, hexagonal (Domain / Application / Infrastructure)
frontend/    Vue 3 + Vite, als PWA gebaut (vite-plugin-pwa)
specs/       Specs; STATUS.md ist der Einstiegspunkt für jede Session
script/      Alle Toolchain-Kommandos — siehe script/help
```

Noch kein Feature-Code — reines Grundgerüst. Nächster Schritt und offene
Entscheidungen: [`specs/STATUS.md`](specs/STATUS.md).

## Los geht's

```bash
script/setup   # Dependencies installieren (api/ + frontend/)
script/qa      # Tests + Typecheck + Build — der Gate vor jedem Commit
script/help    # Alle Kommandos mit Beschreibung + Side-effects
```

`api/` braucht PHP **^8.5** (Tempest) — läuft dafür in einem Container
(`docker-compose.yml` / `.devcontainer/`), nicht auf dem Host. Du brauchst
also **Docker**, nicht PHP 8.5 lokal installiert. Dasselbe Image ist auch
der lokale VS-Code-Dev-Container fürs ganze Repo. Details:
`api/README.md`, `specs/STATUS.md`.

Agent-Arbeitsregeln: [`CLAUDE.md`](CLAUDE.md).

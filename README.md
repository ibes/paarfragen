# paarfragen

Eine PWA mit Fragen für Paare. Geplant: zuerst als Progressive Web App,
später optional als native Cross-Platform-App (gleiches Frontend, in
einem nativen Container).

## Layout

```
api/         PHP-Backend, hexagonal (Domain / Application / Infrastructure)
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

Agent-Arbeitsregeln: [`CLAUDE.md`](CLAUDE.md).

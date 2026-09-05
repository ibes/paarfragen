# paarfragen — API

Hexagonal PHP core. No web framework wired yet — that choice is open, see
`../specs/STATUS.md`.

```
src/
  Domain/          framework-free entities, value objects, domain services
  Application/     use cases orchestrating the domain (ports in, ports out)
  Infrastructure/  adapters: HTTP, persistence, everything framework-specific
```

Domain and Application stay framework-free; only Infrastructure may depend
on a framework, a database driver, or the outside world.

## Toolchain

Via `../script/*`, not composer directly — see `../script/help`.

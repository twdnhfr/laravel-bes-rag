# Contributing

Thanks for considering a contribution to **Laravel BES-RAG**.

## Getting started

```bash
git clone https://github.com/twdnhfr/laravel-bes-rag
cd laravel-bes-rag
composer install
```

There is no app to serve — the package is exercised entirely through its tests,
which run fully offline through the bundled fakes (no HTTP, no provider keys).

## Before you open a PR

Run the full check suite — CI runs the same:

```bash
composer test       # Pest test suite (fast, fully offline)
composer analyse    # PHPStan (level 5)
vendor/bin/pint     # code style (Laravel preset)
```

Please:

- Add or update tests for any behaviour change. Drive the LLM/embedder seams with
  `Testing\FakeLlm` / `Testing\FakeEmbedder` and retrieval with
  `Retrieval\ArrayRetriever`; `tests/Fixtures/TeslaFixture.php` is the shared
  multi-hop scenario.
- Keep PHPStan and Pint green.
- Match the surrounding code style and the conventions in [`CLAUDE.md`](CLAUDE.md).

## Public API & scope

This is a public, MIT-licensed library consumed by unknown third-party Laravel
apps, so:

- The public surface is a stability contract: `Facades\BesRag`, `PendingSearch`,
  `BesResult`, the `Contracts\*` interfaces, the `Testing\*` fakes and the config
  keys. Favour additive changes and call out anything breaking.
- No hard-coded provider/model assumptions — config flows through
  `config/bes-rag.php` and the fluent builder.
- **Never evaluate generated code.** Verifiers are registered, declarative classes,
  never `eval`. There is an arch test enforcing this — don't work around it.

## Releasing

The changelog is **release-driven** — don't hand-edit `CHANGELOG.md` beyond the
current unreleased notes. To cut a release:

1. Tag the commit (`vX.Y.Z`) and publish a **GitHub Release** for that tag.
2. Write the release body in changelog style (e.g. `### Added` / `### Fixed`
   sections) — this body *is* the changelog entry.

On publish, the [`Update Changelog`](.github/workflows/update-changelog.yml)
workflow inserts the release body under a `vX.Y.Z` heading in `CHANGELOG.md` and
commits it to `main`. Publishing the release also triggers the Packagist
auto-update webhook, so the new version appears on Packagist automatically.

## Reporting bugs

Open an issue with a minimal reproduction (a failing test is ideal). For security
issues, see [SECURITY.md](SECURITY.md) — do **not** open a public issue.

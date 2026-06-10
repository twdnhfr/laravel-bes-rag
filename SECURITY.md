# Security Policy

## Supported versions

This package is in early development (`0.x`). Only the latest release receives
fixes.

## Reporting a vulnerability

Please report security vulnerabilities privately by email to
**tobias@wdnhfr.de** — do not open a public issue.

Include enough detail to reproduce (affected version, a minimal example, and the
impact). You'll get an acknowledgement as soon as possible, and a fix or
mitigation will be coordinated before any public disclosure.

## Security model

BES-RAG orchestrates LLM calls and retrieval over **your** data. Two boundaries
matter most:

- **No dynamic code evaluation.** Unlike the original BES inference code, goals are
  checked by registered, declarative verifier classes — never `eval` of
  LLM-generated code. Keep it that way; the arch test exists to enforce it.
- **Retrieval scoping is the consumer's job.** Multi-tenant isolation is delivered
  through `->retrievalContext([...])` → `RetrievalQuery->filters`. Your `Retriever`
  implementation must enforce those filters; the package passes them through but
  cannot police your store. The context is persisted on the run row so queue
  workers see it — treat run ids as you would any internal identifier.

Answers are synthesized strictly from cited, retrieved evidence, but the model
output is still untrusted text — escape or sanitize it before rendering, as you
would any LLM response.

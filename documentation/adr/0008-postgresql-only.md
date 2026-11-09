# 0008. PostgreSQL only

- Status: Accepted
- Date: 2026-11-09
- Issue: #1150
- Pull request: #1181

## Context

Tests ran on SQLite while production runs PostgreSQL. Differences (types, constraints, full-text search, JSON) hid bugs. The MVP needs full-text search and other PostgreSQL features.

## Decision

1. PostgreSQL 18 everywhere: development (Docker), CI (service container) and production.
2. Use PostgreSQL features when they help: full-text search (`tsvector`), JSONB, partial and expression indexes.

## Consequences

1. Tests need a PostgreSQL server (Docker locally, a service in CI).
2. Self-hosters must run PostgreSQL.

## Alternatives considered

1. Keep SQLite for tests: faster, but it tests a different database.

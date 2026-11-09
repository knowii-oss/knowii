# 0001. Record architecture decisions

- Status: Accepted
- Date: 2026-11-09

## Context

The platform grows from a resource-sharing app into a community platform (spaces, posts, chat, direct messages, courses). Many contributors and AI agents change the code. Without a record of why the structure is the way it is, each change can drift from the design.

## Decision

We record each decision that shapes the code as an ADR in `documentation/adr`, in the same pull request as the code. The [ADR guide](README.md) says when to write one. `AGENTS.md` tells agents to read the ADRs and the rules before structural changes.

## Consequences

1. Reviewers check that structural changes come with an ADR.
2. ADRs 0002 to 0010 record decisions made before this one, so the existing design is documented from the start.

## Alternatives considered

1. Decisions in issues and pull requests only: hard to find, and they do not say what is still valid.
2. A wiki outside the repository: drifts from the code.

# Designs

A design says what a feature does for the people who use it, and why, before it is built. It is the text that the owner approves. The decisions that shape the code are in the [architecture decision records](../adr/README.md): a design links to them.

## Contents

| Design                                                | Status                                     | Issues                       | Decision records   |
| ----------------------------------------------------- | ------------------------------------------ | ---------------------------- | ------------------ |
| [Notifications](notifications/design.md)              | Approved (2026-11-15); slices 1 to 3 built | #1392, slices #1474 to #1483 | ADR 0042, ADR 0048 |
| [Email groundwork](notifications/email-groundwork.md) | Approved (2026-11-17); not built           | #1477, #1478                 |                    |
| [Events](events/design.md)                            | Approved (2026-11-16); not built           | #1388, slices #1513 to #1516 |                    |
| [Component toolkit](component-toolkit/proposal.md)    | Accepted (2026-11-17); slice 1 built       | #657                         | ADR 0049           |
| [Personal knowledge layer](personal-layer/design.md)  | Draft; not approved                        | #1525, epic #1524            |                    |

## Rules

1. One folder per feature. The main file is `design.md` (or `proposal.md` for a proposal that is not a full design).
2. The second line of the file is its status: draft, approved or built, with the date, the tracking issue and the decision records.
3. A design comes in by a pull request. The owner approves it there, and the issue links to the file. A long design does not live in an issue comment.
4. A change of an approved design is a pull request too, with the yes of the owner. When the code and the design differ, say which one is right and correct the other.
5. The words follow the rules of the repository: simple English, no name of a third-party community product, no detail of an instance (server addresses, accounts, plans of providers).
6. Working files stay out of this folder: older versions of a design, critiques, research on other products, briefs for one task. See "Where designs and working files go" in [`AGENTS.md`](../../AGENTS.md).

---
name: local-design-check
description: 'Review local uncommitted changes in this CodeIgniter 4 project for design principles and responsibility boundaries. Use during development after a feature or fix, or when asked for a design check, architecture check, SOLID check, or whether a workaround belongs in the right layer. No PR required; read-only by default.'
---

# Local Design Check

Check whether a small change belongs where it was made, before the coding task is considered done. This is a focused design check, not a whole-repository audit, a style review, or a request to refactor everything.

## Scope and evidence

1. Start with the user's named files or feature. Otherwise inspect unstaged and staged changes, plus relevant untracked source files. Do not assume `git diff` includes untracked files. If there are no changes and no named scope, ask what to check rather than auditing the repository.
2. Read the changed code and only the nearest callers, callees, tests, and project conventions needed to understand who owns the behavior. Follow forwarding code to the layer that actually decides the behavior. Consult framework or package code only when its contract is in question; do not modify `vendor/`.
3. State the change's intended behavior and the suspected design boundary before judging it. Distinguish observed behavior and code evidence from assumptions; use a small targeted check when that would settle a disputed claim.
4. Do not change files, stage, commit, create a PR, or run destructive commands as part of this check. A separately requested fix is a separate task. Preserve other worktree changes.

## Engineering standards

- Read the applicable project instructions and check their concrete rules alongside the design lenses below. For Libraries, check ownership, HTTP and presentation boundaries, business contracts, explicit dependencies and service lifetime under `.github/instructions/codeigniter.instructions.md`.
- Treat violations introduced or worsened by the change, and required alignment within materially changed responsibilities, as delivery findings even when behavior works and tests pass. Cite the specific rule, code evidence and smallest correction; a runtime defect is not required to establish a standards violation.
- Distinguish mandatory rules from recommendations. For a deviation from a recommendation, verify its concrete justification, such as a framework integration contract, and report an unjustified deviation when it is in scope. Existing code style alone is not an exception to an explicit rule; do not impose interfaces, extra layers or dependency wrappers unless the project standard or current behavior requires them.
- Leave untouched historical debt outside the delivery gate. Align only the responsibilities materially changed, not every method in a touched file. For an explicitly requested refactoring assessment, identify candidates in the named scope and distinguish standards violations from optional improvements; do not apply that wider scope to routine change reviews.

## Design lenses

Apply only lenses relevant to the change; do not report a violation merely because a principle can be named.

- **Ownership and layering:** Is validation, business meaning, orchestration, persistence, or database compatibility handled by its owning layer? Does a caller know an implementation detail of its dependency? Would the same defect affect other callers?
- **SOLID:** Single responsibility and reasons to change; extension without scattered special cases; substitutability of implementations; narrow interfaces; dependencies on appropriate contracts. Look for actual consumer pressure, not a requirement to introduce interfaces or classes.
- **Cohesion and coupling:** Are related rules together, are dependencies directed consistently, and does a change force unrelated modules to know about each other? For shared business concepts, identify the authoritative source and check whether consumers maintain competing definitions; consider what must change when a valid value is added. Check duplicated policy, not incidental repeated syntax.
- **Abstraction and simplicity:** Is the proposed helper or indirection justified by current usage? Avoid speculative generalization, pattern-for-pattern's-sake, and refactoring stable code solely to satisfy a checklist (YAGNI/DRY).
- **Contracts and failure boundaries:** Are types, validation, transactions, error handling, and observable results coherent across the boundary? Can a local workaround hide a shared failure while tests still pass?

For this project, use `.github/instructions/codeigniter.instructions.md` and nearby code as the concrete baseline. Shared app code lives under `admin/app/`, admin module behavior under `admin/geminus/Admin/`; persisted runtime settings use CodeIgniter Settings. Controllers may validate requests, authorize, and coordinate responses. Keep framework, Settings persistence, and SQL-dialect concerns out of field-specific controller branches unless there is evidence that the behavior truly is a business rule. Do not recommend a new service or repository solely to make a controller shorter.

## Verdict

Report only actionable findings, ordered by impact. For each finding include the location, observed evidence, applicable engineering rule or affected design principle, why the code violates that rule or responsibility boundary, the smallest viable correction and its tradeoff. In-scope standards violations are blockers; optional cleanup is not. Prefer one concrete cause over multiple overlapping principle labels. Explain justified deviations from recommendations, and mark uncertain claims as needing verification with the check that would resolve them.

If no in-scope design issue or engineering standards violation is found, say so explicitly in one sentence and mention any important unverified assumption or standard. Do not present passing tests as proof of sound design or standards compliance, or claim a design rule is enforceable by static checks when it requires contextual judgment. Keep the response brief enough that the user can act on it without reading an exhaustive review.
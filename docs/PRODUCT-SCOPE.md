# PRODUCT SCOPE — canonical

> Binding. Source: v1.4.0 cold-start ledger **Addendum 19** + dispatch Mission.
> This statement is canonical; if any plan, PR, review, or fix contradicts it,
> this document wins.

`abilities-for-fluent-plugins` is a **site-agnostic product** for **arbitrary
WordPress** — **single-site AND multisite**, in **any configuration the wrapped
vendor plugin supports**.

- **Our sites are a test environment only** (helenawillow, wicked-community, …).
  They are *never* the development target and *never* the fix scope. Site
  choice is purely operational: where the vendor plugin happens to be installed
  to run a live call.
- **Site-coupled behavior is a DEFECT, not a fix.** A change that only works
  because of one of our sites' configuration (single-site assumption, a
  particular plugin/theme, a data quirk, a server setting) is wrong by
  definition. Fixes target the **vendor contract**, not a site.
- **Multisite is first-class.** Never silently assume single-site. If a fix or
  its verification has an untested multisite dimension, **flag it explicitly**
  (it does not necessarily gate, but it must be stated).
- **Verification language:** "live-verified via probe on `<site>` (incidental
  test surface)". The site is incidental; the product behavior on the vendor
  contract is the target. Never frame a fix as "for helena" / "the
  FluentCart-live site" / etc.

This applies to all work in this repository — plans, dev briefs, PRs, reviews,
vendor-map rows, and acceptance.

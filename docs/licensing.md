# Licensing

PitWall IT Operations is released under the **Business Source License 1.1**
(BUSL 1.1). The authoritative text is in [LICENSE](../LICENSE); this file
explains what it means in practice.

> Written as an engineering summary, not legal advice. Have a solicitor review
> the licence and the commercial terms before you rely on them.

## The parameters

| Parameter | Value |
| --- | --- |
| Licensor | Daniel Peter John Green |
| Licensed Work | PitWall IT Operations, Version 0.1 |
| Additional Use Grant | Production use, including MSP use; no competing hosted service |
| Change Date | 2030-08-10 |
| Change License | Apache License 2.0 |
| Licensing contact | [dgreen03@gmail.com](mailto:dgreen03@gmail.com) |

Copyright is held personally. If you later form a company and want it to hold
the rights, that is a copyright **assignment** — a signed document, not an edit
to this file.

Two practical notes on the contact address:

- It is published in a public repository, so it will be scraped. A dedicated
  address (`licensing@` on a domain you own) is easy to switch to later and
  keeps commercial enquiries out of a personal inbox.
- It is the only route the licence gives anyone to buy a commercial licence, so
  it needs to keep working. If it changes, `LICENSE` changes with it.

**Change Date — four years.** The maximum BUSL permits. Each *version* converts
four years after its own release, so this is not a single cliff: v0.1 converts
in 2030, and a version shipped in 2028 converts in 2032. The date in `LICENSE`
must be advanced when you cut a new version, or that version inherits an
already-expired date.

**Change License — Apache 2.0.** The usual pairing. Permissive, carries an
express patent grant, and enterprises are comfortable with it. BUSL requires the
Change License to be GPL-2.0-compatible, and Apache 2.0 qualifies.

## What people may and may not do

**Permitted without buying anything:**

- Read, copy, modify and redistribute the source
- Non-production use — evaluation, development, testing
- **Production use**, including an MSP running it to administer their own
  clients' Microsoft 365 tenants

That last point is deliberate and stated explicitly in the grant. MSPs are the
core audience, and an ambiguous grant would have made the obvious use case look
risky to exactly the people you want adopting it.

**Requires a commercial licence:**

- Offering PitWall to third parties as a hosted or managed service that gives
  them access to a substantial portion of its features

In short: run it for your own organisation or your own clients, freely. Resell
it as a competing SaaS, talk to the Licensor first.

## What this is not

BUSL is **not** an OSI-approved open source licence, and calling it "open
source" invites a fight that is not worth having. "Source available" is the
accurate term. The code becomes genuinely open source on the Change Date, when
it converts to Apache 2.0.

Practical consequences worth knowing now:

- Some organisations have procurement policies that block non-OSI licences
  outright
- Package registries and Linux distributions will not treat it as open source
- Contributors may want a CLA before contributing, since you will need the
  rights to relicense their work — worth deciding before the first external
  pull request, not after

## Trademark

The licence grants no rights in the PitWall name or the banner logo. That is
separate from the copyright in the code, and it is what stops someone shipping a
fork under your name after the Change Date. Keep the branding assets clearly
identified as trademarks rather than as part of the licensed source.

## Applying it to the code

BUSL requires the licence to be conspicuously displayed on each copy. The
`LICENSE` file at the repository root satisfies this for the project as a whole.

Per-file headers are optional and this project does not use them — they add
noise to every file and the root `LICENSE` is what actually governs. If you later
distribute individual files outside the repository, revisit that decision.

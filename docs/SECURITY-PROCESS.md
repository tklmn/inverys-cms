# Vulnerability Handling Process

How the Inverys CMS maintainers handle a security report — from intake to the
published advisory. The public-facing policy (supported versions, reporting
channel, timelines) is in [SECURITY.md](../SECURITY.md). The mapping to the EU
Cyber Resilience Act is in [CRA-READINESS.md](CRA-READINESS.md).

## Roles

| Role | Who | Responsibility |
|------|-----|----------------|
| Security contact | Maintainer (Tom Kilimann) | Receives reports, acknowledges, coordinates |
| Fix owner | Maintainer or a trusted contributor | Develops and tests the fix in a private fork |
| Reporter | External researcher / user | Provides details, reviews the fix if they wish |

## 1. Intake

Channels (see SECURITY.md):

- **GitHub Private Vulnerability Reporting** (preferred) — creates a draft
  security advisory that only maintainers and the reporter can see.
- **E-mail** to the maintainer — the maintainer opens a draft advisory
  (Security → Advisories → New draft advisory) and invites the reporter.
- Findings from our own work (audits, CI, `composer audit` / `npm audit`,
  Dependabot alerts) are handled the same way.

Within **5 business days**: acknowledge the report and confirm the channel.

## 2. Triage

Within **10 business days**:

1. Reproduce on the latest release and on `master`.
2. Determine the affected versions (see the supported versions table) and the
   affected components (core, plugin, theme, installer, API).
3. Rate the severity with CVSS (v3.1 or v4.0) and record the vector in the
   advisory.
4. Check whether it is **actively exploited** (reports from users, public
   exploit code, logs). If so, jump to [Actively exploited vulnerabilities](#actively-exploited-vulnerabilities).
5. Decide: valid / not a vulnerability (explain why) / duplicate. Tell the
   reporter.

For vulnerabilities in a **third-party dependency**: check whether Inverys CMS is
actually affected (is the vulnerable code path used?), update the dependency,
and publish an advisory only if Inverys CMS itself was exploitable. Report issues
in dependencies to their maintainers (CRA Art. 13(6)).

## 3. Fix

1. Work in the **temporary private fork** of the draft advisory (GitHub creates
   it on request), not in a public branch.
2. Write a regression test under `tests/Feature/Security/` that fails without
   the fix.
3. Keep the fix minimal; back-port it to every supported release line.
4. Run the full gate: `php artisan test`, `./vendor/bin/pint --test`,
   `python3 bin/check-german.py`, `composer audit`,
   `npm audit --audit-level=high`.
5. Let the reporter review the fix if they want to.

Target times from the assessment: critical 14 days, high 30 days, medium 90
days, low with the next regular release.

## 4. Release

1. Add a `### Security` section to the CHANGELOG entry. Describe the impact and
   the fix without a step-by-step exploit.
2. Cut the patch release with `bin/release.sh X.Y.Z` — it regenerates the SBOM,
   runs the German-text check, the tests and the dependency audits, and attaches
   the source archive, both SBOMs and `SHA256SUMS` to the GitHub release. The
   workflow `release-package.yml` then attaches the update package
   (`inverys-cms-X.Y.Z.zip` with its `.sha256`) that System → Updates installs,
   and installations are notified by their daily update check.
3. Merge the private fork into `master` through the advisory ("Merge pull
   request") or apply the patch right before tagging.

## 5. Advisory and CVE

1. Complete the draft advisory: summary, affected versions (`< X.Y.Z`), patched
   version, CVSS vector, CWE, workaround (if any), credits.
2. For **medium severity or higher**, click **Request CVE** — GitHub is a CVE
   Numbering Authority and assigns the ID.
3. **Publish** the advisory on the agreed date (at the latest together with the
   release). GitHub then notifies users through Dependabot alerts and the
   GitHub Advisory Database.

## 6. Notification

- The advisory and the release notes are the primary notification.
- For critical issues, additionally pin an announcement (GitHub Discussions /
  README banner) that tells operators to update and how to mitigate until then.
- Keep the advisory up to date if new information appears (e.g. more affected
  versions).

## 7. Post-mortem

For high and critical issues: note the root cause and whether a class of bugs
needs a broader fix (e.g. a new test helper, a lint rule, a middleware).

## Actively exploited vulnerabilities

- Treat as critical regardless of the CVSS score: mitigation or fix as fast as
  possible, advisory published with the fix.
- **CRA reporting duty (information):** from **11 September 2026**, *manufacturers*
  of products with digital elements placed on the EU market must notify an
  actively exploited vulnerability, and a severe incident affecting the security
  of the product, through the ENISA single reporting platform to the CSIRT of
  their Member State: an **early warning within 24 hours**, a **notification
  within 72 hours** and a **final report** within 14 days after a fix is
  available (vulnerabilities) or one month (incidents) — Art. 14 CRA.
  Open-source software stewards are subject to this duty only to the extent they
  are involved in the development (Art. 24(3)). Whether and in which role this
  applies to a given distribution of Inverys CMS depends on how it is made
  available (see [CRA-READINESS.md](CRA-READINESS.md)); this document is not
  legal advice.
- Keep a record of when the exploitation became known (timestamp), so these
  deadlines can be met if they apply.

## Records

Keep the draft/published advisories as the record of every report (also
rejected ones: close the draft with a comment). They document the date of the
report, the assessment, the fix and the disclosure.

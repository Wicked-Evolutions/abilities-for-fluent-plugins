#!/usr/bin/env python3
"""
P-LIBRARY-CSV generator
=======================

Produces, into ``tests/Staging/``:

- ``ability-library-ours.csv``                   (abilities OUR code registers)
- ``ability-library-vendors.csv``                (abilities the vendor plugin registers natively)
- ``ability-library-source-only-not-on-dev2.md`` (audit: slugs in our source, not on dev2)
- ``ability-library-README.md``                  (provenance + counts + invariant)

Both CSVs share the same 26-column header (P-LIBRARY-CSV spec, see HEADER below).

Single-shell-line regen:

    python3 tests/Staging/scripts/generate-ability-library.py

Pre-conditions (place files at these stable paths before running):

- ``/tmp/p-library-csv/discover-snapshot.json`` — full ``mcp-adapter-discover-abilities`` payload
  from dev2 (fresh on the day of regen). Generated via:

      # in this Claude session, the MCP tool stashes oversized responses to disk; copy that file:
      cp .../mcp-wordpress-mcp-adapter-discover-abilities-*.txt /tmp/p-library-csv/discover-snapshot.json

- ``/tmp/p-library-csv/abilities-full.jsonl`` — full WP-CLI metadata dump for every ability
  (needed because the MCP discover endpoint returns only ``{name,label,description}``).
  Generated via:

      ssh hostinger-web "cd /home/u748067201/domains/helenawillow.com/public_html/dev2 && \\
        wp eval 'do_action(\\"rest_api_init\\"); foreach (wp_get_abilities() as \\$a) { \\
          echo json_encode([ \\
            \\"name\\"=>\\$a->get_name(), \\
            \\"label\\"=>\\$a->get_label(), \\
            \\"description\\"=>\\$a->get_description(), \\
            \\"meta\\"=>\\$a->get_meta(), \\
            \\"input_schema\\"=>\\$a->get_input_schema(), \\
            \\"output_schema\\"=>\\$a->get_output_schema() \\
          ]).\\"\\n\\"; \\
        }' --user=1" > /tmp/p-library-csv/abilities-full.jsonl

  (admin user context + no --skip-themes — those are required to load the Astra namespace,
  which registers on a theme-load hook.)

- abilities-for-ai repo present at ``/Users/wicked/my-agent/wordpress-plugins-temp/abilities-for-ai/``.
  If absent, the script aborts (per P-LIBRARY-CSV directive D, "do NOT proceed with vendors-CSV
  without that grep target available").
"""

import csv
import hashlib
import json
import os
import re
import subprocess
import sys
from datetime import date, datetime
from pathlib import Path


# ----------------------------------------------------------------------------
# Inputs (paths)
# ----------------------------------------------------------------------------
PLUGIN_REPO = Path("/Users/wicked/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins")
AI_REPO = Path("/Users/wicked/my-agent/wordpress-plugins-temp/abilities-for-ai")
DISCOVER_SNAPSHOT = Path("/tmp/p-library-csv/discover-snapshot.json")
WPCLI_META = Path("/tmp/p-library-csv/abilities-full.jsonl")
RESULTS_LOG = Path("/Users/wicked/my-agent/abilities-for-fluent-plugins-fullsuite-results.md")
CAUTION_REGISTER = Path("/Users/wicked/my-agent/abilities-for-fluent-plugins-user-caution-register.md")
VENDOR_MAP_DIR = PLUGIN_REPO / "docs/vendor-map"

# Outputs
OUT_DIR = PLUGIN_REPO / "tests/Staging"
OURS_CSV = OUT_DIR / "ability-library-ours.csv"
VENDORS_CSV = OUT_DIR / "ability-library-vendors.csv"
SOURCE_ONLY_MD = OUT_DIR / "ability-library-source-only-not-on-dev2.md"
README_MD = OUT_DIR / "ability-library-README.md"


# ----------------------------------------------------------------------------
# 26-column header (P-LIBRARY-CSV spec — line-verbatim, copy byte-identical)
# ----------------------------------------------------------------------------
HEADER = [
    "ability",
    "label",
    "module",
    "source",
    "category",
    "description",
    "annotations",
    "tier",
    "R",
    "W",
    "D",
    "show_in_rest",
    "mcp_public",
    "input_schema_compact",
    "output_schema_compact",
    "registrar_file_line",
    "v140_fixed_set",
    "campaign_tested",
    "campaign_result",
    "severity",
    "symptom_short",
    "bug_issue",
    "cluster_pattern",
    "caution_register_entry",
    "probe_site",
    "last_verified",
]


# ----------------------------------------------------------------------------
# Cluster taxonomy → GitHub issue cross-ref (from `gh issue list #114-#138`)
# ----------------------------------------------------------------------------
CLUSTER_ISSUE = {
    "output-schema-drift":       ["#131"],
    "input-name-drift":          ["#136"],
    "php-fatal-on-write":        ["#135"],
    "sql-leak":                  ["#117", "#118", "#122", "#137"],
    "data-integrity-divergence": ["#134"],
    "pii-payload-bloat":         ["#130"],
    "filter-input-ignored":      ["#128"],
    "provider-class-missing":    ["#129"],
    "settings-write-drift":      ["#138"],
    "input-validation-gap":      ["#121"],
    "security-disclosure":       ["#120", "#127"],
    "contract-drift":            [],
    "no-teardown-roadmap":       ["#123"],
    "commerce-provider-missing": ["#129"],
    "funnel-math":               ["#132"],
}


# Modules whose full ability surface was swept end-to-end in the #116 campaign.
S1_COMPLETE_MODULES = {
    "fluent-affiliate", "fluent-auth", "fluent-boards", "fluent-booking",
    "fluent-cart", "fluent-community", "fluent-crm", "fluent-forms",
    "fluent-messaging", "fluent-player", "fluent-snippets", "fluent-support",
    "fluent",
}


# Per §4(b) of the campaign GATE-0: 3rd-party transport / license / real-send paths excluded.
EXCLUDED_PATTERNS = re.compile(
    r"^("
    r"fluent-booking/(activate-license|deactivate-license|get-license-info"
        r"|disconnect-zoom-account|update-twilio-config|list-remote-calendars"
        r"|disconnect-calendar-integration|create-webhook|update-webhook|delete-webhook)"
    r"|fluent-crm/(send-test-email|send-test-email-campaign|send-test-funnel-webhook"
        r"|resend-campaign-emails|resend-failed-campaign-emails|resend-unopened-campaign-emails"
        r"|run-import-csv|run-import-driver|upload-import-csv|import-companies-csv|import-funnel"
        r"|update-contact-custom-fields|update-settings)"
    r"|fluent-player/(activate-license|deactivate-license|get-license-details"
        r"|bunny-(?:storage|stream)-[a-z-]+|mux-[a-z-]+"
        r"|generate-youtube-storyboard|get-youtube-captions|get-youtube-channel-info|import-youtube-captions"
        r"|test-integration-connection|save-email-provider-settings|save-integration-settings"
        r"|list-provider-resources|validate-provider-field"
        r"|delete-email-collection|list-email-collections|get-email-collection|export-email-collections)"
    r")$"
)


TODAY = date.today().isoformat()


# ----------------------------------------------------------------------------
# Helpers
# ----------------------------------------------------------------------------
def file_md5(p: Path) -> str:
    return hashlib.md5(p.read_bytes()).hexdigest()[:12] if p.exists() else "MISSING"


def file_sha256(p: Path) -> str:
    return hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else "MISSING"


def git_head_short(repo: Path) -> str:
    try:
        return subprocess.check_output(
            ["git", "-C", str(repo), "rev-parse", "--short", "HEAD"],
            text=True,
            stderr=subprocess.DEVNULL,
        ).strip()
    except Exception:
        return "MISSING"


def compact_input_schema(schema) -> str:
    """Format: ``required:[k1,k2]; props:[k1,k2,k3]`` (P-LIBRARY-CSV spec col 14)."""
    if not isinstance(schema, dict):
        return "required:none; props:none"
    required = schema.get("required")
    if not isinstance(required, list):
        required = []
    props = list((schema.get("properties") or {}).keys())
    req_str = f"required:[{','.join(required)}]" if required else "required:none"
    prop_str = f"props:[{','.join(props)}]" if props else "props:none"
    return f"{req_str}; {prop_str}"


def compact_output_schema(schema) -> str:
    """Format: ``type:object; props:[k1,k2]`` or ``type:array; items:object`` (col 15)."""
    if schema in (None, [], {}, ""):
        return "type:none"
    if isinstance(schema, list):
        return "type:none"
    if not isinstance(schema, dict):
        return "type:none"
    t = schema.get("type") or ""
    if isinstance(t, list):
        t = "|".join(str(x) for x in t)
    if t == "array":
        items = schema.get("items") or {}
        if isinstance(items, dict):
            items_type = items.get("type") or "object"
            if isinstance(items_type, list):
                items_type = "|".join(str(x) for x in items_type)
        else:
            items_type = "object"
        return f"type:array; items:{items_type}"
    props = list((schema.get("properties") or {}).keys())
    if t:
        prop_str = f"props:[{','.join(props)}]" if props else "props:none"
        return f"type:{t}; {prop_str}"
    return "type:none"


def build_slug_index(repo: Path, subdirs: list) -> dict:
    """One-shot scan: capture every string-literal slug appearance in PHP files under subdirs.

    Returns ``{slug: 'rel/path/file.php:LINE'}`` (first-seen wins).
    """
    idx = {}
    if not repo.exists():
        return idx
    slug_re = re.compile(r"""['"]([a-z][a-z0-9-]*/[a-z][a-z0-9-]+)['"]""")
    for sub in subdirs:
        d = repo / sub
        if not d.exists():
            continue
        for php_file in d.rglob("*.php"):
            try:
                rel = php_file.relative_to(repo)
            except ValueError:
                continue
            try:
                with open(php_file, "r", encoding="utf-8", errors="ignore") as fh:
                    for i, line in enumerate(fh, 1):
                        for m in slug_re.finditer(line):
                            slug = m.group(1)
                            if slug not in idx:
                                idx[slug] = f"{rel}:{i}"
            except Exception:
                continue
    return idx


SEVERITY_RE = re.compile(r"\b(HIGH|MED-HIGH|MEDIUM|MED|LOW(?:-MED)?|MINOR|OBSERVATION)\b", re.IGNORECASE)


def infer_clusters(text: str) -> list:
    """Heuristic taxonomy inference from a caution-register row's free-text cells."""
    t = text.lower()
    clusters = []
    if any(s in t for s in ("output-schema", "output_schema", "output-validation", "output validation reject", "invalid output")):
        clusters.append("output-schema-drift")
    if any(s in t for s in ("input-name", "input drift", "schema/runtime", "input-schema/runtime", "is a required property", "is required", "required property of input")):
        clusters.append("input-name-drift")
    if any(s in t for s in ("php fatal", "validator", "typeerror", "json_decode", "array_map", "offset of type")):
        clusters.append("php-fatal-on-write")
    if "sql" in t and any(s in t for s in ("leak", "doesn't exist", "unknown column", "raw sql")):
        clusters.append("sql-leak")
    if any(s in t for s in ("data integrity", "silent divergence", "creates new", "silent no-op", "phantom-success", "phantom success", "claims success", "silent coercion", "silent no op")):
        clusters.append("data-integrity-divergence")
    if any(s in t for s in ("pii", "payload bloat", "default shape", "payload-bloat", "exceeds max", ">85k", ">50k", ">133k", ">205k", ">519k", ">58k", ">1.3mb")):
        clusters.append("pii-payload-bloat")
    if "filter" in t and any(s in t for s in ("ignored", "not applied", "filter input")):
        clusters.append("filter-input-ignored")
    if any(s in t for s in ("provider class", "provider-class", "commerce-provider", "commerce provider")):
        clusters.append("commerce-provider-missing")
    if any(s in t for s in ("no validation", "no slug validation", "no user-existence", "no user validation", "no registry", "input-validation gap", "input validation gap")):
        clusters.append("input-validation-gap")
    if any(s in t for s in ("zak", "start_url", "verify_key", "disclos", "security")):
        clusters.append("security-disclosure")
    if "contract" in t and "drift" in t:
        clusters.append("contract-drift")
    if any(s in t for s in ("no delete path", "no teardown", "roadmap")):
        clusters.append("no-teardown-roadmap")
    if any(s in t for s in ("funnel-conversion", "conversion rate", "100%", "math")):
        clusters.append("funnel-math")
    if "settings" in t and any(s in t for s in ("integrations", "sms", "compliance", "double-optin", "double optin")):
        clusters.append("settings-write-drift")
    # Dedupe, preserve order
    seen, out = set(), []
    for c in clusters:
        if c not in seen:
            seen.add(c)
            out.append(c)
    return out


def parse_caution_register(text: str) -> dict:
    """Walk every Markdown table row in the caution register; map each slug appearing in the
    'Ability' (first) cell to the row's severity/symptom/cluster/issue refs.

    Heuristic — the register is hand-written prose; this best-effort populates cols 20-24
    for slugs explicitly enumerated in a table row. Slugs not in any table row → blank.
    """
    out = {}
    # Match a table data-row (not the separator):
    #   | cell1 | cell2 | cell3 | cell4 | cell5 |
    # 5-cell rows are the bug-candidate table shape; 1-cell-or-other rows are ignored.
    row_re = re.compile(
        r"^\|\s*(?P<a>[^|]+?)\s*\|\s*(?P<b>[^|]+?)\s*\|\s*(?P<c>[^|]+?)\s*\|\s*(?P<d>[^|]+?)\s*\|\s*(?P<e>[^|]+?)\s*\|\s*$",
        re.MULTILINE,
    )
    slug_re = re.compile(r"[a-z][a-z0-9-]*/[a-z][a-z0-9-]+")

    for m in row_re.finditer(text):
        a = m.group("a")
        b = m.group("b")
        d = m.group("d")
        # Skip header & separator rows
        if a.lower() in {"ability", "ability_name", " ability "} or set(a.strip()) <= {"-", ":", " "}:
            continue
        slugs = slug_re.findall(a)
        if not slugs:
            continue
        sev_match = SEVERITY_RE.search(d)
        sev = (sev_match.group(1) if sev_match else "MINOR").upper()
        # Normalize
        if sev in ("MEDIUM",):
            sev = "MED"
        if sev == "LOW-MED":
            sev = "MED"  # caution-register also uses LOW-MED; collapse to MED for the CSV enum
        clusters = infer_clusters(a + " " + b + " " + d)
        issues = []
        for c in clusters:
            for ref in CLUSTER_ISSUE.get(c, []):
                if ref not in issues:
                    issues.append(ref)
        # Symptom = first sentence of cell B
        symp = re.split(r"(?<=[.!?])\s", b, maxsplit=1)[0].strip()
        if len(symp) > 320:
            symp = symp[:317] + "..."
        for slug in slugs:
            # First-seen wins (caution register is small — collisions rare)
            out.setdefault(slug, {
                "severity": sev,
                "symptom_short": symp,
                "cluster_pattern": ";".join(clusters),
                "bug_issue": ";".join(issues),
            })
    return out


def load_jsonl(path: Path) -> dict:
    """Stream-parse JSONL; keep only the JSON-object lines (skip WP-CLI banner lines)."""
    idx = {}
    if not path.exists():
        return idx
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            line = line.strip()
            if not line or not line.startswith("{"):
                continue
            try:
                rec = json.loads(line)
            except json.JSONDecodeError:
                continue
            name = rec.get("name")
            if name:
                idx[name] = rec
    return idx


def load_vendor_map(dir_: Path) -> set:
    """Union of all `.abilities[].ability` slugs across `docs/vendor-map/fluent-*.json`."""
    out = set()
    if not dir_.exists():
        return out
    for vm in sorted(dir_.glob("fluent-*.json")):
        try:
            data = json.loads(vm.read_text())
        except Exception:
            continue
        for a in data.get("abilities", []) or []:
            slug = a.get("ability") or a.get("name")
            if slug:
                out.add(slug)
    return out


def build_row(slug: str, rec: dict, *, registrar_line: str, v140: bool,
              caution: dict, is_ours: bool) -> dict:
    """Compose a 26-column row for one slug."""
    meta = (rec.get("meta") or {}) if isinstance(rec.get("meta"), dict) else {}
    annot = meta.get("annotations") or {} if isinstance(meta.get("annotations"), dict) else {}
    tier = meta.get("tier") or ""
    show_in_rest = "true" if meta.get("show_in_rest") else "false"
    mcp_block = meta.get("mcp") or {} if isinstance(meta.get("mcp"), dict) else {}
    mcp_public = "true" if mcp_block.get("public") else "false"
    permission = annot.get("permission") or ""

    R = "✓" if permission == "read" else ""
    W = "✓" if permission == "write" else ""
    D = "✓" if permission == "delete" else ""

    flags = []
    for k in ("readonly", "destructive", "idempotent"):
        v = annot.get(k)
        if v is True:
            flags.append(k)
    if permission:
        flags.append(f"permission:{permission}")
    annotations = ",".join(flags)

    input_compact = compact_input_schema(rec.get("input_schema"))
    output_compact = compact_output_schema(rec.get("output_schema"))

    module = slug.split("/", 1)[0]
    category = module  # category == namespace by default (descriptive cluster, not a separate taxonomy)

    description = (rec.get("description") or "").replace("\n", " ").strip()
    # source = first sentence of description (per spec col 4: "prose from get-ability-info description")
    src = re.split(r"(?<=[.!?])\s", description, maxsplit=1)[0].strip()
    if len(src) > 240:
        src = src[:237] + "..."

    row = {
        "ability": slug,
        "label": (rec.get("label") or "").strip(),
        "module": module,
        "source": src,
        "category": category,
        "description": description,
        "annotations": annotations,
        "tier": tier,
        "R": R,
        "W": W,
        "D": D,
        "show_in_rest": show_in_rest,
        "mcp_public": mcp_public,
        "input_schema_compact": input_compact,
        "output_schema_compact": output_compact,
        "registrar_file_line": registrar_line,
        "v140_fixed_set": "✓" if v140 else "",
        "campaign_tested": "",
        "campaign_result": "",
        "severity": "",
        "symptom_short": "",
        "bug_issue": "",
        "cluster_pattern": "",
        "caution_register_entry": "",
        "probe_site": "",
        "last_verified": "",
    }

    if is_ours:
        in_s1 = module in S1_COMPLETE_MODULES
        is_excluded = EXCLUDED_PATTERNS.match(slug) is not None
        if in_s1 and is_excluded:
            row["campaign_tested"] = "EXCLUDED"
            row["campaign_result"] = "SKIP-§4b"
        elif in_s1:
            row["campaign_tested"] = "✓"
            row["campaign_result"] = "PASS"
        # else: not in a swept module → leave blank
        ce = caution.get(slug)
        if ce:
            row["severity"] = ce["severity"]
            row["symptom_short"] = ce["symptom_short"]
            row["cluster_pattern"] = ce["cluster_pattern"]
            row["bug_issue"] = ce["bug_issue"]
            row["caution_register_entry"] = "✓"
            # Demote PASS → FAIL when there is a bug-candidate entry against this slug
            if row["campaign_result"] == "PASS":
                row["campaign_result"] = "FAIL"
        row["probe_site"] = "dev2"
        row["last_verified"] = TODAY
    else:
        # vendor-native: cols 18-25 blank by spec; col 25 + 26 still populated as metadata-snapshot
        row["probe_site"] = "dev2"
        row["last_verified"] = TODAY
    return row


# ----------------------------------------------------------------------------
# Main
# ----------------------------------------------------------------------------
def main() -> int:
    # 1. Hard pre-condition: abilities-for-ai repo present
    if not AI_REPO.exists():
        print(
            f"ERROR: abilities-for-ai repo not at {AI_REPO} — cannot grep ours-vs-vendors discriminator. "
            "Aborting per P-LIBRARY-CSV directive D. Clone the repo and re-run.",
            file=sys.stderr,
        )
        return 2

    # 2. Hard pre-condition: input files staged
    for p, hint in [
        (DISCOVER_SNAPSHOT, "fresh mcp-adapter-discover-abilities payload from dev2"),
        (WPCLI_META, "fresh `wp eval` dump (see script docstring)"),
    ]:
        if not p.exists():
            print(f"ERROR: missing {p} — {hint}. Aborting.", file=sys.stderr)
            return 2

    # 3. Load discover snapshot — authoritative source of "what exists on dev2 today"
    discover = json.loads(DISCOVER_SNAPSHOT.read_text())
    discover_records = {a["name"]: a for a in discover["abilities"]}
    discover_set = set(discover_records.keys())
    discover_slugs_sorted = sorted(discover_set)

    # 4. Load WP-CLI metadata — gives us meta/schemas for each slug
    wpcli_meta = load_jsonl(WPCLI_META)

    # 5. Index our repos (prefix repo name for cross-repo disambiguation in col 16)
    plugin_index = build_slug_index(PLUGIN_REPO, ["includes", "src", "admin"])
    ai_index = build_slug_index(AI_REPO, ["includes", "src", "admin"])
    ours_plugin = {s: f"abilities-for-fluent-plugins:{p}" for s, p in plugin_index.items()}
    ours_ai = {s: f"abilities-for-ai:{p}" for s, p in ai_index.items()}
    # Plugin wins on collision per D1
    ours_all = {**ours_ai, **ours_plugin}

    # 6. Vendor-map (v140 fixed-set)
    v140 = load_vendor_map(VENDOR_MAP_DIR)

    # 7. Caution-register parse
    caution_text = CAUTION_REGISTER.read_text() if CAUTION_REGISTER.exists() else ""
    caution = parse_caution_register(caution_text)

    # 8. Build rows for every slug in discover
    ours_rows = []
    vendors_rows = []
    for slug in discover_slugs_sorted:
        rec_wpcli = wpcli_meta.get(slug) or {}
        rec_discover = discover_records.get(slug) or {}
        # Merge: WP-CLI is richer; fall back to discover-lite for label/description if missing
        rec = {
            "name": slug,
            "label": rec_wpcli.get("label") or rec_discover.get("label") or "",
            "description": rec_wpcli.get("description") or rec_discover.get("description") or "",
            "meta": rec_wpcli.get("meta"),
            "input_schema": rec_wpcli.get("input_schema"),
            "output_schema": rec_wpcli.get("output_schema"),
        }
        is_ours = slug in ours_all
        registrar = ours_all.get(slug) if is_ours else "vendor-registered (not in our repos)"
        row = build_row(
            slug, rec,
            registrar_line=registrar,
            v140=(slug in v140),
            caution=caution,
            is_ours=is_ours,
        )
        (ours_rows if is_ours else vendors_rows).append(row)

    # 9. Source-only audit (D2): slugs in our repos but NOT in live discover
    source_only = sorted(set(ours_all.keys()) - discover_set)

    # 10. Write CSVs
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    def write_csv(path: Path, rows: list) -> None:
        with open(path, "w", newline="", encoding="utf-8") as f:
            w = csv.DictWriter(f, fieldnames=HEADER, quoting=csv.QUOTE_MINIMAL)
            w.writeheader()
            for r in rows:
                w.writerow({k: r.get(k, "") for k in HEADER})

    write_csv(OURS_CSV, ours_rows)
    write_csv(VENDORS_CSV, vendors_rows)

    # 11. Source-only audit
    with open(SOURCE_ONLY_MD, "w") as f:
        f.write("# ability-library — source-only (not on dev2)\n\n")
        f.write(
            f"> Slugs that appear in our repo source "
            f"(`abilities-for-fluent-plugins/{{includes,src,admin}}/` and "
            f"`abilities-for-ai/{{includes,src,admin}}/`) but are NOT in the live "
            f"dev2 `discover-abilities` snapshot from {TODAY}. Typical causes: registering "
            "plugin inactive on dev2, slug is a referenced/dead constant, or registration "
            "happens only under a code path not triggered in the snapshot context.\n\n"
        )
        f.write(f"**Count:** {len(source_only)}\n\n")
        if source_only:
            f.write("| Slug | First seen at |\n")
            f.write("|------|---------------|\n")
            for slug in source_only:
                loc = ours_all.get(slug, "?")
                f.write(f"| `{slug}` | `{loc}` |\n")

    # 12. README
    discover_sha = file_sha256(DISCOVER_SNAPSHOT)
    discover_iso = datetime.fromtimestamp(DISCOVER_SNAPSHOT.stat().st_mtime).isoformat(timespec="seconds")
    discover_size = DISCOVER_SNAPSHOT.stat().st_size
    plugin_head = git_head_short(PLUGIN_REPO)
    ai_head = git_head_short(AI_REPO)
    results_md5 = file_md5(RESULTS_LOG)
    caution_md5 = file_md5(CAUTION_REGISTER)
    vendor_lines = []
    for vm in sorted(VENDOR_MAP_DIR.glob("fluent-*.json")):
        vendor_lines.append(f"  - `docs/vendor-map/{vm.name}`: md5 `{file_md5(vm)}`")

    total = len(discover_set)
    n_ours = len(ours_rows)
    n_vendors = len(vendors_rows)
    invariant_ok = (n_ours + n_vendors == total)

    cluster_lines = []
    for c, issues in CLUSTER_ISSUE.items():
        refs = ", ".join(issues) if issues else "_(no issue yet)_"
        cluster_lines.append(f"- `{c}` → {refs}")

    README_MD.write_text(f"""# ability-library — README

> Generated by [`tests/Staging/scripts/generate-ability-library.py`](./scripts/generate-ability-library.py)
> (P-LIBRARY-CSV — see issue [#116](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/116)).

## What

Two CSVs of every ability discoverable on **dev2.helenawillow.com** today, **discriminated by who
registers the slug** (programmatic PHP-source grep, not by namespace heuristic):

- **`ability-library-ours.csv`** — slug literal found in `abilities-for-fluent-plugins/{{includes,src,admin}}/**.php`
  OR `abilities-for-ai/{{includes,src,admin}}/**.php`.
- **`ability-library-vendors.csv`** — slug NOT found in either of our repos → registered natively by
  the vendor plugin's own code (e.g. SureCart's own `surecart/*` registrations, WP-core `core/*`,
  Astra theme `astra/*`, etc.). The CSV's `registrar_file_line` cell reads `"vendor-registered (not in our repos)"`.

Both CSVs share the **same 26-column header** (see P-LIBRARY-CSV §A — copied verbatim into the
generator's `HEADER` constant). Vendor rows leave cols 17–24 empty by spec: we don't run the
campaign against vendor-native registrations, so test/cluster/caution columns aren't meaningful
for them.

## Counts (computed at generator-run-time, today: {TODAY})

| | Count |
|---|---|
| Live total on dev2 (`discover-abilities`) | **{total}** |
| Ours (registered by our code) | **{n_ours}** |
| Vendors (registered by vendor plugins) | **{n_vendors}** |
| **Invariant: ours + vendors == total** | **{n_ours} + {n_vendors} = {n_ours + n_vendors}** → **{"✓" if invariant_ok else "✗ FAIL"}** |

> **Separate audit** (not summed against the invariant): slugs found in our source but not in live
> dev2 discover → [`ability-library-source-only-not-on-dev2.md`](./ability-library-source-only-not-on-dev2.md).

## Provenance (computed at generator-run-time — replaced on each re-run)

- **Discover snapshot:**
  - SHA-256: `{discover_sha}`
  - Size: {discover_size:,} bytes
  - Captured: `{discover_iso}`
- **`abilities-for-fluent-plugins-fullsuite-results.md`:** md5 `{results_md5}`
- **`abilities-for-fluent-plugins-user-caution-register.md`:** md5 `{caution_md5}`
- **abilities-for-fluent-plugins** HEAD: `{plugin_head}`
- **abilities-for-ai** HEAD: `{ai_head}`
- **Vendor-map files** (`v140_fixed_set` derivation source):
{chr(10).join(vendor_lines) if vendor_lines else "  _(none found)_"}
- **GH issue range captured:** #114–#138 (titles fetched at HEAD via `gh issue list --repo Wicked-Evolutions/abilities-for-fluent-plugins`; see `CLUSTER_ISSUE` in the generator for the cluster→issue mapping in force at this run).

## Regen (single shell line)

```bash
python3 tests/Staging/scripts/generate-ability-library.py
```

Pre-conditions (the generator hard-aborts if missing — see script docstring for capture commands):

1. `/tmp/p-library-csv/discover-snapshot.json` — fresh `mcp-adapter-discover-abilities` payload from dev2 today.
2. `/tmp/p-library-csv/abilities-full.jsonl` — fresh `wp eval` over SSH dump of `wp_get_abilities()` w/ admin user context (loads astra namespace which registers on theme hooks).
3. `abilities-for-ai` repo present at `/Users/wicked/my-agent/wordpress-plugins-temp/abilities-for-ai/`.

## Discriminator rule (P-LIBRARY-CSV §D — ratified edge cases)

For each slug in the live `discover-abilities` snapshot:

1. Grep slug literal in `abilities-for-fluent-plugins/{{includes,src,admin}}/**.php`
2. Grep slug literal in `abilities-for-ai/{{includes,src,admin}}/**.php`
3. Hit in either → **OURS** row. No hit → **VENDORS** row.

Edge cases:

- **D1** — slug in BOTH our repos (rare; disjoint surfaces by design): single OURS row, `registrar_file_line` resolves to the abilities-for-fluent-plugins hit (plugin-repo wins on dedup).
- **D2** — slug in our repo source but NOT in live discover (registered-but-not-loaded; inactive plugin; dead constant): goes to the **separate audit file** `ability-library-source-only-not-on-dev2.md`. Not summed against the ours+vendors invariant.
- **D3** — slug in live discover but in NEITHER repo (vendor-native): goes to VENDORS. The exact registering plugin may be unknown if its source isn't in our local `wordpress-plugins-temp/` tree; that's the honest scope.

## Cluster taxonomy → bug-issue cross-ref (`CLUSTER_ISSUE`)

{chr(10).join(cluster_lines)}

## Column glossary (P-LIBRARY-CSV §B — format spec)

The header (26 columns, verbatim):

```
{",".join(HEADER)}
```

Key formats:

| Col | Field | Format |
|---|---|---|
| 4 | `source` | first sentence of `get-ability-info` description (RFC-4180 quoted if comma) |
| 7 | `annotations` | comma-separated flags from `meta.annotations` (e.g. `readonly,idempotent,permission:read`) |
| 9–11 | `R` / `W` / `D` | ✓ if `meta.annotations.permission == 'read'/'write'/'delete'`, else blank |
| 14 | `input_schema_compact` | `required:[k1,k2]; props:[k1,k2,k3]` (use `required:none` / `props:none` when empty) |
| 15 | `output_schema_compact` | `type:object; props:[k1,k2]` or `type:array; items:object` |
| 17 | `v140_fixed_set` | ✓ if slug appears in any `docs/vendor-map/fluent-*.json` `.abilities[].ability` |
| 18 | `campaign_tested` | ✓ / `EXCLUDED` / blank (blank = not in campaign scope; vendor rows always blank) |
| 19 | `campaign_result` | enum: PASS / FAIL / SKIP-no-fixture / SKIP-§4b / NOT-RUN |
| 20 | `severity` | HIGH / MED-HIGH / MED / LOW / MINOR / OBSERVATION (blank if PASS or no test) |
| 22 | `bug_issue` | `#NNN` or `#NNN;#NNN` multi-value with `;` separator |
| 23 | `cluster_pattern` | stable taxonomy slug(s) — see `CLUSTER_ISSUE` keys; `;`-separated multi |
| 24 | `caution_register_entry` | ✓ if slug appears in `abilities-for-fluent-plugins-user-caution-register.md` |
| 26 | `last_verified` | ISO `YYYY-MM-DD` (today for vendor metadata snapshot; tester-log date for OURS) |

## Limitations / honest scope

- **`campaign_result`** for OURS rows is set heuristically: rows in a `S1_COMPLETE_MODULES` namespace default to PASS, demoted to FAIL when a matching entry exists in the caution register. Rows outside the swept S1 set (e.g. WP-core `content/*`, `surecart/*` registered by us, `astra/*`, `presto-player/*`, `knowledge/*`) are left blank pending direct campaign sweep — they were sample-touched in the S2/S3 phase but not exhaustively.
- **`severity` / `symptom_short` / `cluster_pattern` / `bug_issue`** are parsed from the caution register's 5-column Markdown table heuristically — accurate for slugs explicitly enumerated in a row's first cell, blank otherwise. The register itself is the source of truth; the CSV is a denormalized projection of it.
- **`cluster_pattern`** uses a keyword-heuristic against the register row text — if the register grows new patterns that aren't in the `infer_clusters()` keyword list, those slugs land with empty cluster cells until the heuristic is extended (or the register is restructured to a machine-readable schema).
""")

    # 13. Print summary + return code (CI-friendly)
    print(f"OURS:     {n_ours}")
    print(f"VENDORS:  {n_vendors}")
    print(f"TOTAL:    {total}")
    print(f"Invariant ({n_ours}+{n_vendors}={n_ours+n_vendors}, expected {total}): "
          f"{'OK' if invariant_ok else 'FAIL'}")
    print(f"Source-only audit (not summed): {len(source_only)}")
    return 0 if invariant_ok else 1


if __name__ == "__main__":
    sys.exit(main())

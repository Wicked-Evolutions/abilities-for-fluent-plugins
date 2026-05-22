# Package 4b — P-H normalize-family: per-slug vendor-source pass

> Dispatch-mandated. NOT a blanket normalize. Each slug's decision below is
> grounded in the **installed vendor source** read live via the helenawillow
> probe (incidental test surface per `docs/PRODUCT-SCOPE.md` / Addendum 19;
> the target is product behavior on the vendor contract). Route resolution
> verified against the vendor route table `app/Http/Routes/api.php` (the
> registrar `Source:` descriptions carry pervasive method-name drift — that is
> a V4 description-accuracy concern, out of 4b scope, noted not fixed).

Decision vocabulary:
- **normalize** — declared collection key must always be a JSON array of
  objects; vendor returns a paginator object / `(object)` cast / `{data,total}`
  wrapper / post-type-keyed map / Eloquent Collection. Fix: wrap the proxy
  result in `fluent_abilities_normalize_collection(<proxy>, '<key>')` (the
  4a paginator/wrapper-aware helper: extracts `data`, `array_values()` keyed
  maps, `[]` for null/`{}`, idempotent on clean arrays, `WP_Error` passthrough).
  Rows are objects → no schema change.
- **item-union** — the keyed value is genuinely a **structured object**, not a
  row collection. Fix: `output_schema` declares the real object shape (NO
  array-coercion — normalizing would corrupt it).
- **separable-defect** — not a P-H response-shape issue at all; a distinct
  defect. Per the Addendum 15 (c) split trigger: **STOP, do not self-elect**,
  bounce to reviewer.

## NORMALIZE (21)

| slug | vendor handler (installed, route-verified) | vendor return (verbatim) | shape | why normalize |
|---|---|---|---|---|
| get-report-advanced-providers | `ReportingController::getAdvancedReportProviders` | `return ['providers'=>apply_filters('fluent_crm/advanced_report_providers',[])];` | filter passthrough; default `[]`, may be keyed-map | array/keyed-map drift; `array_values` → stable array. Item shape is a vendor **extension point** (filter-supplied) — documented, not pinnable. |
| get-report-contacts-by-tags | `ReportingController::getContactsByTags` | `return $this->sendSuccess(['tags'=>$tags]);` ($tags=`->paginate()`) | paginator object | declared array vs paginator object → normalize extracts `data`. (Row key `contact_count` vs schema `count` = V4 note, not a validation reject.) |
| get-report-contacts-by-lists | `ReportingController::getContactsByLists` | `return $this->sendSuccess(['lists'=>$lists]);` ($lists=`->paginate()`) | paginator object | same as above (`lists`). |
| get-report-recent-tags | `ReportingController::getRecentTags` | `return $this->sendSuccess(['tags'=>$tags]);` ($tags=`->limit()->get()`) | Collection | JSON array-of-objects; normalize is idempotent (`array_values`) — applied for family consistency + guards a keyed Collection. |
| get-report-automations | `ReportingController::getAutomationReports` | `return $this->sendSuccess(['automations'=>$funnels,'overview'=>...,'top_automations'=>...]);` ($funnels=`->paginate()`) | paginator object | normalize `automations` (paginator→data array). Row keys (`total_subscribers`/`in_progress_count`) diverge from schema (`subscribers_count`) and siblings `overview`/`top_automations` are dropped by the collection wrapper — **documented V4/shape limitation**, not a 4b reject fix. |
| list-report-emails | `ReportingController::getEmails` | `return ['emails'=>$emails,'statuses'=>$statuses,'types'=>$emailTypes];` ($emails=`->paginate()`) | paginator object | normalize the declared collection `emails` (paginator→data). `statuses`/`types` are separate non-declared keys (default `null`) — not the collection; left as-is (not schema-required). |
| search-contacts-fast | `SubscriberController::searchContacts` (L1655) | `return ['contacts'=>(object)$contacts];` | `(object)` id-keyed map | declared array vs JSON object/`{}` → normalize `array_values`. |
| list-subscriber-tracking-events | `SubscriberController::getTrackingEvents` (L1789) | `return ['events'=>$events];` ($events=`->paginate()`) | paginator object | normalize extracts `data`. `sendError()` branch (tracking disabled) → `WP_Error`, helper passes through untouched. |
| list-subscriber-automations | `FunnelController::subscriberAutomations` (L1027) | `return ['automations'=>$automations];` (`->paginate()`) | paginator object | normalize extracts `data`. |
| get-funnel-all-activities | `FunnelController::getAllActivities` (L672) | `return ['activities'=>$funnelSubscribers];` (`->paginate()`, rows decorated `->metrics`) | paginator object | normalize extracts `data` (decorated objects preserved). |
| list-campaigns-pro-post-taxonomies | `DynamicPostDataController::getPostsTaxonomies` (L124) | `return ['taxonomies'=>$taxonomies];` (post-type-keyed map; `[]` when none) | keyed-map / `[]` | object-when-populated vs `[]`-when-empty inconsistency → normalize for a stable array. |
| list-recurring-campaign-emails | `RecurringCampaignController::getEmails` (~L322) | `$data=['emails'=>RecurringMail::...->paginate()]; if(page==1) $data['drafts']=...->get(); return $data;` | paginator object | normalize `emails` (paginator→data). `drafts` = optional page-1-only key (not the declared collection; left optional). |
| list-pro-managers | `ManagerController::getManagers` (L11) | `return ['managers'=>['data'=>$managers,'total'=>$query->get_total()],'permissions'=>...];` | `{data:[obj],total}` wrapper | declared collection vs `{data,total}` wrapper → normalize extracts `data` (rows ARE objects; the v1.4.0 "non-object item" reading was the wrapper's `total` scalar, NOT a scalar-item-union — vendor source disproves the item-union hypothesis). |
| list-email-patterns | `EmailPatternController::index` (L13) | `return $this->sendSuccess(['patterns'=>['data'=>$formattedPatterns,'total'=>$patterns->total()]]);` | `{data:[obj],total}` wrapper | same wrapper shape → normalize extracts `data`. |
| list-company-notes | `CompanyController::getNotes` | `return $this->sendSuccess(['notes'=>$notes,'fields'=>...]);` ($notes=`->paginate()`) | paginator object | normalize `notes` (paginator→data). |
| list-campaign-emails | `CampaignController::campaignEmails` (L279) | `return ['emails'=>$emails,'failed_counts'=>int];` ($emails=`->paginate()`) | paginator object | normalize `emails` (paginator→data). Optional `campaign` key on `?with_campaign` (left optional). |
| get-system-logs | `SystemLogController::index` (L19) | `return ['logs'=>$logs];` ($logs=`->paginate()`) | paginator object | declared array vs paginator object → normalize extracts `data`. (This is the original "logs not array" P-H reject; the naive 4a-era array_values bug is fixed by the paginator-aware helper.) |
| list-experiments-campaigns | `SettingsController::getCampaigns` | `return ['campaigns'=>$campaigns];` ($campaigns=`Campaign::...->get()`) | Collection (unbounded) | JSON array-of-objects; normalize idempotent. **Multisite/scale note:** unpaginated `->get()` — unbounded payload (vendor behavior, flagged, not gated). |
| list-docs-addons | `DocsController::getAddons` (L64) | `return ['addons'=>$addOns(+optional experimental_features)];` | fixed non-empty keyed-map | declared array vs JSON object → normalize `array_values` (provider entries are objects). |
| list-fluent-forms-templates | `FormsController::getTemplates` (L217) | `return apply_filters('fluent_crm/ff_form_templates',['templates'=>$templates]);` | keyed-map (filter-mutable) | object-vs-array drift through a filter → normalize for stable array. |
| list-import-drivers | `ImporterController::getDrivers` (L21) | `return ['drivers'=>$drivers];` | fixed non-empty keyed-map | declared array vs JSON object → normalize `array_values` (driver entries are objects). |

## ITEM-UNION (3) — output_schema object-declare, NO normalize

| slug | vendor handler | vendor return (verbatim) | real shape → schema |
|---|---|---|---|
| get-contact-purchase-history | `PurchaseHistoryController::getOrders` (L24) — *route-verified; registrar cited `SubscriberController::getPurchaseHistory` which does not exist (V4 drift)* | `return $this->sendSuccess(['orders'=>$data]);` where `$data=apply_filters('fluent_crm/purchase_history_'.$provider,['orders'=>[],'total'=>0],$subscriber)` | `orders` is an **object** `{orders:array,total:int}` — declare object, not collection. |
| get-contact-support-tickets | `SubscriberController::getSupportTickets` (L966) | `return $this->sendSuccess(['tickets'=>$data]);` `$data=apply_filters('fluentcrm-get_support_tickets_'.$provider,['data'=>[],'total'=>0],$subscriber); $data['columns_config']=[...];` | `tickets` is an **object** `{data:array,total:int,columns_config:object}` — declare object. |
| get-contact-info-widgets | `SubscriberController::getInfoWidgets` (L1720) | `return ['widgets'=>['top_widgets'=>$topWidgets,'other_widgets'=>$otherWidgets,'widgets_count'=>int]];` (alternate `return ['widget'=>$widget];` when `?by_widget`) | `widgets` is an **object** `{top_widgets:array,other_widgets:array,widgets_count:int}`; note alternate singular `widget` key branch — declare both optional/union. |

## SEPARABLE-DEFECT (1) — STOP / bounce to reviewer, NOT self-elected

| slug | finding | why bounced |
|---|---|---|
| get-report-top-campaigns | The ability proxies `GET /fluent-crm/v2/reports/top-campaigns`. Vendor route table `app/Http/Routes/api.php` (`$router->prefix('reports')`) has **NO `top-campaigns` route** → `rest_do_request` returns a **404 WP_Error**. Closest real route: `reports/campaigns-list` → `ReportingController::getCampaignsList` (returns `sendSuccess(['campaigns'=>$campaigns])`, a paginator). | This is **not a P-H response-shape drift** — it is a broken proxy route (separable defect). Repointing the route is a V3 route-correctness change of a different class than the 4b normalize/union pass (cf. Addendum 15 (c) split trigger). **Not self-elected.** Excluded from 4b code; flagged to reviewer for disposition (repoint to `campaigns-list` vs deprecate). Vendor-map row records PENDING/bounced. |

## get-template — Addendum 15 (b): same-slug V3 correctness completion

`fluent-crm/get-template` proxies `GET /fluent-crm/v2/templates/{id}` → vendor `TemplateController::template($request,$templateId=0)` (route-verified; registrar cited `getTemplate` which does not exist — V4 drift). **Defect (V3):** `$template = Template::find($templateId)`; on a miss it does **not** 404 — the `else` branch fabricates a **blank default placeholder** (`post_title=''`, `post_content=''`, `design_template=Helper::getDefaultEmailTemplate()`) and still returns `sendSuccess(['template'=>$templateData])`. Caller cannot distinguish "not found" from "real empty template" — the v1.4.0 "placeholder instead of stored template / wrong shape" finding.

**(b) ruling applies — same-slug, NOT (c):** this is one slug, callback-body only, not a cross-slug pattern and not a separable defect from the slug's own contract. Minimum route-correctness + V5 fix, dual-cited:
- **V3 (route correctness):** guard `id <= 0` before the proxy (vendor coerces missing/non-int to `0` → guaranteed placeholder); after the proxy, detect the vendor placeholder sentinel (`post_title===''` AND `post_content===''` AND `design_template===` the vendor default) and return a typed `fluent_abilities_error('not_found', …)` instead of silently surfacing the placeholder — so the ability reflects the **actual stored** vendor template surface or an explicit not-found. Vendor primitives cited: `Template::find()` (silent-null source of the defect) vs `Template::findOrFail()` / `GET /templates/all` (`TemplateController::allTemplates`, only ever returns real persisted rows) as the existence-authoritative read.
- **V5 (shape):** return is already an object; ensure scalar/object boundary clean (no model/Collection leak — `sendSuccess` payload is plain arrays).
- **V3 read-back (live):** create a template → `get-template {id}` returns the stored title/content (not placeholder); `get-template {id: <nonexistent>}` returns typed `not_found` (not a blank 200). Provenance: ledger Addendum 15 (b); P4b.

## Multisite dimension (Addendum 19)
All 4b fixes are response-boundary projection / output_schema corrections at the
ability layer — **vendor-contract-level, site-config-agnostic, no single/multisite
branching**. Verification probes are single-site (incidental surface); the
multisite dimension is **config-agnostic by construction** — flagged, not gated.

---

## Deploy-Hygiene Receipt — corrected to the 792990b standard (reviewer #90)

Initial PR receipt said "probe restored to 29b9101". That was the **wrong
baseline**: P4b's parent is **792990b** (which includes 4a's edits to the same
`includes/crm/extended-*.php` + `includes/helpers.php`). The original restore
genuinely returned the probe's touched files to **29b9101 content** (pre-4a),
leaving the probe incoherent with the PR's actual parent. Resolved per reviewer
option (ii): the probe's 12 touched files were **re-restored to the 792990b
blob** (standing test-env clearance, reversible), pre/post recorded vs the true
parent.

helenawillow probe (incidental test surface), touched-file md5 (pre = state
before this correction = 29b9101 content; post = git `792990b:<file>` blob):

| file | pre (29b9101) | post-restore (792990b) | == 792990b |
|---|---|---|---|
| includes/crm/extended-campaigns.php | 66c97d22 | 8a03fcd8 | ✅ |
| includes/crm/extended-funnels.php | 2e77e8a6 | 53e8702e | ✅ |
| includes/crm/extended-misc-medium.php | 013125bd | 15cc3905 | ✅ |
| includes/crm/extended-misc-small.php | 2883517b | d24a931e | ✅ |
| includes/crm/extended-pro-companies.php | da1e571e | 101972db | ✅ |
| includes/crm/extended-pro-marketing.php | 410c30e0 | fa0cb000 | ✅ |
| includes/crm/extended-pro-settings-and-commerce.php | 4f373bd0 | 4f373bd0 | ✅ (4a-untouched; identical in both blobs) |
| includes/crm/extended-reports.php | abaaa3f7 | 44292357 | ✅ |
| includes/crm/extended-settings.php | d0f3f464 | d0f3f464 | ✅ (4a-untouched) |
| includes/crm/extended-subscribers.php | 78de92ac | 78de92ac | ✅ (4a-untouched) |
| includes/crm/extended-templates-and-patterns.php | 64ab5eb9 | 64ab5eb9 | ✅ (4a-untouched) |
| includes/helpers.php | 04f382da | 6089e3cf | ✅ (4a-provided helper set) |

Probe touched-file set is now coherently == **792990b** (P4b parent). Fixture
template 5111 deleted earlier; `/tmp` artifacts removed. **0 residue.** Same
pre==post-restore==PR-parent standard as P3c/P4a.

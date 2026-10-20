# Approved survey source mapping

## Scope and reading status

This review covers the two approved DOCX reports in `docs/approved-survey-sources/`. Their body paragraphs and table cells were extracted, and embedded media were visually inventoried. Source references below use document section names and table numbers in DOCX order; **page numbers are not verified** because these DOCX files could not be rendered to PDF in the review environment. The North Bank report contains technical diagrams and image-based tables whose small labels/numbers need full-resolution confirmation before they are entered as requirements. No requirement below is independently validated against a referenced aviation standard.

The reports are completed inspections. Observed answers, dates, comments, photographs, and findings are not template defaults. Keep source wording traceable, including apparent spelling errors or inconsistent terms, until an authorized source owner resolves them.

### DOCX table-order audit

The committed North Bank DOCX contains **36 physical Word tables**, not 32. The source mapping below numbers its visible/logical tables and combines some adjacent forms. In physical DOCX table order, the helicopter dimensions, taxiway, obstacle, firefighting, report identity, evidence, non-compliance, recommendation, HLL, and endorsement material are tables **16, 17, 18, 21, 31, 32, 33, 34, 35, and 36**, respectively. Physical table 12 has no extractable text. Several prior logical entries combine adjacent physical tables (including stand/protection, FATO/dimensions, and lighting). Template provenance uses physical table/row/cell order so a reviewer can locate the source without assuming the logical numbering is one-to-one. The North Bank visual-only/diagram content still needs full-resolution review.

## Proposed template structures

### Offshore helideck — FSO TOPAZ

Source: `HELIDECK REPORT FSO TOPAZ.docx` (35 tables).

1. **Template identity and installation context** — cover table 1 and installation-details table 2. Fields include landing site, owner/operator, inspector, operational-clearance wording, D/T values, inspection dates, installation type, location/area, drawings, height and installation identification. Keep site-specific entries out of the reusable template.
2. **Checklist sections** — table 3 and tables 4–29:
   - Manuals and procedures (table 3).
   - Helideck surface (4); dimensions and markings (5); aircraft dimensions/values (6); obstruction environment (7).
   - Helideck net (8), perimeter safety net (9), perimeter lighting (10), flood lighting (11), general lighting (12), obstruction marking/lighting (13), status light (14), additional equipment (15), turbulence (16), access points (17).
   - Firefighting equipment (18), its foam requirement data table (19), rescue equipment (20), water rescue (21), protective clothing (22), radio equipment (23), meteorology (24), miscellaneous (25), aviation fuel system (26), helideck crew (27), dangerous goods (28), and sub-1D risk assessment (29).
3. **Structured technical data, not ordinary questions** — aircraft dimension table (6), foam requirement matrix (19), and rescue equipment/quantity list (20). Represent rows and named columns so values retain their units, labels, and source reference. Do not convert example/recorded values into default answers or validation limits.
4. **Inspection report fields** — report identity table 30 and narrative executive summary following it. Table 31 is a photo/evidence layout, not a question list. Its labels are “VHF and HAND HELD RADIOS,” “CALIBRATED WEIGHING SCALE,” “BRIEFING ROOM,” “WIND SOCK WITH ILLUMINTION,” “HELICOPTER CRASH BOX,” “ANE MOMETER AND INCLINOMETER,” “SEALED HELIDECK WITH AIR GAP,” “LIFEBOAT,” “TOP VIEW,” “AFFF FOAM AND WATE R HOSE STATION,” “GOOD HELIDECK LIGHTING,” and “PRIMETER SAFETY NET AND LIGHT.” Non-compliance records (table 32), recommendations (33), HLL entries (34), endorsement/signatures (35), and distribution list are distinct report components.

### Onshore heliport — NORTH BANK

Source: `Heliport inspection report NORTH BANK.docx` (32 tables).

1. **Template identity and facility context** — cover table 1 and installation details table 2. The source labels facility type as “Surface Level Heliport: … Single Pad FATO …” and separately records location, operations area, drawings, elevation and identification. Treat completed North Bank values as inspection data only.
2. **Checklist sections** — checklist table 3 and tables 4–26:
   - Manuals/procedures (3); TLOF (4); helicopter stand/protection areas and central zone (5); FATO (6); safety areas/protected side slope (7); rejected takeoff area/clearway (8); identification markings (9); alignment guidance (10); heliport lighting (11); helicopter data (12); taxiways/taxi routes (13); obstacle limitation requirements (14).
   - Additional equipment (15), turbulence (16), firefighting data (17), protective clothing (18), water rescue (19), access control (20), radios (21), meteorology (22), miscellaneous (23), fuel system (24), helideck crew (25), and dangerous goods (26).
3. **Structured technical data, not ordinary questions** — helicopter dimensions table (12); taxiway/taxi-route criteria table (13); obstacle-surface material table/text (14); firefighting categories/agent data (17). Tables 4–11 also contain grouped measurements, dimensions, or multi-part checks that should retain their source-defined structure rather than become one free-text prompt each.
4. **Inspection/report content** — report identity table 27; inspection instructions, definitions and executive-summary narrative; photo/evidence layout (logical table 28; physical DOCX table 32) with the labels “CRASH BOX,” “FIRE FIGHTING SYSTEM,” and “AFF SYSTEM”; formal non-compliance records (logical table 29; physical table 33); recommendations (30/34); HLL (31/35); endorsements (32/36); and distribution/confirmation text after table 36.

## Traceability

Confidence describes confidence in locating and reading the source material, not technical validity.

| Source document | Section / table reference (page unverified) | Proposed application component | Confidence | Unresolved questions |
|---|---|---|---|---|
| FSO TOPAZ | Cover table 1; installation details table 2 | Versioned template metadata plus inspection-level site/owner/inspector/date fields | High for text | Which fields belong to a reusable template versus each inspection? Resolve inconsistent dates and D/T values before any example is retained. |
| FSO TOPAZ | Offshore checklist table 3; tables 4–5, 7–18, 21–29 | Ordered checklist sections/questions with source wording, reference citation and source table/row locator | High for extracted text | Confirm response semantics and whether compound prompts should split into subquestions. The report alternates among “COMPLIANT,” “COMPLAINT,” “UNKNOWN,” “NOT SIGHTED,” “NOT SEEN,” “NOT APPLICABLE,” and free text. |
| FSO TOPAZ | Aircraft table 6; foam table 19; rescue list table 20 | Repeatable structured data tables with typed columns/units and per-inspection values | High for extracted cells | Confirm table column interpretation and whether aircraft/foam tables are template reference data or inspection-specific schedules. Do not treat rows as requirements without source-owner approval. |
| FSO TOPAZ | Executive summary after table 30; image/evidence table 31 | Narrative report field and repeatable evidence/photo attachments grouped under exact source labels | High for labels; medium for image meaning | Confirm whether every category is expected for every inspection. The source does not state that a photo is mandatory for each category. |
| FSO TOPAZ | Non-compliance table 32; recommendations 33; HLL 34; endorsement 35; distribution list | Separate findings, recommendations, operational limitations, approvals/signatures and recipients | High for extracted text | Define lifecycle/statuses, responsible roles and whether report distribution is recorded or performed externally. |
| North Bank | Cover table 1; installation details table 2 | Versioned template metadata plus inspection-level facility/context fields | High for text | Resolve D/T ordering and confirm source terminology for the selected facility type. |
| North Bank | Onshore checklist table 3; tables 4–11, 15–16, 18–26 | Ordered onshore checklist sections/questions with citations and source locators | High for extracted text; medium where diagram-linked | Confirm which mixed offshore/helideck sections apply to onshore locations. Clarify response values; source includes Yes/No, compliant/non-compliant, unknown, not sighted, and free text. |
| North Bank | Helicopter table 12; taxiway table 13; obstacle table 14; firefighting table 17 | Structured helicopter, geometry, obstacle and firefighting datasets | High for text extraction; low/medium for image-linked details | Full-resolution review needed for diagrams/image tables and their labels/values. Confirm units, field meanings, and which criteria are intentionally applicable. |
| North Bank | Definitions before table 2; graphics embedded among tables 4–14 and 17 | Glossary and linked reference assets; do not flatten graphics into inferred rules | High for paragraph text; low for small graphic labels | Confirm image text and diagram revisions/source citations at full resolution. Page locations are not verified. |
| North Bank | Executive summary after table 27; evidence table 28 (physical table 32) | Narrative report and repeatable evidence/photo attachments grouped under exact source labels | High for labels; medium for image layout | Confirm whether every category is expected for every inspection. The source does not state that a photo is mandatory for each category. |
| North Bank | Non-compliance table 29; recommendations 30; HLL 31; endorsement 32; distribution/confirmation paragraphs | Findings, recommendations, limitations, approval/sign-off and distribution records | High for extracted text | Define workflow, required approvals, signature capture, and distribution audit expectations. |

## Source ambiguities to preserve

- **FSO TOPAZ next-inspection date:** cover table 1 lists 26 July 2026; report identity table 30 lists 26 July 2028. Do not choose one silently.
- **FSO TOPAZ D/T values:** cover table 1 records “21 12.8”; table 30 states D value 21 m and T value 12.8 t, while aircraft table 6 lists S92A T value 12.6t. Keep each source location/value distinct pending clarification.
- **North Bank D/T ordering:** cover table 1 says “12.6T, 21M”; table 27 says “21M, 12.6T.”
- **North Bank mixed terminology:** although titled as a heliport report and containing an “ONSHORE HELIPORT INSPECTION CHECKLIST,” it includes helideck/helipad language, helideck crew and offshore-reference material. Applicability needs confirmation.
- **Different standards references:** FSO TOPAZ says its report is based on UK CAA CAP 437 and Nigerian NCAA NCARS. North Bank checklist prompts cite Annex 14 Vol. II and other named materials; its executive summary also references CAP 437. These cited source documents are not included here, so this mapping does not reconcile or validate them.
- **Completed answers are observations:** neither report is a clean blank specification. Do not prefill template answers from the completed inspections.

## Existing application and gaps

The current working tree already has organization-scoped `SurveyType`, `ChecklistDefinition`, `ChecklistVersion`, `ChecklistSection`, `ChecklistQuestion` and `AnswerOption` models/migrations. The administration API supports listing, creating and editing definitions, drafting/cloning and publishing versions; the React page edits ordinary sections/questions/options. The feature test covers authentication/permission, organization isolation, draft creation, version immutability/cloning and basic validation.

The existing checklist model/API shape supports ordinary prompts and select options but does not persist formal source-document provenance per question/column, structured/repeating technical tables, or template report schemas. The separate metadata-snapshot migration named `2026_10_19_144851_add_metadata_snapshots_to_checklist_versions_table.php` remains an empty stub. The survey-taking slice adds organization-scoped survey instances pinned to a published checklist version, a creation-time checklist name/type snapshot, typed per-question answers/comments, assignment-scoped access, progress/resume and completion. It does not yet implement findings, evidence attachments, recommendations, HLL, endorsements, distribution auditing, or report generation. The source reference note in the UI is not a provenance record; editable question instructions/source notes can preserve unresolved source wording within a checklist version.

## Recommended data model for the next design slice

Keep the existing checklist tables as the versioned template foundation and add:

- **Template provenance:** source document record (stable identifier, filename, revision/date if confirmed, checksum); source locator (section/table/row/cell/page when verified); per version section/question/column citation and verbatim source label; ambiguity/review status. Preserve original wording separately from display labels or approved interpretations.
- **Versioned structured blocks:** typed repeatable table definition with ordered columns, labels, units, and source locators; cells/rows stored as typed JSON or normalized row/value records with validation and applicability rules. Use normalized relational columns where values need querying/reporting; never bake uncertain numbers into global validation rules.
- **Survey instance:** inspection record belongs to organization and location, references an immutable `checklist_version_id`, captures status, inspector/assignees and dates. Once created, later template versions must not alter its sections/questions/columns. If exact historical rendering must survive template archival or future schema changes, store a creation-time immutable schema snapshot alongside the FK.
- **Answers:** one answer per instance/question (or structured row/cell for table blocks), typed value plus source question key/version, comments and responder/time. Validate against that pinned version.
- **Findings/non-compliances:** separate records linked to inspection and optionally answer/source item; description, priority as explicitly defined by template, action party, target/closure date, closure evidence and verification. Do not infer a status from an answer automatically without an approved rule.
- **Evidence/photos:** attachment metadata and private object-storage key, original filename, media type, checksum, uploader/time, caption, and links to inspection/answer/finding/report section. Avoid storing public URLs as the access-control mechanism.
- **Recommendations:** distinct from findings, with source wording, action party, priority if applicable, comments and closure tracking.
- **HLL limitations:** aircraft type, limitation, reason, effective/removal dates and removal reason, linked to inspection and author/approval history.
- **Report narrative and approvals:** executive-summary text, report-only metadata, endorsement/signature events, recipient/distribution audit entries, and generated report artifact/version. Generate from the pinned checklist version plus that inspection’s answers and records; preserve a reproducible artifact and generation timestamp/template version.

Next step: review the North Bank embedded technical diagrams/tables at full resolution and obtain source-owner answers on the listed contradictions/applicability before seeding production templates. The next implementation design can then cover structured source tables, evidence/findings, HLL, approvals and reproducible report generation around the version-pinned survey records.

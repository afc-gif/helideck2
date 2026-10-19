"""Generate editable JSON template drafts from the approved DOCX source tables.

Run from backend/: python3 scripts/extract_approved_templates.py
The extractor uses only Python's standard library and never copies DOCX answer
cells or source schedule rows into the reusable definitions.
"""

from __future__ import annotations

import json
import re
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = Path(__file__).resolve().parents[1] / "resources/survey-templates/approved"
NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}

TEMPLATES = {
    "offshore-helideck": {
        "document": "HELIDECK REPORT FSO TOPAZ.docx",
        "sections": {
            3: "Manuals and procedures", 4: "Helideck surface",
            5: "Helideck dimensions and markings", 6: "Aircraft dimensions and values",
            7: "Obstruction environment", 8: "Helideck net", 9: "Perimeter safety net",
            10: "Perimeter lighting", 11: "Helideck flood lighting", 12: "General lighting",
            13: "Obstruction marking and lighting", 14: "Status light",
            15: "Additional equipment", 16: "Turbulence", 17: "Access points",
            18: "Firefighting equipment", 19: "Foam requirement schedule",
            20: "Rescue equipment schedule", 21: "Water rescue", 22: "Protective clothing",
            23: "Radio operator and radio equipment", 24: "Meteorological reports and equipment",
            25: "Miscellaneous", 26: "Aviation fuel system", 27: "Helideck crew",
            28: "Dangerous goods", 29: "Sub-1D risk assessment",
        },
    },
    "onshore-heliport": {
        "document": "Heliport inspection report NORTH BANK.docx",
        "sections": {
            3: "Manuals and procedures", 4: "TLOF",
            5: "Helicopter stands and protection areas", 6: "Helicopter stands and protection areas",
            7: "FATO", 8: "FATO dimensions", 9: "Safety areas and protected side slope",
            10: "Rejected take-off area and clearway", 11: "Identification markings",
            13: "Flight-path alignment guidance", 14: "Heliport lighting", 15: "Heliport lighting",
            16: "Helicopter dimensions and values", 17: "Taxiways and taxi routes",
            18: "Obstacle limitation surfaces", 19: "Additional equipment", 20: "Turbulence",
            21: "Firefighting", 22: "Protective clothing", 23: "Water rescue",
            24: "Access control", 25: "Radio operator and radio equipment",
            26: "Meteorological reports and equipment", 27: "Miscellaneous",
            28: "Aviation fuel system", 29: "Helideck crew", 30: "Dangerous goods",
        },
    },
}


def clean(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("\xa0", " ")).strip()


def cell_text(cell: ET.Element) -> str:
    paragraphs = [
        "".join(node.text or "" for node in paragraph.findall(".//w:t", NS))
        for paragraph in cell.findall("w:p", NS)
    ]

    return clean(" ".join(paragraphs))


def locator(table: int, row: int, cell: int | None = None) -> dict[str, str]:
    result = {"table": str(table), "row": str(row)}
    if cell is not None:
        result["cell"] = str(cell)
    return result


def provenance(document: str, wording: str, location: dict[str, str]) -> dict[str, object]:
    return {
        "document_identifier": document,
        "wording": wording,
        "locator": location,
        "review_status": "review_required",
    }


def technical_block(slug: str, table_no: int, table: ET.Element, key: str, document: str) -> dict[str, object]:
    rows = table.findall("w:tr", NS)
    header_cells = rows[0].findall("w:tc", NS) if rows else []
    labels = [cell_text(cell) for cell in header_cells]
    units: list[str] = []
    if slug == "offshore-helideck" and table_no == 19 and len(rows) > 1:
        units = [cell_text(cell).strip("() ") for cell in rows[1].findall("w:tc", NS)]

    block_titles = {
        ("offshore-helideck", 6): "Aircraft dimensions and values",
        ("offshore-helideck", 19): "Foam requirement matrix",
        ("offshore-helideck", 20): "Rescue equipment schedule",
        ("onshore-heliport", 16): "Helicopter dimensions and values",
        ("onshore-heliport", 17): "Taxiways and taxi-route criteria",
        ("onshore-heliport", 18): "Obstacle limitation surface data",
        ("onshore-heliport", 21): "Firefighting equipment data",
    }
    if slug == "onshore-heliport" and table_no in (17, 18, 21):
        custom_columns = {
            17: [
                ("Taxiway / taxi-route type", "Helicopter GROUND Taxiways & GROUND Taxi Routes; Helicopter AIR Taxiways & AIR Taxi Routes"),
                ("Installed", "Installed: Yes or No"),
                ("Dimensions", "Ground Taxiway Dimensions; Ground Taxi-Route Dimensions; Air Taxiway Dimensions; Air Taxi-Route Dimensions"),
                ("Slope", "Slope of Taxiway & Taxi Route; Slope of Air Taxiway & Air Taxi Route"),
                ("Maximum static load bearing", "Taxiway’s & Taxi Routes Maximum Static Load Bearing; Air Taxiway’s & Air Taxi Routes Maximum Static Load Bearing"),
                ("Rotor-wash resistance", "Resistant to rotor wash: Yes or No"),
                ("Rapid drainage", "Rapid Drainage: Yes or No"),
            ],
            18: [
                ("Obstacle surface", "Approach Surface; Take-off & Climb Surface"),
                ("Description and characteristics", "Description; CHARACTERICTICS"),
                ("Underlying obstacles and penetration", "Obstacles underlying the Approach Surface; Penetrated by any obstacles"),
                ("Clearway provided", "Clearway Provided: Yes or No"),
                ("Dimensions and recorded details", "Dimensions within with Annex 14 Table 4-1"),
            ],
            21: [
                ("Helicopter category", "Category of helicopter: H1, H2 or H3"),
                ("Foam quantity and type", "FOAM Quantity on site; Level B Performance: Yes or No; Type: (1%-6%)"),
                ("Backup quantity", "Backup quantity on site: (100%)"),
                ("Foam monitor count and type", "Foam Monitors: Number of Monitors; Type: (Aspirated, Non-aspirated, Manual, Oscillating)"),
                ("Water quantity, hydrants and pressure", "Quantity of water available; Number of Hydrant Points; Location of Hydrant Points; Main water pressure"),
                ("Discharge rates", "Discharge Rates of Fire Fighting Agents: FOAM; Dry powder; Halon; CO2"),
                ("Firefighting equipment and records", "Crash Box; Required Equipment Contained in each Crash Box"),
            ],
        }[table_no]
        labels = [item[0] for item in custom_columns]
    else:
        custom_columns = []

    columns = []
    for index, label in enumerate(labels, 1):
        if not label:
            continue
        data_type = "text"
        unit = None
        if slug == "offshore-helideck" and table_no == 6 and 1 < index < 11:
            data_type = "decimal"
        elif slug == "offshore-helideck" and table_no == 19:
            data_type = "decimal"
            unit = units[index - 1] if index <= len(units) else None
        elif slug == "offshore-helideck" and table_no == 20 and index == 2:
            data_type = "integer"
        elif slug == "onshore-heliport" and table_no == 16 and 1 < index < 12:
            data_type = "decimal"
        elif slug == "onshore-heliport" and table_no == 17 and index in (6, 7):
            data_type = "boolean"
        source_wording = custom_columns[index - 1][1] if custom_columns else label
        columns.append({
            "stable_key": f"{key}-column-{index}",
            "label": label,
            "source_label": source_wording,
            "data_type": data_type,
            "unit": unit,
            "source_provenance": provenance(
                document,
                source_wording,
                locator(table_no, {1: 3, 2: 4, 3: 4, 4: 5, 5: 8, 6: 9, 7: 11}.get(index, 1), index)
                if table_no == 21 and slug == "onshore-heliport"
                else locator(table_no, 1, index) if not custom_columns
                else {"table": str(table_no)},
            ),
        })

    return {
        "stable_key": f"{key}-block",
        "title": block_titles[(slug, table_no)],
        "description": "Source-derived structured schedule. Rows start blank; source inspection data rows are not loaded.",
        "columns": columns,
    }


def build(slug: str, spec: dict[str, object]) -> dict[str, object]:
    document = str(spec["document"])
    source_path = ROOT / "docs/approved-survey-sources" / document
    root = ET.fromstring(zipfile.ZipFile(source_path).read("word/document.xml"))
    tables = root.findall(".//w:tbl", NS)
    section_specs = []
    structured = {
        "offshore-helideck": {6, 19, 20},
        "onshore-heliport": {16, 17, 18, 21},
    }[slug]

    for table_no, title in spec["sections"].items():
        table = tables[table_no - 1]
        key = f"{slug}-table-{table_no}"
        section = {
            "stable_key": key,
            "title": title,
            "description": "",
            "source_provenance": provenance(document, title, {"table": str(table_no)}),
            "questions": [],
            "table_blocks": [],
        }
        if table_no in structured:
            section["table_blocks"].append(technical_block(slug, table_no, table, key, document))
        else:
            for row_no, row in enumerate(table.findall("w:tr", NS), 1):
                cells = row.findall("w:tc", NS)
                label = cell_text(cells[0]) if cells else ""
                if not label or label.lower() in {"item", "item description", "helicopter type"}:
                    continue
                # These North Bank cells mix prompt text with observed answers in the same cell.
                # Do not turn those completed inspection values into reusable question wording.
                if slug == "onshore-heliport" and table_no == 19 and row_no in {5, 6}:
                    continue
                section["questions"].append({
                    "stable_key": f"{key}-row-{row_no}",
                    "label": label,
                    "field_type": "text",
                    "is_required": False,
                    "unit": None,
                    "options": [],
                    "source_provenance": provenance(document, label, locator(table_no, row_no, 1)),
                })
        section_specs.append(section)

    title = "FSO TOPAZ Offshore Helideck" if slug == "offshore-helideck" else "North Bank Onshore Heliport"
    return {
        "key": f"artsci-{slug}",
        "name": title,
        "survey_type": slug,
        "description": f"Draft template derived from approved source {document}. Source wording requires owner review; inspection answers and schedule rows are not preloaded.",
        "source_document_identifier": document,
        "source_review_status": "review_required",
        "sections": section_specs,
        "source_notes": [
            "Locators use one-based physical DOCX table, row, and cell order. Page numbers are omitted because they are unverified.",
            "Completed values in answer/comment cells and all source schedule rows are omitted.",
        ],
    }


if __name__ == "__main__":
    OUTPUT.mkdir(parents=True, exist_ok=True)
    for survey_slug, definition in TEMPLATES.items():
        data = build(survey_slug, definition)
        destination = OUTPUT / f"{survey_slug}.json"
        destination.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        question_count = sum(len(section["questions"]) for section in data["sections"])
        block_count = sum(len(section["table_blocks"]) for section in data["sections"])
        print(f"{destination}: {len(data['sections'])} sections, {question_count} questions, {block_count} tables")

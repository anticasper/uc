import json
import re
import unicodedata
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

from normalize_cities import normalize_city


WORKBOOK_PATH = Path("base.xlsx")
OUTPUT_PATH = Path("base.json")
NS = {"a": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}


def normalize_key(text):
    normalized = unicodedata.normalize("NFD", text)
    normalized = "".join(char for char in normalized if unicodedata.category(char) != "Mn")
    normalized = normalized.lower().strip()
    normalized = re.sub(r"[^a-z0-9]+", "_", normalized)
    return normalized.strip("_")


def repair_text(value):
    if not isinstance(value, str):
        return value

    text = value.strip()
    if not text:
        return ""

    if any(token in text for token in ("Ã", "â", "�")):
        try:
            return text.encode("latin1").decode("utf-8").strip()
        except (UnicodeEncodeError, UnicodeDecodeError):
            return text

    return text


def column_letters(cell_ref):
    match = re.match(r"([A-Z]+)", cell_ref or "")
    return match.group(1) if match else ""


def read_shared_strings(archive):
    shared_strings = []
    if "xl/sharedStrings.xml" not in archive.namelist():
      return shared_strings

    root = ET.fromstring(archive.read("xl/sharedStrings.xml"))
    for item in root.findall("a:si", NS):
        text = "".join(node.text or "" for node in item.iterfind(".//a:t", NS))
        shared_strings.append(text)
    return shared_strings


def read_sheet_rows(archive, sheet_path, shared_strings):
    root = ET.fromstring(archive.read(sheet_path))
    rows = []

    for row in root.find("a:sheetData", NS):
        parsed_row = {}
        for cell in row.findall("a:c", NS):
            ref = cell.attrib.get("r", "")
            cell_type = cell.attrib.get("t")
            value_node = cell.find("a:v", NS)

            if cell_type == "s" and value_node is not None:
                value = shared_strings[int(value_node.text)]
            elif cell_type == "inlineStr":
                value = "".join(node.text or "" for node in cell.iterfind(".//a:t", NS))
            elif value_node is not None:
                value = value_node.text or ""
            else:
                value = ""

            parsed_row[column_letters(ref)] = repair_text(value)
        rows.append(parsed_row)

    return rows


def parse_activities(raw_text):
    text = (raw_text or "").strip()
    if not text or text.lower() == "não informada":
        return []

    normalized = text.replace("\r\n", "\n").replace("\r", "\n").strip()
    chunks = re.split(r"\n(?=T[íi]tulo\s*\(Nome da Atividade\):)", normalized)
    activities = []

    for chunk in chunks:
        block = chunk.strip()
        if not block:
            continue

        activity = {
            "titulo": "",
            "data": "",
            "horario": "",
            "descricao": "",
            "publico": "",
            "dificuldade": "",
            "texto_completo": block,
        }

        current_key = None
        label_map = {
            "titulo_nome_da_atividade": "titulo",
            "data": "data",
            "horario": "horario",
            "descricao": "descricao",
            "publico": "publico",
            "dificuldade": "dificuldade",
        }

        for line in block.split("\n"):
            stripped = line.strip()
            if not stripped:
                continue

            if ":" in stripped:
                label, value = stripped.split(":", 1)
                mapped_key = label_map.get(normalize_key(label))
                if mapped_key:
                    current_key = mapped_key
                    activity[current_key] = value.strip()
                    continue

            if current_key:
                extra = stripped if not activity[current_key] else f"{activity[current_key]} {stripped}"
                activity[current_key] = extra.strip()

        if any(value for field, value in activity.items() if field != "texto_completo"):
            activities.append(activity)

    if activities:
        return activities

    return [
        {
            "titulo": "",
            "data": "",
            "horario": "",
            "descricao": text,
            "publico": "",
            "dificuldade": "",
            "texto_completo": text,
        }
    ]


def build_records(rows):
    if not rows:
        return []

    header_row = rows[0]
    ordered_columns = [key for key in sorted(header_row.keys(), key=lambda item: (len(item), item)) if header_row[key]]
    headers = {column: header_row[column].strip() for column in ordered_columns}

    records = []
    for index, row in enumerate(rows[1:], start=2):
        if not any((row.get(column) or "").strip() for column in headers):
            continue

        raw = {}
        normalized = {}

        for column, header in headers.items():
            value = repair_text(row.get(column) or "")
            raw[header] = value
            normalized[normalize_key(header)] = value

        normalized["atividades_lista"] = parse_activities(normalized.get("atividades", ""))
        normalized["atividade_total"] = len(normalized["atividades_lista"])
        normalized["row_number"] = index

        if normalized.get("municipio"):
            normalized_city = normalize_city(normalized["municipio"], normalized.get("nome", ""))
            if normalized_city and normalized_city != normalized["municipio"]:
                normalized["municipio_original"] = normalized["municipio"]
                normalized["municipio"] = normalized_city

        records.append(
            {
                "id": len(records) + 1,
                "row_number": index,
                "raw": raw,
                "normalized": normalized,
            }
        )

    return records


def main():
    with zipfile.ZipFile(WORKBOOK_PATH) as archive:
        shared_strings = read_shared_strings(archive)
        rows = read_sheet_rows(archive, "xl/worksheets/sheet1.xml", shared_strings)

    records = build_records(rows)
    payload = {
        "source_file": WORKBOOK_PATH.name,
        "sheet_name": "Planilha1",
        "total_records": len(records),
        "records": records,
    }

    OUTPUT_PATH.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Arquivo gerado: {OUTPUT_PATH} com {len(records)} registros.")


if __name__ == "__main__":
    main()

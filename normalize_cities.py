import json
import re
import unicodedata
from pathlib import Path


UF_CODES = {
    "AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG",
    "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO",
}

UF_NAMES = {
    "acre", "alagoas", "amapa", "amazonas", "bahia", "ceara", "distrito federal",
    "espirito santo", "goias", "maranhao", "mato grosso", "mato grosso do sul",
    "minas gerais", "para", "paraiba", "parana", "pernambuco", "piaui", "rio de janeiro",
    "rio grande do norte", "rio grande do sul", "rondonia", "roraima", "santa catarina",
    "sao paulo", "sergipe", "tocantins",
}

CITY_ALIASES = {
    "alta floresta": "Alta Floresta",
    "alto paraiso de goias": "Alto Paraíso de Goiás",
    "apiai": "Apiaí",
    "atilio vivacqua": "Atílio Vivacqua",
    "baiao": "Baião",
    "baião": "Baião",
    "belem": "Belém",
    "belém": "Belém",
    "brasilia": "Brasília",
    "brasília": "Brasília",
    "ceara-mirim": "Ceará-Mirim",
    "ceará-mirim": "Ceará-Mirim",
    "chapada gaucha": "Chapada Gaúcha",
    "conceicao do mato dentro": "Conceição do Mato Dentro",
    "conceição do mato dentro": "Conceição do Mato Dentro",
    "cordisburgo": "Cordisburgo",
    "fóz do iguacu": "Foz do Iguaçu",
    "foz do iguacu": "Foz do Iguaçu",
    "foz do iguaçu": "Foz do Iguaçu",
    "grao mogol": "Grão Mogol",
    "grão mogol": "Grão Mogol",
    "guajara-mirim": "Guajará-Mirim",
    "guajará-mirim": "Guajará-Mirim",
    "itaituba": "Itaituba",
    "juruti": "Juruti",
    "novo airao": "Novo Airão",
    "novo airão": "Novo Airão",
    "palhoca": "Palhoça",
    "palhoça": "Palhoça",
    "passa quatro": "Passa Quatro",
    "piracuruca": "Piracuruca",
    "rio de janeiro": "Rio de Janeiro",
    "santarem": "Santarém",
    "santarém": "Santarém",
    "sao paulo": "São Paulo",
    "são paulo": "São Paulo",
    "sao roque de minas": "São Roque de Minas",
    "são roque de minas": "São Roque de Minas",
    "teresopolis": "Teresópolis",
    "teresópolis": "Teresópolis",
    "uberlandia": "Uberlândia",
    "uberlândia": "Uberlândia",
}

LOWER_WORDS = {"da", "de", "di", "do", "das", "dos", "e"}


def strip_accents(text):
    normalized = unicodedata.normalize("NFD", text or "")
    return "".join(char for char in normalized if unicodedata.category(char) != "Mn")


def canonical(text):
    text = strip_accents(text).lower().strip()
    text = re.sub(r"\s+", " ", text)
    return text


def smart_title(text):
    parts = re.split(r"(\s+|-|/|,)", text.strip().lower())
    titled = []

    for part in parts:
        if not part or part.isspace() or part in {"-", "/", ","}:
            titled.append(part)
        elif part in LOWER_WORDS:
            titled.append(part)
        else:
            titled.append(part[:1].upper() + part[1:])

    return "".join(titled).strip()


def strip_state_suffix(text):
    value = re.sub(r"\s+", " ", text or "").strip(" ,.-/")

    for _ in range(2):
        before = value
        value = re.sub(r"\s*[/,-]\s*([A-Za-z]{2})\.?$", lambda m: "" if m.group(1).upper() in UF_CODES else m.group(0), value).strip(" ,.-/")
        value = re.sub(r"\s+\b([A-Za-z]{2})\.?$", lambda m: "" if m.group(1).upper() in UF_CODES else m.group(0), value).strip(" ,.-/")

        lowered = canonical(value)
        for uf_name in UF_NAMES:
            suffixes = (f"/{uf_name}", f"-{uf_name}", f", {uf_name}", f" {uf_name}")
            if any(lowered.endswith(suffix) for suffix in suffixes):
                pattern = re.compile(rf"\s*[/,\- ]\s*{re.escape(uf_name)}\.?$", flags=re.IGNORECASE)
                value = pattern.sub("", value).strip(" ,.-/")
                break

        if value == before:
            break

    return value


def normalize_simple_city(text):
    value = strip_state_suffix(text)
    value = re.sub(r"\s+", " ", value).strip()

    if not value:
        return ""

    key = canonical(value)
    if key in CITY_ALIASES:
        return CITY_ALIASES[key]

    return smart_title(value)


def normalize_city(city, record_name=""):
    raw = (city or "").strip()
    name_key = canonical(record_name)

    if not raw:
        return ""

    if "titulo (nome da atividade)" in canonical(raw) and "passa quatro" in name_key:
        return "Passa Quatro"

    if raw.startswith("Visconde de Maua:") or raw.startswith("Visconde de Mauá:"):
        return "Itatiaia"

    if canonical(raw).startswith("posto oceanografico da ilha da trindade"):
        return "Ilha da Trindade"

    if "as atividades serao realizadas" in canonical(raw):
        return "Rio Tinto e Mamanguape"

    raw = re.sub(r"\s*\(duas entradas principais\)", "", raw, flags=re.IGNORECASE)
    raw = re.sub(r"\s*-\s*Uso Publico FEENA$", "", raw, flags=re.IGNORECASE)
    raw = re.sub(r"\s*-\s*Uso Público FEENA$", "", raw, flags=re.IGNORECASE)

    if "/" in raw and "vila de sao jorge" in canonical(raw):
        raw = raw.split("/")[0]

    raw = strip_state_suffix(raw)

    separators = []
    if "," in raw:
        separators.append(",")
    if " / " in raw:
        separators.append("/")
    if re.search(r"\s+e\s+", raw, flags=re.IGNORECASE):
        separators.append(" e ")

    if separators:
        parts = re.split(r"\s*,\s*|\s*/\s*|\s+e\s+", raw)
        normalized_parts = [normalize_simple_city(part) for part in parts if normalize_simple_city(part)]
        joiner = ", "
        if " e " in separators and len(normalized_parts) == 2 and "," not in raw and "/" not in raw:
            joiner = " e "
        return joiner.join(normalized_parts)

    return normalize_simple_city(raw)


def normalize_base_json(path):
    payload = json.loads(path.read_text(encoding="utf-8"))
    changed = 0

    for record in payload.get("records", []):
        normalized = record.get("normalized", {})
        current = normalized.get("municipio", "")
        original = normalized.get("municipio_original", current)
        cleaned = normalize_city(original, normalized.get("nome", ""))

        if cleaned and cleaned != current:
            normalized.setdefault("municipio_original", original)
            normalized["municipio"] = cleaned
            changed += 1

    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    return changed


def normalize_map_data(path):
    payload = json.loads(path.read_text(encoding="utf-8"))
    changed = 0

    for item in payload.get("items", []):
        current = item.get("city", "")
        original = item.get("city_original", current)
        cleaned = normalize_city(original, item.get("name", ""))

        if cleaned and cleaned != current:
            item.setdefault("city_original", original)
            item["city"] = cleaned
            changed += 1

    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    return changed


def normalize_geocoded_json(path):
    payload = json.loads(path.read_text(encoding="utf-8"))
    changed = 0

    for record in payload.get("records", []):
        normalized = record.get("normalized", {})
        current = normalized.get("municipio", "")
        original = normalized.get("municipio_original", current)
        cleaned = normalize_city(original, normalized.get("nome", ""))

        if cleaned and cleaned != current:
            normalized.setdefault("municipio_original", original)
            normalized["municipio"] = cleaned
            changed += 1

    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    return changed


def main():
    results = []

    for filename, normalizer in (
        ("base.json", normalize_base_json),
        ("base-geocoded.json", normalize_geocoded_json),
        ("map-data.json", normalize_map_data),
    ):
        path = Path(filename)
        if path.exists():
            results.append((filename, normalizer(path)))

    for filename, changed in results:
        print(f"{filename}: {changed} cidades normalizadas")


if __name__ == "__main__":
    main()

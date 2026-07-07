import json
import re
import time
import unicodedata
import urllib.parse
import urllib.request
from pathlib import Path

from normalize_cities import normalize_city


BASE_PATH = Path("base.json")
GEOCODED_PATH = Path("base-geocoded.json")
MAP_DATA_PATH = Path("map-data.json")
USER_AGENT = "UCMapBuilder/1.0 (local dev geocoder)"
REQUEST_INTERVAL_SECONDS = 1.1


def load_records():
    payload = json.loads(BASE_PATH.read_text(encoding="utf-8"))
    return payload


def normalize_ascii(text):
    text = unicodedata.normalize("NFD", text or "")
    text = "".join(char for char in text if unicodedata.category(char) != "Mn")
    return text


def clean_whitespace(text):
    return re.sub(r"\s+", " ", (text or "")).strip(" ,.-")


def clean_city(text):
    return clean_whitespace(normalize_city(text))


def clean_address(text):
    value = (text or "").strip()
    if not value:
        return ""

    value = re.sub(r"https?://\S+", "", value, flags=re.IGNORECASE)
    value = re.sub(r"Link de localiza[cç][aã]o.*$", "", value, flags=re.IGNORECASE)
    value = re.sub(r"O ponto de encontro.*?ser[aá]\s+em:\s*", "", value, flags=re.IGNORECASE)
    value = re.sub(r'Por NO GPS\s*"([^"]+)"', r"\1", value, flags=re.IGNORECASE)
    value = re.sub(r"\bsem n[uú]mero\b", "s/n", value, flags=re.IGNORECASE)
    value = clean_whitespace(value)
    return value


def extract_coordinates_from_text(text):
    if not text:
        return None

    match = re.search(r"@(-?\d+\.\d+),(-?\d+\.\d+)", text)
    if match:
        return float(match.group(1)), float(match.group(2)), "google_url"

    match = re.search(r"ll=(-?\d+\.\d+),(-?\d+\.\d+)", text)
    if match:
        return float(match.group(1)), float(match.group(2)), "query_param"

    return None


def geocode_query(query, cache):
    query = clean_whitespace(query)
    if not query:
        return None

    cache_key = normalize_ascii(query).lower()
    if cache_key in cache:
        return cache[cache_key]

    encoded_query = urllib.parse.quote(query)
    url = f"https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=br&q={encoded_query}"
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept-Language": "pt-BR,pt;q=0.9"})

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except Exception:
        payload = []

    time.sleep(REQUEST_INTERVAL_SECONDS)

    if payload:
        result = {
            "lat": float(payload[0]["lat"]),
            "lng": float(payload[0]["lon"]),
            "display_name": payload[0].get("display_name", ""),
            "type": payload[0].get("type", ""),
        }
    else:
        result = None

    cache[cache_key] = result
    return result


def geocode_record(record, cache):
    data = record["normalized"]
    name = clean_whitespace(data.get("nome", ""))
    state = clean_whitespace(data.get("estado", ""))
    city = clean_city(data.get("municipio", ""))
    postal_code = clean_whitespace(data.get("cep", ""))
    address = clean_address(data.get("endereco", ""))
    explicit_link = clean_whitespace(data.get("link_do_endereco", ""))

    combined_text = " ".join(filter(None, [explicit_link, data.get("endereco", "")]))
    direct_coordinates = extract_coordinates_from_text(combined_text)
    if direct_coordinates:
        lat, lng, source = direct_coordinates
        return {
            "lat": lat,
            "lng": lng,
            "source": source,
            "precision": "exact",
            "query": "",
            "display_name": "",
        }

    queries = []

    if postal_code and city and state:
        queries.append(("postal_code", f"{postal_code}, {city}, {state}, Brasil"))
    if address and city and state:
        queries.append(("address", f"{address}, {city}, {state}, Brasil"))
    if name and city and state:
        queries.append(("name_city", f"{name}, {city}, {state}, Brasil"))
    if city and state:
        queries.append(("city", f"{city}, {state}, Brasil"))

    for source, query in queries:
        result = geocode_query(query, cache)
        if result:
            precision = "exact" if source in {"postal_code", "address"} else "approximate"
            if source == "city":
                precision = "municipality"
            return {
                "lat": result["lat"],
                "lng": result["lng"],
                "source": source,
                "precision": precision,
                "query": query,
                "display_name": result["display_name"],
            }

    return {
        "lat": None,
        "lng": None,
        "source": "not_found",
        "precision": "not_found",
        "query": "",
        "display_name": "",
    }


def build_map_item(record, location):
    normalized = record["normalized"]
    activities = normalized.get("atividades_lista", [])
    first_activity = activities[0] if activities else {}

    return {
        "id": record["id"],
        "name": normalized.get("nome", ""),
        "state": normalized.get("estado", ""),
        "city": normalized.get("municipio", ""),
        "biome": normalized.get("bioma", ""),
        "activity": first_activity.get("titulo") or "Atividade",
        "lat": location["lat"],
        "lng": location["lng"],
        "description": first_activity.get("descricao") or normalized.get("descricao", "") or normalized.get("endereco", ""),
        "tags": [value for value in [normalized.get("bioma", ""), first_activity.get("dificuldade", ""), normalized.get("estado", "")] if value],
        "responsavel": normalized.get("responsavel_atividade", ""),
        "email": normalized.get("email", ""),
        "whatsapp": normalized.get("whatsapp", ""),
        "social": normalized.get("social", ""),
        "cep": normalized.get("cep", ""),
        "endereco": normalized.get("endereco", ""),
        "link_do_endereco": normalized.get("link_do_endereco", ""),
        "atividades": activities,
        "location_meta": {
            "source": location["source"],
            "precision": location["precision"],
            "query": location["query"],
            "display_name": location["display_name"],
        },
    }


def main():
    payload = load_records()
    cache = {}
    enriched_records = []
    map_items = []

    for index, record in enumerate(payload["records"], start=1):
        location = geocode_record(record, cache)
        enriched_record = {
            **record,
            "location": location,
        }
        enriched_records.append(enriched_record)

        if location["lat"] is not None and location["lng"] is not None:
            map_items.append(build_map_item(record, location))

        print(f"[{index}/{len(payload['records'])}] {record['normalized'].get('nome', '')} -> {location['source']} ({location['precision']})")

    geocoded_payload = {
        **payload,
        "geocoding": {
            "provider": "OpenStreetMap Nominatim",
            "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S"),
            "total_with_coordinates": len(map_items),
            "total_without_coordinates": len(payload["records"]) - len(map_items),
        },
        "records": enriched_records,
    }

    map_payload = {
        "source_file": payload["source_file"],
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S"),
        "total_items": len(map_items),
        "items": map_items,
    }

    GEOCODED_PATH.write_text(json.dumps(geocoded_payload, ensure_ascii=False, indent=2), encoding="utf-8")
    MAP_DATA_PATH.write_text(json.dumps(map_payload, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"\nGerado {GEOCODED_PATH} com {len(enriched_records)} registros.")
    print(f"Gerado {MAP_DATA_PATH} com {len(map_items)} pins.")


if __name__ == "__main__":
    main()

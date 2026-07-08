# API No Parque

Plugin WordPress de integracao entre a base JSON e o plugin legado **Um Dia No Parque**.

## Objetivo

- Importar `map-data.json`, `base.json` ou `base-geocoded.json`.
- Popular os CPTs/taxonomias do plugin legado sem alterar o plugin legado.
- Expor uma REST API propria no formato usado pelos componentes `[uc_map]` e `[uc_list]`.

## Endpoints

```txt
GET  /wp-json/api-no-parque/v1/status
GET  /wp-json/api-no-parque/v1/map
GET  /wp-json/api-no-parque/v1/list
GET  /wp-json/api-no-parque/v1/ucs
GET  /wp-json/api-no-parque/v1/ucs/{id}
POST /wp-json/api-no-parque/v1/ucs
PUT  /wp-json/api-no-parque/v1/ucs/{id}
DELETE /wp-json/api-no-parque/v1/ucs/{id}
POST /wp-json/api-no-parque/v1/import
```

Rotas de escrita exigem usuario autenticado com `manage_options`.

## Importacao pelo admin

No WordPress:

```txt
Ferramentas > API No Parque
```

Envie o JSON e rode a importacao. Ha uma opcao de simulacao sem gravar dados.

## Dependencia

O plugin legado precisa estar ativo, pois este plugin usa:

- CPT `uc`
- CPT `atividade`
- CPT `uf`
- taxonomia `bioma`
- taxonomia `cidade`
- taxonomias de atividade `dificuldade`, `publico`, `tipo_atividade`

## Metas adicionais

Este plugin adiciona metadados proprios para preservar dados que o legado nao persiste:

- `_api_np_source_id`
- `_api_np_lat`
- `_api_np_lng`
- `_api_np_location_source`
- `_api_np_location_precision`
- `_api_np_location_query`
- `_api_np_location_display_name`
- `_api_np_activity_source_key`

## Campos retornados nos GETs

Os endpoints de leitura retornam a estrutura principal usada pelos componentes e tambem campos extras do WordPress/legado:

- `url`
- `slug`
- `title`
- `content`
- `excerpt`
- `image`
- `image_url`
- `thumbnail`
- `meta`

O campo `image` inclui:

```json
{
  "id": 0,
  "url": "",
  "alt": "",
  "caption": "",
  "sizes": {
    "thumbnail": "",
    "medium": "",
    "medium_large": "",
    "large": "",
    "full": ""
  }
}
```

As atividades dentro de `atividades` tambem trazem `url`, `slug`, `content`, `excerpt`, `image`, `image_url`, `thumbnail` e `meta`.

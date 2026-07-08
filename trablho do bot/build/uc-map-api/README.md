# UC Map

Plugin WordPress para exibir o mapa e a lista de Unidades de Conservacao no Elementor.

## Informacoes do plugin

- Nome: UC Map
- Versao: 1.3.4
- Autor: ACDEV
- Site do plugin: https://acdev.com.br
- Site do autor: https://acdev.com.br
- Licenca: GPL-2.0-or-later
- WordPress minimo: 6.0
- PHP minimo: 7.4

## Como usar

1. Copie a pasta `pluginWpMap2` para `wp-content/plugins/`.
2. No painel do WordPress, ative o plugin **UC Map**.
3. No Elementor, adicione um widget **Shortcode**.
4. Use um dos shortcodes:

```txt
[uc_map]
```

```txt
[uc_list]
```

`[uc_map]` exibe o mapa interativo.
`[uc_list]` exibe a lista com filtros por busca, bioma e UF. Ao clicar em **Ver Atividades**, abre a janela com as atividades da UC.

Em paginas individuais do post type `uc`, o plugin hidrata os campos mockados do Elementor com dados reais da API, preservando o layout existente.

O plugin carrega seus proprios assets em `assets/`:

- `app.js`
- `list.js`
- `single.js`
- `styles.css`
- `map-data.json`

## Fonte dos dados

Por padrao, os componentes consomem a API do plugin **api-no-parque**:

- `[uc_map]`: `/wp-json/api-no-parque/v1/map?per_page=500`
- `[uc_list]`: `/wp-json/api-no-parque/v1/list?per_page=500`

O arquivo `assets/map-data.json` fica apenas como fallback caso a API esteja indisponivel, o que ajuda em testes locais e previews.

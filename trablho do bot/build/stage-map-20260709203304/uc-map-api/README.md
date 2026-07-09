# UC Map

Plugin WordPress para exibir o mapa e a lista de Unidades de Conservacao no Elementor.

## Informacoes do plugin

- Nome: UC Map
- Versao: 1.7.5
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

```txt
[uc_parceiro categoria="parceiros-da-edicao-de-2026"]
```

```txt
[uc_tipos_atividade]
```

```txt
[uc_oque_levar]
```

```txt
[uc_depoimento]
```

```txt
[uc_depoimento id="123"]
```

```txt
[uc_depoimento slider="sim" quantidade="6"]
```

`[uc_map]` exibe o mapa interativo.
`[uc_list]` exibe a lista com filtros por busca, bioma e UF. Ao clicar em **Ver Atividades**, abre a janela com as atividades da UC.
`[uc_parceiro]` exibe apenas um slider de logos de parceiros filtrado pelo slug da taxonomia `categoria_parceiro`.
`[uc_tipos_atividade]` exibe um slider continuo com todos os termos existentes de `tipo_atividade` e seus icones Font Awesome.
`[uc_oque_levar]` exibe, na pagina da UC atual, um slider com os itens de `O que levar` selecionados no cadastro da UC. Tambem aceita `id`, por exemplo `[uc_oque_levar id="123"]`.
`[uc_depoimento]` exibe um depoimento aleatorio ou um depoimento especifico por `id`. Com `slider="sim"`, exibe um carrossel de depoimentos; `quantidade` controla quantos itens entram no slider.

Em paginas individuais do post type `uc`, o plugin hidrata os campos mockados do Elementor com dados reais da API, preservando o layout existente.

O plugin carrega seus proprios assets em `assets/`:

- `app.js`
- `list.js`
- `testimonials.js`
- `single.js`
- `styles.css`
- `map-data.json`

## Fonte dos dados

Por padrao, os componentes consomem a API do plugin **api-no-parque**:

- `[uc_map]`: `/wp-json/api-no-parque/v1/map?per_page=500`
- `[uc_list]`: `/wp-json/api-no-parque/v1/list?per_page=500`

O arquivo `assets/map-data.json` fica apenas como fallback caso a API esteja indisponivel, o que ajuda em testes locais e previews.

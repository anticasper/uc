# Trabalho do bot

Esta pasta separa os arquivos gerados durante o trabalho e remove da raiz respostas temporarias, pacotes e caches.

## Estrutura

- `plugins/`: codigo-fonte dos plugins criados ou alterados.
- `build/`: pacotes temporarios montados para upload no WordPress.
- `pacotes-zip/`: pacotes prontos para upload no WordPress.
- `backups/`: backups gerados antes de importacoes ou limpezas.
- `dados-gerados/`: bases intermediarias geradas durante importacao ou saneamento.
- `retornos-wordpress/`: respostas HTML/JSON capturadas durante testes, uploads, importacoes e chamadas da API.
- `cache-python/`: caches Python que estavam na raiz.
- `sensivel-nao-subir/`: arquivos temporarios sensiveis, como cookies de sessao. Nao envie estes arquivos para repositorios, clientes ou hospedagem.

## Pacotes principais

- `pacotes-zip/pluginWpMap2.zip`: plugin dos shortcodes `[uc_map]` e `[uc_list]`.
- `pacotes-zip/uc-map-api.zip`: versao instalada no site com mapa, lista e paginas de UC consumindo a API.
- `pacotes-zip/api-no-parque.zip`: plugin da API e importacao dos dados.
- `pacotes-zip/api-no-parque-2.zip`: versao instalada no site ativo.

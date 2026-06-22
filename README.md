# IFRS Defeso Eleitoral

Plugin WordPress para ocultar conteúdos publicados antes de uma data de corte em toda a rede multisite, atendendo à legislação de defeso eleitoral brasileira.

## Descrição

Durante o período eleitoral, a legislação brasileira restringe a divulgação de conteúdos institucionais por órgãos públicos. Este plugin filtra automaticamente posts (notícias) anteriores à data de corte definida, impedindo sua exibição em listagens, arquivos, buscas, feeds e páginas individuais.

## Requisitos

- WordPress 5.3+
- PHP 8.0+
- Ambiente multisite (recomendado como must-use plugin)

## Instalação

Copie o arquivo `ifrs-wp-defeso-eleitoral.php` para o diretório `wp-content/mu-plugins/` do seu WordPress. Por ser um must-use plugin, ele é carregado automaticamente, sem necessidade de ativação manual.

## Configuração

As opções são definidas via constantes PHP, preferencialmente no `wp-config.php` ou em um arquivo de configuração do ambiente, **antes** do carregamento do plugin.

| Constante | Padrão | Descrição |
|---|---|---|
| `IFRS_DEFESO_ENABLED` | `true` | Ativa ou desativa o plugin |
| `IFRS_DEFESO_CUTOFF` | `'2026-01-01 00:00:00'` | Data/hora de corte (fuso horário do site) |
| `IFRS_DEFESO_POST_TYPES` | `['post']` | Array com os post types filtrados |
| `IFRS_DEFESO_BLOCK_SINGLES` | `true` | Retorna 404 para posts individuais anteriores ao corte |
| `IFRS_DEFESO_APPLY_TO_FEEDS` | `true` | Aplica o filtro também aos feeds RSS/Atom |

### Exemplo

```php
// wp-config.php
define( 'IFRS_DEFESO_ENABLED', true );
define( 'IFRS_DEFESO_CUTOFF', '2026-04-06 00:00:00' );
define( 'IFRS_DEFESO_POST_TYPES', array( 'post', 'noticias' ) );
define( 'IFRS_DEFESO_BLOCK_SINGLES', true );
define( 'IFRS_DEFESO_APPLY_TO_FEEDS', true );
```

## Comportamento

- **Listagens, arquivos e buscas:** posts anteriores à data de corte são excluídos das queries principais.
- **REST API:** o filtro é aplicado nas rotas padrão de posts e na busca via API.
- **Posts individuais:** se `IFRS_DEFESO_BLOCK_SINGLES` estiver ativo, acessar a URL de um post anterior ao corte retorna HTTP 404.
- **Painel administrativo:** nenhum conteúdo é ocultado no admin.

## Licença

Esse código é distribuído sob a licença [GPLv3](https://www.gnu.org/licenses/gpl-3.0.html).

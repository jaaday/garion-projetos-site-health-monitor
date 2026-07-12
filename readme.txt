=== WP Site Health Monitor ===
Contributors: garionprojetos
Tags: health, monitoring, diagnostics, security, maintenance
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Painel de diagnostico para WordPress: PHP, SSL, plugins/temas desatualizados, cron, disco e banco de dados.

== Description ==

WP Site Health Monitor centraliza verificacoes de saude do site em um unico painel:

* Versao do WordPress
* Versao do PHP
* Certificado SSL
* Plugins e temas desatualizados
* Espaco em disco
* Status dos cron jobs
* Erros recentes (debug.log)
* Permissoes de arquivos
* Status do banco de dados
* Cache ativo
* Conexao com servicos externos configurados no site

Este plugin nao envia dados para servidores externos. Todas as verificacoes rodam localmente no seu servidor.

== Installation ==

1. Envie a pasta do plugin para `/wp-content/plugins/`.
2. Ative o plugin em "Plugins" no painel do WordPress.
3. Acesse "Ferramentas > WP Site Health Monitor" para ver o painel de diagnostico.

== Frequently Asked Questions ==

= Este plugin envia dados para servicos externos? =

Nao. O diagnostico e feito localmente, consultando apenas o proprio ambiente do site.

== Changelog ==

= 0.1.0 =
* Versao inicial do plugin.

== Upgrade Notice ==

= 0.1.0 =
Versao inicial.

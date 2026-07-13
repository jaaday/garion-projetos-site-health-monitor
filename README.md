# Garion Projetos — Site Health Monitor

Painel WordPress que verifica a saúde geral do site.

## Verificações planejadas

- Versão do WordPress
- Versão do PHP
- Certificado SSL
- Plugins desatualizados
- Temas desatualizados
- Espaço em disco
- Status dos cron jobs
- Erros recentes (debug.log)
- Permissões de arquivos
- Status do banco de dados
- Cache ativo
- Conexão com serviços externos

## Requisitos

- WordPress 6.x+
- PHP 8.0+

## Estrutura

```
garion-projetos-site-health-monitor/
├── garion-projetos-site-health-monitor.php
├── includes/
│   └── checks/         # uma classe por verificação
├── admin/
└── assets/
```

## Status

🚧 Em desenvolvimento inicial.

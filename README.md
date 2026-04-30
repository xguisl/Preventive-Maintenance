# Plugin de Manutenção Preventiva para GLPI

## 📌 Visão Geral
Plugin para agendar, monitorar e automatizar manutenções preventivas de computadores no GLPI, com:
- Cadastro de planos de manutenção por entidade
- Alertas visuais
- Geração automática de tickets para manutenções urgentes
- Atualização automática da data de manutenção assim que o chamado é resolvido
- Lista de manutenção separada por entidade
- Filtro avançado na lista

## 🚀 Instalação
Baixe a última versão do GitHub

Extraia para: `/glpi/plugins/preventivemaintenance`

Ative o plugin em: Configurações > Plugins
Configure permissões para grupos técnicos

### Requisitos
- GLPI 10.x
- PHP 7.4+

### Configuração do Cron Job
Para que as funcionalidades automáticas funcionem corretamente, configure as tarefas cron no GLPI:

1. Acesse: Configurações > Tarefas Automáticas (Cron)
2. Configure as seguintes tarefas:
   - **Plugin Preventive Maintenance - Auto Ticket**: Executa a cada hora para criar tickets automáticos
   - **Plugin Preventive Maintenance - Notifications**: Executa a cada hora para enviar notificações

3. Certifique-se de que o cron do sistema esteja configurado para executar o script cron.php do GLPI:
   ```bash
   # Exemplo de configuração no crontab do Linux
   * * * * * /usr/bin/php /path/to/glpi/front/cron.php
   ```

## 🔧 Funcionalidades Principais

### 1. Agendamento Inteligente
- Defina intervalos personalizados (diário, semanal, mensal, anual)
- Calendário interativo com cálculo automático de datas

### 2. Automatização
- Tickets automáticos para manutenções atrasadas (configurável via cron job)
- Atualização da data de manutenção automática se Tickets automáticos estiver habilitado
- Status visual por cores (✅ Em dia / ⚠️ Atenção / ❌ Urgente)
- Filtros por técnico, entidade ou data
- Progresso em porcentagem para cada item

### 3. Notificações
- Envio de notificações por e-mail
- Integração com Microsoft Teams via webhook
- Configuração de dias antes para alertas

## ⚙ Configuração
Acesse: Configurações > Plugins > Manutenção Preventiva

- Auto Ticket: Habilite/desative a criação automática
- Permissões: Controle acesso por perfil (leitura, edição, exclusão)
- Notificações: Configure e-mail e webhook do Teams

## 📊 Integrações
- GLPI Tickets: Vincula manutenções a tickets existentes
- Inventário: Mostra dados do item (serial, modelo, localização)

## 📜 Licença
Licenciado sob GNU GPLv2+ - Ver licença completa.

Desenvolvido por:
© 2025 WIDA - Work Information Development Analytics
www.widatecnologia.com.br

🔧 Manutenção preventiva = Menos falhas + Mais produtividade!

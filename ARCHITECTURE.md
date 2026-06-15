# Smart Lighting Control System

Sistema de controle inteligente de iluminação com suporte a Home Assistant, agendamentos automáticos e transições de cores suave.

## 🎯 Objetivo

Fornecer uma solução completa para controlar uma luminária colorida que não possui controle de cor nativo via Home Assistant. O sistema utiliza ciclos de liga/desliga para navegar entre as cores disponíveis.

## ✨ Recursos Principais

### 1. Controle Manual
- Interface web intuitiva (Dashboard)
- API RESTful para integração
- Ligar/desligar com um clique

### 2. Seleção de Cores
- 6 cores pré-configuradas (Colorido, Vermelho, Verde, Azul, Amarelo, Branco)
- Transição automática entre cores
- Progresso em tempo real com barra visual

### 3. Sistema de Agendamentos
- Criar timers para ligar/desligar em horários específicos
- Executar cor de destino automaticamente
- Agendamentos persistentes (sobrevivem a reinicializações)

### 4. Monitoramento
- Dashboard com status em tempo real
- Logs detalhados de todas as operações
- API de logs para auditoria

## 🏗️ Arquitetura

```
┌─────────────────────────────────────────────────────┐
│                   Web Dashboard                      │
│               (HTML + JavaScript)                    │
└────────────────────┬────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────┐
│              API RESTful (PHP)                       │
│  ├─ State Management (state.php)                    │
│  ├─ Light Control (on.php, off.php)               │
│  ├─ Color Management (colors.php)                  │
│  ├─ Transition Tracking (progress.php, step.php)  │
│  ├─ Timer Management (timers_*.php)               │
│  └─ Logging (logs.php)                            │
└────┬───────────────────────────────────┬───────────┘
     │                                   │
     ↓                                   ↓
┌─────────────────────┐      ┌──────────────────────┐
│    JSON Database    │      │   Logger (Files)     │
│  ├─ state.json      │      │  ├─ app.log          │
│  ├─ progress.json   │      │  └─ audit.log        │
│  ├─ timers.json     │      └──────────────────────┘
│  └─ queue.json      │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────────────────────────────────────┐
│           Cron Job (execute_timers.php)             │
│    (Executa a cada minuto, verifica timers)        │
└────────────────────┬────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────┐
│        Home Assistant Client (PHP)                  │
│  └─ Comunica via API REST com Home Assistant      │
└────────────────────┬────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────┐
│          Home Assistant Instance                    │
│  └─ Controla a entidade de luz (light.*)          │
└────────────────────┬────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────┐
│             Luminária Inteligente                   │
│        (Responde a comandos on/off)                │
└─────────────────────────────────────────────────────┘
```

## 🔄 Fluxo de Transição de Cores

### Situação: Mudar de Vermelho para Azul

1. **Usuário clica em "Azul"** no dashboard
2. **API calcula o caminho**: Vermelho → Verde → Azul (2 passos)
3. **Sistema inicia transição**:
   - Desliga a luz
   - Ligua novamente → Avança para Verde
   - [Aguarda INTERVAL_TIME segundos]
   - Desliga a luz
   - Liga novamente → Avança para Azul
   - Transição completa!

### Detalhes Técnicos

- **Cada ciclo liga/desliga** avança uma cor na sequência
- **Duração estimada**: `(número_de_passos × INTERVAL_TIME)` segundos
- **Progresso**: Rastreado em `progress.json` e via API `/progress`
- **Persistência**: Se o servidor cair, a transição continua ao reiniciar

## 📊 Fluxo de Agendamentos

### Exemplo: Timer para ligar às 7h com cor branca

```
06:59:59 → Cron job executa execute_timers.php
          ↓
         Verifica se há timers pendentes
          ↓
         Encontra: morning_on (07:00, type=on, color=branco)
          ↓
         Valida se já foi executado hoje (não)
          ↓
         Liga a luz via Home Assistant
          ↓
         Calcula caminho para cor branca
          ↓
         Inicia transição automática
          ↓
         Marca como executado (last_executed)
```

## 🗄️ Estrutura de Dados

### state.json
```json
{
  "state": "on",
  "current_color": "azul",
  "last_updated": "2026-06-15T10:30:00",
  "is_executing": false
}
```

### progress.json
```json
{
  "is_executing": true,
  "current_step": 1,
  "total_steps": 3,
  "from_color": "vermelho",
  "to_color": "azul",
  "path": ["vermelho", "verde", "azul"],
  "started_at": "2026-06-15T10:30:00",
  "estimated_completion": "2026-06-15T10:31:30"
}
```

### timers.json
```json
{
  "morning_on": {
    "id": "morning_on",
    "type": "on",
    "time": "07:00",
    "enabled": true,
    "target_color": "branco",
    "created_at": "2026-06-15T10:00:00",
    "last_executed": "2026-06-15T07:00:00"
  }
}
```

## 🔌 Integração com Home Assistant

### Requisitos
1. Home Assistant instalado e acessível
2. Token de acesso (gerado em Configurações → Chaves de Acesso)
3. Entity ID da luz configurada

### Configuração em Home Assistant

```yaml
# configuration.yaml
automation:
  - alias: "Sincronizar luz com Smart Lighting"
    trigger:
      platform: state
      entity_id: light.minha_luz
    action:
      - service: rest_command.sync_smartlighting
        data:
          state: "{{ trigger.to_state.state }}"

rest_command:
  sync_smartlighting:
    url: "http://seu-servidor/api/sync"
    method: POST
    payload: '{"state": "{{ state }}"}'
```

## 🚀 Performance e Escalabilidade

### Características de Design
- **Sem banco de dados**: Uso de JSON para simplicidade
- **Arquivos com lock**: Evita condições de corrida
- **Cron job otimizado**: Executa rapidamente, saindo se já em execução
- **Logs rotacionáveis**: Não crescem indefinidamente
- **API stateless**: Cada requisição é independente

### Limitações Conhecidas
- Máximo ~1000 entradas de log antes de rotação
- Até 100 timers (limite prático)
- Uma transição por vez (fila de tamanho 1)

## 🔒 Segurança

### Implementado
- Validação de entrada em todos endpoints
- Sanitização de strings
- Logs de auditoria
- Proteção contra race conditions (file locks)

### Recomendações
- Use HTTPS em produção
- Proteja `/cron/` com `.htaccess` ou firewall
- Implemente autenticação em `/api/` se exposto à internet
- Revise logs regularmente

## 📋 Checklist de Instalação

- [ ] PHP 7.4+ instalado
- [ ] Home Assistant acessível
- [ ] Token de acesso HA gerado
- [ ] `.env` configurado
- [ ] Diretórios criados (data, logs, tmp)
- [ ] Permissões configuradas (755 para diretórios)
- [ ] Cron job configurado
- [ ] Dashboard acessível via browser
- [ ] Teste de ligar/desligar funciona
- [ ] Logs aparecem em `/logs/`

## 🐛 Debugging

### Verificar status do cron
```bash
# Ver últimas execuções
tail -f /var/log/syslog | grep execute_timers

# Executar manualmente para testar
php /caminho/para/cron/execute_timers.php
```

### Verificar conectividade com HA
```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  http://192.168.1.100:8123/api/states
```

### Ver logs da aplicação
```bash
tail -100 logs/app.log
```

## 📞 Suporte

Para problemas, verifique:
1. Logs em `/logs/app.log`
2. Conectividade com Home Assistant
3. Permissões de arquivo
4. Variáveis de ambiente em `.env`

---

**Versão**: 1.0.0  
**Última atualização**: 2026-06-15  
**Autor**: JCasallecchio

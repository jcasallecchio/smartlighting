# Smart Lighting Control System

Sistema inteligente de controle de iluminação com suporte a Home Assistant, agendamentos e transições de cores.

## 🎯 Características

- **Controle de Luz**: Ligar/desligar luminária via Home Assistant
- **Ciclo de Cores**: 5 cores diferentes com transições automáticas
- **Agendamentos**: Timers para ligar/desligar em horários específicos
- **Progresso em Tempo Real**: Acompanhe transições de cor com barra de progresso
- **API RESTful**: Endpoints completos para integração
- **Logs Detalhados**: Rastreamento completo de todas as operações
- **Dashboard Web**: Interface intuitiva para controle

## 🚀 Instalação

### Pré-requisitos

- PHP 7.4+
- Home Assistant com token de acesso
- Servidor web (Apache/Nginx)
- SQLite (opcional, para logs)

### Setup

1. **Clone o repositório**
```bash
git clone https://github.com/jcasallecchio/smartlighting.git
cd smartlighting
```

2. **Configure variáveis de ambiente**
```bash
cp .env.example .env
```

Edite `.env` com suas configurações:
```env
# Home Assistant
HA_URL=http://192.168.1.100:8123
HA_TOKEN=seu_token_aqui
ENTITY_ID=light.sua_luz

# Timing
INTERVAL_TIME=30  # Segundos entre ciclos de cor

# Logging
LOG_LEVEL=INFO
LOG_PATH=./logs
```

3. **Crie diretórios necessários**
```bash
mkdir -p data logs tmp
chmod 755 data logs tmp
```

4. **Configure cron job** (execute a cada minuto)
```bash
* * * * * php /caminho/para/smartlighting/cron/execute_timers.php
```

Ou use o scheduler do seu host:
```bash
# Cron em Linux/Mac
*/5 * * * * php /home/usuario/smartlighting/cron/execute_timers.php

# Task Scheduler no Windows
scheduletasks /create /tn SmartLighting /tr "C:\PHP\php.exe C:\smartlighting\cron\execute_timers.php" /sc minute /mo 1
```

5. **Acesse o dashboard**
```
http://seu-servidor/smartlighting/public/
```

## 📋 API Endpoints

### Estado e Controle

#### GET `/api/state`
Obtém o estado atual da luminária.

```json
{
  "success": true,
  "data": {
    "state": "on",
    "current_color": "azul",
    "last_updated": "2026-06-15T10:30:00",
    "is_executing": false,
    "execution_progress": null
  }
}
```

#### POST `/api/on`
Liga a luminária e define cor de destino.

```bash
curl -X POST http://localhost/api/on \
  -H "Content-Type: application/json" \
  -d '{"color": "vermelho"}'
```

Cores disponíveis: `colorido`, `vermelho`, `verde`, `azul`, `amarelo`, `branco`

#### POST `/api/off`
Desliga a luminária.

```bash
curl -X POST http://localhost/api/off
```

### Cores

#### GET `/api/colors`
Obtém lista de cores disponíveis.

```json
{
  "success": true,
  "data": {
    "colors": [
      {
        "name": "colorido",
        "hex": "#FF00FF",
        "order": 0
      }
    ],
    "total": 6
  }
}
```

### Progresso

#### GET `/api/progress`
Obtém progresso da transição de cor em andamento.

```json
{
  "success": true,
  "data": {
    "is_executing": true,
    "current_step": 2,
    "total_steps": 4,
    "progress_percentage": 50,
    "from_color": "vermelho",
    "to_color": "azul",
    "time_info": {
      "elapsed_seconds": 60,
      "estimated_seconds_remaining": 60
    }
  }
}
```

### Agendamentos (Timers)

#### GET `/api/timers`
Lista todos os agendamentos.

```json
{
  "success": true,
  "data": {
    "timers": [
      {
        "id": "morning_on",
        "type": "on",
        "time": "07:00",
        "enabled": true,
        "target_color": "branco",
        "created_at": "2026-06-15T10:00:00",
        "last_executed": null
      }
    ],
    "total": 1
  }
}
```

#### POST `/api/timers/create`
Cria novo agendamento.

```bash
curl -X POST http://localhost/api/timers/create \
  -H "Content-Type: application/json" \
  -d '{
    "id": "morning_on",
    "type": "on",
    "time": "07:00",
    "enabled": true,
    "target_color": "branco"
  }'
```

#### PUT `/api/timers/update`
Atualiza um agendamento.

```bash
curl -X PUT http://localhost/api/timers/update \
  -H "Content-Type: application/json" \
  -d '{
    "id": "morning_on",
    "time": "07:30",
    "enabled": false
  }'
```

#### DELETE `/api/timers/delete`
Deleta um agendamento.

```bash
curl -X DELETE http://localhost/api/timers/delete \
  -H "Content-Type: application/json" \
  -d '{"id": "morning_on"}'
```

### Logs

#### GET `/api/logs`
Obtém logs da aplicação com filtros opcionais.

```bash
# Todos os logs
curl http://localhost/api/logs

# Filtrar por data
curl http://localhost/api/logs?date=2026-06-15

# Filtrar por nível
curl http://localhost/api/logs?level=ERROR

# Buscar
curl http://localhost/api/logs?search=timer

# Paginação
curl http://localhost/api/logs?limit=50&offset=100
```

## 🎨 Sequência de Cores

A luminária tem 5 cores principais em sequência:

1. **Colorido** (Magenta) → Inicial/Padrão
2. **Vermelho**
3. **Verde**
4. **Azul**
5. **Amarelo**
6. **Branco**

Para mudar de uma cor para outra:
- O sistema calcula o caminho mais curto
- Realiza ciclos de desliga/liga para avançar
- Cada ciclo leva ~30 segundos (configurável)
- Progresso é rastreado em tempo real

## ⏰ Sistema de Agendamentos

### Criando um Timer

**Exemplo: Ligar luz à noite com cor branca**

```bash
curl -X POST http://localhost/api/timers/create \
  -H "Content-Type: application/json" \
  -d '{
    "id": "evening_on",
    "type": "on",
    "time": "18:00",
    "enabled": true,
    "target_color": "branco"
  }'
```

**Exemplo: Desligar luz à noite**

```bash
curl -X POST http://localhost/api/timers/create \
  -H "Content-Type: application/json" \
  -d '{
    "id": "night_off",
    "type": "off",
    "time": "23:00",
    "enabled": true
  }'
```

## 🔧 Estrutura de Arquivos

```
smartlighting/
├── public/
│   ├── index.html           # Dashboard web
│   └── api/
│       ├── state.php        # Obtém estado
│       ├── on.php           # Ligar
│       ├── off.php          # Desligar
│       ├── colors.php       # Lista cores
│       ├── progress.php     # Progresso
│       ├── timers.php       # Lista timers
│       ├── timers_create.php
│       ├── timers_update.php
│       ├── timers_delete.php
│       ├── step.php         # Próximo passo da transição
│       └── logs.php         # Logs
├── config/
│   ├── bootstrap.php        # Inicialização
│   ├── constants.php        # Constantes
│   ├── helpers.php          # Funções auxiliares
│   ├── logger.php           # Sistema de logs
│   ├── color_manager.php    # Gerenciador de cores
│   └── scheduler.php        # Gerenciador de timers
├── classes/
│   └── HomeAssistantClient.php  # Cliente HA
├── cron/
│   ├── execute_timers.php   # Executor de timers
│   └── transition_step.php  # Passo de transição
├── data/
│   ├── state.json           # Estado atual
│   ├── progress.json        # Progresso de transição
│   ├── timers.json          # Agendamentos
│   └── queue.json           # Fila de tarefas
├── logs/
│   └── app.log              # Log de aplicação
├── tmp/
│   └── *.lock               # Arquivos de lock
└── .env                     # Configuração
```

## 🐛 Troubleshooting

### Luz não responde

1. Verifique a URL e token do Home Assistant em `.env`
2. Teste a conexão com HA:
```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  http://192.168.1.100:8123/api/services
```
3. Verifique se o entity_id está correto

### Cores não mudam

1. Verifique se o cron job está rodando:
```bash
grep execute_timers /var/log/syslog
```

2. Teste manualmente:
```bash
php /caminho/para/cron/execute_timers.php
```

3. Verifique permissões:
```bash
chmod 755 cron/*.php
chown www-data:www-data data/ logs/ tmp/
```

### Logs não aparecem

1. Verifique permissões de escrita:
```bash
touch logs/test.log
rm logs/test.log
```

2. Aumente o tamanho do log em `.env`:
```env
LOG_LEVEL=DEBUG
```

## 📝 Exemplos de Uso

### JavaScript/Fetch

```javascript
// Ligar luz com cor azul
fetch('/api/on', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ color: 'azul' })
}).then(r => r.json()).then(d => console.log(d));

// Obter progresso
fetch('/api/progress')
  .then(r => r.json())
  .then(d => console.log(`${d.data.progress_percentage}% complete`));
```

### Python

```python
import requests

BASE_URL = 'http://localhost/api'

# Ligar
response = requests.post(f'{BASE_URL}/on', json={'color': 'vermelho'})
print(response.json())

# Obter estado
response = requests.get(f'{BASE_URL}/state')
print(response.json()['data']['current_color'])
```

### Home Assistant Automation

```yaml
automation:
  - alias: "Luz de Bom-dia"
    trigger:
      platform: time
      at: '07:00:00'
    action:
      - service: rest_command.smartlighting_on
        data:
          color: "branco"

rest_command:
  smartlighting_on:
    url: "http://localhost/api/on"
    method: POST
    payload: '{"color": "{{ color }}"}'
    content_type: application/json
```

## 📄 Licença

MIT License - veja LICENSE.md

## 🤝 Contribuindo

Contribuições são bem-vindas! Por favor:

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📞 Suporte

Para issues, dúvidas ou sugestões, abra uma issue no GitHub.

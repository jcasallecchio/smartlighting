# Smart Lighting

Interface web para controlar uma luminaria pelo Home Assistant, aplicar cores por ciclos de energia e enviar programacoes ao Node-RED.

## Requisitos

- Apache 2.4 com PHP 8.0 ou superior
- Home Assistant com token de longa duracao
- Endpoint HTTP do Node-RED protegido por autenticacao Basic

## Configuracao

Crie o arquivo `.env` na raiz:

```env
HA_URL=http://home-assistant:8123
HA_TOKEN=seu_token
ENTITY_ID=switch.sua_luminaria

INTERVAL_TIME=2
COLOR_SEQUENCE=colorido,rosa,azul,vermelho,verde,ciano,roxo,amarelo,branco

TIMER_WEBHOOK_URL=http://node-red:1880/endpoint/smartlighting
TIMER_WEBHOOK_USERNAME=seu_usuario
TIMER_WEBHOOK_PASSWORD=sua_senha
TIMER_WEBHOOK_INTERVAL=2

LOG_LEVEL=INFO
LOG_MAX_SIZE=10485760
APP_TIMEZONE=America/Sao_Paulo
```

## Fluxos

- **Energia:** consulta e aciona a entidade diretamente no Home Assistant.
- **Cores:** a cor atual e informada pelo usuario. Cada ciclo desliga, aguarda `INTERVAL_TIME` e liga novamente.
- **Timer:** envia arrays JSON ao Node-RED.
  - Ligar: `[1, timestamp, "roxo"]`
  - Desligar: `[0, timestamp]`
  - Cancelar ligar: `[1]`
  - Cancelar desligar: `[0]`
- **Persistencia do timer:** as ultimas opcoes confirmadas ficam no `localStorage` do navegador.

## Endpoints ativos

- `GET public/api/state.php`
- `POST public/api/on.php`
- `POST public/api/off.php`
- `POST public/api/color_apply.php`
- `POST public/api/timer_submit.php`
- `GET public/api/logs.php`

## Logs

O painel fica em `public/logs.html`. Os registros estruturados sao gravados em `data/logs/app.log`; o padrao para novas funcionalidades esta em `OBSERVABILITY.md`.

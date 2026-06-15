# Observabilidade e logs

Toda funcionalidade nova deve registrar eventos suficientes para reconstruir o que aconteceu sem expor credenciais.

## Padrao minimo

- Registre o inicio de comandos que alteram estado com nivel `INFO`.
- Registre conclusoes bem-sucedidas com nivel `SUCCESS`.
- Registre rejeicoes esperadas com nivel `WARNING`.
- Registre falhas com nivel `ERROR`, incluindo a mensagem da excecao no contexto.
- Use `DEBUG` para leituras frequentes e detalhes de diagnostico.
- Escolha uma categoria estavel, como `Device`, `HomeAssistant`, `Scheduler`, `Transition`, `HTTP` ou `System`.
- Inclua contexto util: IDs, estado anterior, estado final, status HTTP, duracao e quantidade processada.
- Nunca registre tokens, cabecalhos de autorizacao, senhas, segredos ou corpos com dados sensiveis.

## Exemplo

```php
$logger->log('INFO', 'Operation started', [
    'entity_id' => $entityId,
    'action' => 'turn_on'
], 'Device');

try {
    // Execute operation.
    $logger->log('SUCCESS', 'Operation completed', [
        'entity_id' => $entityId,
        'duration_ms' => $durationMs
    ], 'Device');
} catch (Exception $e) {
    $logger->log('ERROR', 'Operation failed', [
        'entity_id' => $entityId,
        'error' => $e->getMessage()
    ], 'Device');
    throw $e;
}
```

O logger remove automaticamente valores cujo nome indique token, autorizacao, senha, segredo ou chave de API.

## Niveis

O nivel minimo e definido por `LOG_LEVEL` no `.env`. Use `INFO` em operacao normal e `DEBUG` durante investigacoes. O painel fica em `public/logs.html` e le `data/logs/app.log` por meio da API protegida da aplicacao.

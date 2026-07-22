# PAPI Rastro

Rastreamento de representantes de vendas no mapa, com login, grupos e permissões.

## Tipos de usuário

| Tipo | Papel |
|------|--------|
| **Gestor** | Usuários, grupos, escalas, regiões, empresas, pagamentos; vê a empresa no mapa |
| **Usuário master** | Grupos, usuários (só nos grupos dele) e escalas |
| **Usuário** | Mapa e mensagens apenas dos grupos em que participa |

Família, amigos e ronda são **grupos**. Quem está em vários grupos escolhe qual ver no mapa.

| Modo do grupo | No mapa |
|---------------|---------|
| **Social** (ex.: Amigos) | Membros online e compartilhando |
| **Ronda** (ex.: Ronda São Dimas) | Só quem está em **escala** no dia/hora atuais |

## Cadastros do gestor

- **Regiões** — bairro/rua/área
- **Escalas** — funcionário + região + data + horário início/fim
- **Usuários** — tipo, foto, telefone, grupos, regiões (morador)
- **Empresas / Pagamentos** — créditos manuais (mensal, anual, etc.)

## Mensagens (app + WhatsApp)

No mapa, toque na foto → texto ou áudio.

1. A mensagem **sempre** fica no app (menu **Mensagens**, com badge e notificação).
2. Se o destinatário tem telefone:
   - **Sem API Meta**: abre o WhatsApp (`wa.me`) com o texto para confirmar o envio.
   - **Com Cloud API** (`config/whatsapp.php`): envia texto/áudio direto no Zap.
3. Ao **ler no app**, mensagens que chegaram **do WhatsApp** (webhook) são marcadas como lidas na API da Meta.

> Limpar o “não lido” no celular do destinatário de uma msg que *você* enviou não é possível pela API — só o próprio WhatsApp dele faz isso. O que a Meta permite é marcar como lida as msgs que **ele** mandou para o número da empresa.

Webhook: `api/whatsapp_webhook.php` (verify token em `config/whatsapp.php`).

## Escala e mapa

- No cadastro, marque **Participa de escala** para o usuário poder ser selecionado em **Escalas**.
- Esses usuários **só aparecem no mapa** (e só gravam GPS) **no horário da escala**.
- Demais usuários aparecem sempre que estiverem online e compartilhando.

## Localização no celular

- Navegador aberto ou app instalado: GPS ativo.
- Minimizar: tenta continuar (melhor no Android com app instalado + notificação).
- Fechar a aba/app: some do mapa ao vivo.

## Pausar localização

No mapa, qualquer usuário pode **Pausar compartilhamento** / **Retomar**.

## Acesso

- URL local: `http://localhost/papi-rastro/login.php`
- Usuário inicial: `papijunior` / `123456` (gestor)
- Empresa demo com crédito de 365 dias criada automaticamente


## Estrutura

- `login.php` — tela de login (padrão PAPI NF)
- `index.php` — mapa em tempo real (foto, endereço, WhatsApp)
- `historico.php` — histórico diário + limpeza de registros antigos
- `usuarios.php` / `grupos.php` — cadastros (somente quem tem permissão)
- `api/` — salvar e listar localizações
- `manifest.webmanifest` + `sw.js` — instalar no celular (PWA)

## Celular

1. Abra o app no navegador do celular
2. Instale (banner “Instalar” ou “Adicionar à Tela de Início”)
3. Toque em **Ativar localização** e permita o GPS
4. A posição entra no histórico (`localizacoes`) e aparece no mapa com a foto

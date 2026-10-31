# PWL Encomendas

- **Versão:** 1.4.17
- **Área:** Recursos Corporativos
- **Shortcode:** `[pwl_encomendas]`
- **Status:** Em produção
- **Dependências:** PWL Core, PWL Notificações e PWL Design System 1.3.1

## Instalação

Envie a pasta `pwl-encomendas` para `wp-content/plugins/` e ative o plugin. Insira `[pwl_encomendas]` na página do Portal. Configure no Core as permissões `encomendas.view`, `encomendas.manage` e `encomendas.admin` para os grupos autorizados. `encomendas.manage` libera a gestão pela Portal UI e não libera a Admin UI. A permissão `encomendas.admin` libera a Admin UI de forma independente.

## Interface e Design System

A Portal UI carrega `pwl_ds_enqueue(array('all'), 'portal')` e usa tokens `--pwl-*`. O canvas, cards, campos, botões, badges e cards de encomenda consomem as cores, tipografia, espaçamentos, bordas e estados definidos pelo PWL Design System 1.3.1. O hover dos botões da Portal UI usa `--pwl-color-primary-hover`.

## Avisos antecipados com palavra-passe

Colaboradores com acesso `encomendas.view` podem registrar no Portal um aviso antes da chegada, informando transportadora, previsão, rastreio opcional, descrição, pessoa autorizada e palavra-passe. O colaborador consulta apenas as próprias encomendas recebidas e os avisos enviados. Usuários com `encomendas.manage` consultam avisos pendentes no Portal UI de gestão e iniciam o cadastro da chegada a partir do aviso, que preenche destinatário e os dados conhecidos. O registro real continua sendo criado pela Portaria e recebe o código interno normal.

A palavra-passe é armazenada criptografada usando os salts do WordPress, não é enviada em e-mail, WhatsApp ou notificações e fica disponível ao colaborador responsável e à gestão. Ao registrar a retirada ou cancelar um aviso, o texto criptografado é apagado. Se os salts do WordPress forem trocados antes da retirada, a palavra-passe previamente cadastrada deixa de poder ser descriptografada; o colaborador deve então criar um novo aviso atualizado.

Ao enviar um aviso antecipado, o módulo notifica no Portal cada usuário com `encomendas.manage`. A notificação inclui colaborador e transportadora, nunca a palavra-passe, e não dispara e-mail, WhatsApp ou notificação do Windows.

Na retirada, a lista de pessoas vem do cadastro ativo do PWL Core. Ao abrir a lista, “Outra pessoa” é a primeira opção; ao selecioná-la, aparece o campo para digitar o nome. Pessoas selecionadas na lista são validadas novamente no servidor e gravadas pelo ID do cadastro.

Os cards de encomendas exibem a foto do colaborador cadastrada no PWL Core. Quando o cadastro não tem foto ou a encomenda pertence a um grupo, o card mantém o avatar com as iniciais.

Na Portal UI, a listagem abre filtrada por encomendas aguardando retirada, com opção de consultar todos os status. Nos cards, o botão “Reenviar avisos” fica na faixa superior. O bloco de detalhes apresenta a data de recebimento, remetente com selo da transportadora, código interno e descrição; canais, operador e ação de retirada aparecem abaixo, conforme a referência visual aprovada.

O card **Registrar encomenda** na Portal UI recebe destaque visual em azul-marinho Air Glass, com transparência, desfoque, borda e contraste tipográfico; os controles mantêm a superfície clara para facilitar o preenchimento. Na Admin UI, o formulário não é duplicado: a tela concentra consulta e gerenciamento.

A Admin UI organiza as páginas em **Encomendas**, **Relatórios**, **Integrações** (WhatsApp e Teams), **Cadastros** (Grupos de Recebimento e Transportadoras) e **Sobre**, que mantém o changelog recolhido até ser expandido. Os quatro indicadores de encomendas ficam na mesma linha em telas largas.

Na Admin UI, cada transportadora pode receber uma imagem selecionada na Biblioteca de Mídia do WordPress. A imagem cadastrada tem prioridade e aparece em selo circular à direita do remetente nos cards do Portal. Sem imagem cadastrada, o plugin usa o logo automático quando reconhecer AliExpress, Shopee, Mercado Livre, Amazon, Magalu, SHEIN, DHL, Correios ou Jadlog.

O botão “Reenviar avisos” solicita uma nova tentativa em todos os canais disponíveis para encomendas que ainda aguardam retirada, mesmo que uma tentativa anterior tenha sido aceita. O resultado de cada tentativa fica registrado nos eventos do módulo.

Ao reenviar manualmente, a notificação do Portal e o e-mail recebem o prefixo “Lembrete:” no título/assunto e no início do corpo; os dados da mensagem original são mantidos. O primeiro envio e o modelo do WhatsApp permanecem inalterados.

Os e-mails de encomendas usam o template HTML centralizado de PWL Notificações, incluindo identidade visual, logo, rodapé e botão para acessar o Portal. Para destinatários sem conta vinculada, o módulo envia um e-mail HTML compatível com as mesmas configurações de identidade visual.

O logotipo nos e-mails recebe o atributo HTML `width="300"` e estilos responsivos com largura máxima de 300 px e altura proporcional, para evitar que clientes de e-mail exibam a imagem em sua resolução original.

O aviso de encomenda é apresentado em linhas separadas com rótulos em negrito, identifica o código como “Código interno da Encomenda” e termina com o ramal da Portaria (285 / 286). O e-mail não inclui botão ou link de ação; o título não é repetido fora do cartão. As etiquetas de categoria/prioridade não são exibidas, e o identificador interno da unidade é omitido das mensagens.

A listagem de encomendas permite combinar busca livre, status, usuário destinatário e transportadora. Os filtros por usuário e transportadora usam os cadastros do PWL Core e do próprio módulo, incluindo registros inativos para localizar encomendas antigas.

Os campos de rastreio e descrição no cadastro usam a borda e o raio oficiais dos controles do Design System, com tokens próprios também para hover e foco.

As comboboxes da Portal UI e da Admin UI usam a cor de texto dos tokens oficiais do respectivo Design System, inclusive nas opções da lista.

Nos cards e na Admin UI, a identificação de quem retirou fica recolhida até o operador clicar em “Registrar retirada”. Depois de abrir, o botão de confirmação aparece antes do campo de identificação; o botão de reenvio permanece abaixo dele.

O CSS local da Portal UI controla apenas o layout responsivo. A Admin UI carrega `pwl_core_admin_ui_enqueue()` somente nas telas administrativas deste plugin, com composição administrativa isolada em `assets/css/encomendas-admin.css` e tokens `--pwl-ui-*`.

Versões de referência inspecionadas: **PWL Core 1.54.4** e **PWL Design System 1.3.1**.

## Recursos

- Registro de encomendas associado à Base Universal do Core.
- Destinatário e remetente selecionados em listas; remetentes vêm da tabela de transportadoras do módulo.
- Avisos no Portal, por e-mail corporativo, Microsoft Teams individual via Power Automate e por WhatsApp Cloud API quando configurados.
- Cards responsivos de encomendas na Portal UI e tabela administrativa na Admin UI.
- Registro de retirada e reenvio de avisos pendentes.
- Eventos de recebimento, avisos, reenvios e retiradas gravados para auditoria e consulta em **Plataforma → Relatórios**.
- Cadastro e ativação/desativação de transportadoras em **Plataforma → Transportadoras**. Somente transportadoras ativas aparecem no formulário.
- Cadastro de grupos de recebimento em **Plataforma → Grupos de Recebimento**, com seleção de colaboradores ativos. Os grupos ativos aparecem antes das pessoas, ambos em ordem alfabética, no campo de destinatário. Cada membro recebe aviso individual no Portal/Windows, e-mail e mensagem privada do Flow bot no Teams quando configurados; WhatsApp é tentado se já estiver configurado. Os membros são fotografados no momento do registro para manter reenvios consistentes.
- Integração privada com Teams configurada em **Plataforma → Teams**. O fluxo recebe cada destinatário individualmente, usando o e-mail corporativo do Core, e envia em “Chat com Flow bot”. O registro do módulo confirma que o webhook aceitou o pedido; a entrega final deve ser verificada no histórico do Power Automate.
- As abas administrativas carregam seus dados em segundo plano e trocam de conteúdo sem recarregar a página. A tela de transportadoras organiza cadastro e listagem lado a lado, e o cadastro de grupos exibe os colaboradores em uma única coluna.
- No cadastro de grupos, o campo de busca filtra colaboradores por nome sem desmarcar os itens selecionados.
- Cada card da Portal UI informa o operador que registrou a encomenda, usando o nome de exibição da conta WordPress e o rótulo em negrito.

Os eventos continuam armazenados na tabela `pwl_encomendas_events`, sem histórico individual na tela de encomendas. A nova tabela `pwl_encomendas_carriers` armazena as transportadoras. Na atualização, remetentes já usados são importados como opções ativas; novos cadastros podem ser incluídos ou desativados na tela Transportadoras.

Cada encomenda recebe o código único `ENC-AAAA-000001`, salvo no banco. Na atualização, os registros existentes são preenchidos automaticamente. O código aparece abaixo do remetente no cartão do Portal e no corpo do e-mail, e também pode ser consultado nos Relatórios.

Na atualização para a versão 1.0.0, a rotina de produção remove uma única vez as encomendas de ID 1 a 37 e seus eventos e destinatários vinculados, mantendo o cadastro de pessoas do Core, os grupos, seus membros e as transportadoras. A sequência de códigos passa a ser independente do ID interno do banco e reinicia em `ENC-2026-000001`.

## Configuração opcional do WhatsApp

Para preparar o diagnóstico do Embedded Signup, no Admin UI acesse **Plataforma → WhatsApp**. Cadastre o Meta App ID e o Configuration ID de uma configuração Embedded Signup que inclua o onboarding de WhatsApp Business App. O domínio autorizado para o SDK deve incluir `portal.cobremack.com.br`.

O botão **Testar fluxo de coexistência** abre o login da Meta e registra apenas eventos de diagnóstico. Esta entrega ainda não troca o código OAuth por token, não registra o telefone na Cloud API e não configura o webhook. Não use o botão como confirmação de conexão concluída. O app Meta também precisa ter permissões e revisão exigidas pela Meta para o uso de produção.

Defina no `wp-config.php`:

```php
define( 'PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID', 'ID_DO_NUMERO_NA_META' );
define( 'PWL_ENCOMENDAS_WA_ACCESS_TOKEN', 'TOKEN_DE_ACESSO_DA_API' );
define( 'PWL_ENCOMENDAS_WA_GRAPH_VERSION', 'VERSAO_GRAPH_ATIVA' );
define( 'PWL_ENCOMENDAS_WA_TEMPLATE_NAME', 'aviso_encomenda' );
define( 'PWL_ENCOMENDAS_WA_TEMPLATE_LANGUAGE', 'pt_BR' );
```

O modelo precisa estar aprovado pela Meta e seus parâmetros devem corresponder aos dados enviados pelo plugin.


Na Admin UI, somente super administradores do WordPress podem selecionar e excluir encomendas. A confirmação exige digitar exatamente `EXCLUIR`; a operação também remove os eventos relacionados. A Portal UI não oferece exclusão.

## Registro de avisos pela Admin UI

A aba Registrar aviso aparece entre Encomendas e Relatórios. O formulário associa o aviso ao colaborador vinculado à conta conectada e reutiliza as validações, notificações e proteção da palavra-passe do fluxo da Portal UI. Após o envio, a pessoa retorna à aba com a confirmação do resultado.


## Permissões do módulo

- `encomendas.view`: acesso à Portal UI para consultar encomendas próprias e registrar avisos antecipados.
- `encomendas.manage`: gestão de encomendas pela Portal UI, incluindo registro e retirada. Não concede acesso à Admin UI.
- `encomendas.admin`: acesso à Admin UI. O acesso a cadastros e operações de gestão continua exigindo também `encomendas.manage`.

Usuários com `encomendas.manage` não recebem `encomendas.admin` automaticamente. Contas com a capacidade WordPress `manage_options` mantêm o acesso administrativo privilegiado definido pelo Core.

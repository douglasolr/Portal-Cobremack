## 1.5.11 (2026-10-31)
- Corrigido o formato da mensagem do Teams para usar HTML simples com quebras `<br>` e negrito `<strong>`, em vez de Markdown literal.

## 1.5.10 (2026-10-31)
- Formatada a mensagem enviada ao workflow do Teams com título, campos em linhas separadas e rótulos em negrito.

## 1.5.9 (2026-10-31)
- Arredondados os cantos do cartão de e-mail de encomendas no renderizador central e no envio alternativo.

## 1.5.8 (2026-10-31)
- Corrigido o preenchimento dos tokens de descrição e rastreio no e-mail de encomendas; o rótulo da descrição agora corresponde ao modelo central.
- Os envios alternativos reconhecem os rótulos `Descrição:` e `Embalagem:` e mantêm os valores cadastrados.

## 1.5.7 (2026-10-29)
- Removido exclusivamente o símbolo + do botão Registrar Encomenda na Portal UI de Gestão.

## 1.5.6 (2026-10-29)
- Corrigido o arredondamento dos cantos do popup Avisar a Portaria em desktop e mobile, utilizando o token oficial do Design System.
- Preservados os dois formulários e as permissões.

## 1.5.5 | 29/10/2026
- Corrigida a cor dos rótulos dos seis campos do popup Avisar a Portaria para preto (#000000), sem alterar os formulários ou fluxos.

## 1.5.4 | 29/10/2026
- Ajustada a cor dos textos do popup Avisar a Portaria para preto e substituído exclusivamente seu ícone por avião de papel. Popup Registrar Encomenda preservado.

## 1.5.3 | 29/10/2026
- Restaurados os dois formulários independentes em popup e posicionados os botões lado a lado no cabeçalho da Gestão.
- Corrigida a seleção do formulário original de registro, sem alterar seu envio.

## 1.5.2 | 29/10/2026
Correção visual do popup Avisar a Portaria: reutilização das classes originais do popup Registrar Encomenda, ajuste de seletor JavaScript e responsividade, preservando o fluxo e a base 1.4.47.

## 1.5.1 | 29/10/2026
- Aviso à Portaria aberto em popup na Portal UI híbrida, mantendo formulário e envio originais.
- Preservado o popup de registro da versão 1.4.47.

## 1.5.0 | 2026-10-29
- Adicionada a permissão `encomendas.hybrid` e alternância entre gestão e aviso antecipado na Portal UI.
- Preservado integralmente o formulário de registro no popup da versão 1.4.47.
- Avisos próprios usam o vínculo com PWL Core e o fluxo existente.

## 1.4.47 | 2026-10-29
- Portal UI Gestão: removida a caixa informativa azul do estado vazio de notificações; mantido apenas o texto.

## 1.4.46 | 2026-10-29
- Removida a exibição da mensagem informativa após reenviar avisos, preservando o processamento e os registros de eventos.

## 1.4.45 | 2026-10-29
- Portal UI Gestão: indicadores de atenção mudam automaticamente de verde (0 a 4) para amarelo (5 a 9) e vermelho (10 ou mais); Recebidas hoje e Retiradas hoje permanecem verdes. Cores usam tokens oficiais, inclusive no modo escuro.

## 1.4.44 | 2026-10-29
- Corrigido o ícone do popup Registrar Encomenda: caixa branca dentro de quadrado laranja com cantos arredondados, idêntico ao cabeçalho.
- Movido o SVG para o cabeçalho do popup para impedir ampliação indevida no corpo do formulário.
- Backend e demais telas preservados.

## 1.4.43 | 2026-10-29
- Portal UI Gestao: estendido o fundo oficial aos wrappers do tema que geravam faixa de cor diferente abaixo do header.
- Sem mudancas no backend, no header global ou em outras telas.

## 1.4.42 | 2026-10-29
- Portal UI Gestao: uniformizado o fundo da faixa entre o header global e o conteudo, usando o token oficial de background.
- Backend e demais interfaces preservados.

## 1.4.41 | 2026-10-29
- Portal UI Gestão: redução dos intervalos entre cabeçalho, indicadores e encomendas registradas.
- Portal UI Gestão: remoção do espaço superior do container para aproximar o fundo do header.
- Popup Registrar Encomenda: ícone de caixa idêntico ao cabeçalho.
- Backend e administração preservados.

## 1.4.40 | 29/10/2026
- Portal UI Gestão: reorganizados os cinco indicadores, com o primeiro em destaque vertical, quatro em grade 2x2 e notificações ao lado, conforme imagem de referência.
- Mantidos quatro cards de encomendas por linha no desktop, responsividade, funcionalidades e administração.

## 1.4.39 | 2026-10-28
- Portal UI Gestão: quatro colunas de encomendas registradas no desktop, com adaptação para três, duas ou uma conforme largura.
- Backend e Admin UI preservados.

## 1.4.38 | 2026-10-28
- Portal UI Gestão: adicionado ícone de caixa de papelão em linhas claras sobre bloco laranja ao lado do título e da descrição.
- Nenhuma alteração em operações, permissões, integrações ou Admin UI.

## 1.4.37 | 2026-10-28
- Portal UI Gestão: removido o subtítulo de navegação "Recursos Corporativos · Portaria & Acesso" e a barra laranja lateral do título, preservando o restante do cabeçalho.

## 1.4.36 | 2026-10-28
- Portal UI Gestão: removida a restrição de largura máxima de 1440px; mantida a largura responsiva do container oficial.

## 1.4.35 | 2026-10-28
- Portal UI Gestão: fonte dos botões Reenviar avisos reduzida em 1 ponto, sem alterar os demais botões.

## 1.4.34 | 2026-10-28
- Portal UI Gestão: faixa dos cinco indicadores expandida à largura total do conteúdo (até 1440px).
- Painel de notificações de colaboradores movido para baixo da faixa de indicadores, em largura integral.
- Backend, permissões e Admin UI preservados.

## 1.4.33 | 2026-10-28
- Portal UI Gestão: conteúdo centralizado com largura máxima de 1440px, preservando adaptação em telas menores.

## 1.4.32 | 2026-10-28
- Portal UI Gestão: cinco indicadores agrupados à esquerda e card de notificações de colaboradores à direita, conforme referência visual.
- Correção de disposição para impedir blocos residuais abaixo dos indicadores; responsividade mantida.
- Backend, permissões, notificações e Admin UI preservados.

## 1.4.31 | 2026-10-28
- Portal UI Gestao: removida a borda do campo Codigo de Rastreio / Identificador no popup Registrar Encomenda.
- Demais campos, backend e Admin UI preservados.

## 1.4.30 | 2026-10-28
- Portal UI Gestão: adicionado quinto card de indicador para notificações de colaboradores, utilizando a contagem real de avisos antecipados pendentes.
- Card de notificações preservado e destacado em largura integral, com espaçamento interno.
- Backend e Admin UI inalterados.

## 1.4.29 | 2026-10-28
- Popup Registrar Encomenda: removido o texto visivel Fechar do botao, preservando o icone X e o rotulo acessivel.

## 1.4.28 | 2026-10-28
- Portal UI Gestao: titulo e descricao do popup alinhados a esquerda, proximos ao icone.
- Descricao do popup em preto, sem alteracoes funcionais.

## 1.4.27 | 2026-10-28
- Portal UI Gestão: três colunas de encomendas registradas em desktop, adaptadas para tablet e smartphone.
- Indicadores: valor acima da descrição.
- Notificações de colaboradores: card independente em largura integral, sem limitação de altura e sem rolagem interna.
- Backend, permissões, endpoints e Admin UI preservados.

## 1.4.26 | 2026-10-28
- Portal UI Gestao: opacidade do card Air Glass reduzida de 38% para 20%, e cabecalho de 28% para 12%.
- Sem alteracoes no backend, nas notificacoes ou na interface administrativa.

## 1.4.25 (2026-10-28)
- Corrigida a opacidade das superficies internas do popup de Gestao, que escondiam o efeito Air Glass.
- Card branco translúcido com desfoque localizado; overlay mais suave, sem desfoque global.
- Nenhuma alteracao funcional ou administrativa.

## 1.4.24 (2026-10-28)
- Aumentada a transparência do card Air Glass do popup de registro, de 82% para 65% de opacidade.
- Mantidos a ausência de moldura externa e o fundo da página sem desfoque.
- Backend e Admin UI preservados.

## 1.4.23 (2026-10-28)
- Aplicado efeito Air Glass translúcido diretamente ao fundo do card de registro na Portal UI Gestão.
- Removida a moldura desfocada externa, preservando o fundo da página sem desfoque.
- Backend e Admin UI preservados.

## 1.4.22 (2026-10-28)
- Ajustada a moldura Air Glass para acompanhar o tamanho real do popup, com margem ao redor do formulario.
- Removido o desfoque do fundo da pagina; o vidro fosco permanece apenas na moldura.
- Nenhuma alteracao no backend ou Admin UI.

## 1.4.21 | 2026-10-28
- Portal UI Gestao: ampliado o espacamento entre os campos do popup de registro.
- Adicionada moldura translúcida Air Glass ao redor do popup, mantendo o desfoque de fundo.
- Backend, Admin UI e fluxos de notificacao preservados.

## 1.4.20 (2026-10-28)
- Removida a secao visual de canais de notificacao do popup Registrar Encomenda na Portal UI Gestao.
- Aplicado desfoque real ao fundo da tela com backdrop-filter no overlay do popup.
- Mantidos o envio automatico de notificacoes e os contratos do backend.

## 1.4.19 (2026-10-28)
- Ajustado exclusivamente o popup Registrar Encomenda da Portal UI Gestao para a composicao visual do Stitch.
- Preservados os campos reais, cadastro e canais de notificacao configurados no backend.
- Ajustados cabecalho, campos, grade, botoes, overlay e responsividade.
- Canais exibidos como indicadores, sem simular controles de preferencias inexistentes.

## 1.4.18
- Iniciada adaptação visual isolada da Gestão da Portal UI para a referência Stitch: cabeçalho de ação, registro em sobreposição, cards, filtros e responsividade com tokens oficiais.
- Preservadas rotas, formulários, lógica e telas administrativas.
- Pendente validação visual e funcional em WordPress; não constitui reprodução integral certificada.

## 1.4.17
- Ao confirmar retirada, o proprietário e os membros snapshot de encomendas para grupos recebem aviso de retirada pelo PWL Notificações e Teams.
- A mensagem informa data, hora, quem retirou e o operador logado que entregou; Portal, e-mail e Windows usam a integração central, e Teams usa o webhook já configurado.
- Os resultados por canal são registrados no histórico da encomenda sem sobrescrever os status dos avisos de chegada.

## 1.4.16
- Adicionado espaçamento uniforme em torno da mensagem de fila vazia no painel de notificações de colaboradores da Portal UI de gestão.

## 1.4.15
- Cards de encomendas retiradas na Portal UI de gestão agora identificam o usuário logado que confirmou a entrega, usando o registro salvo em `picked_up_by_user`.
- Mantida a identificação da pessoa que retirou a encomenda.

## 1.4.14
- A ordenação por tipo e tamanho Pequena, Média e Grande fica somente nas comboboxes de registro e promoção de avisos.
- A lista administrativa de embalagens mantém sua ordenação cadastral, sem classificação especial.

## 1.4.13
- Ordenação refinada por nome completo do tipo de embalagem e, dentro de cada tipo, por tamanho: Pequena/Pequeno, Média/Médio e Grande.
- Aplicada à listagem administrativa e aos seletores de registro; itens inativos continuam fora dos seletores.

## 1.4.12
- Ajustada a ordenação das embalagens: agrupamento pela inicial do tipo e sequência de tamanho Pequena, Média e Grande.
- A mesma ordem é aplicada à lista administrativa e aos seletores de novos registros, que continuam exibindo somente itens ativos.

## 1.4.11
- A lista de embalagens e os seletores de registro passam a usar ordenação alfabética.
- Removidos os controles de ordenação manual; embalagens inativas continuam excluídas dos seletores e bloqueadas pela validação do servidor.

## 1.4.10
- Ajustado o controle de ordenação para exibir setas acompanhadas dos rótulos “Subir” e “Descer”, tornando as ações visíveis na tabela de embalagens.

## 1.4.9
- Adicionada ordenação manual das embalagens cadastradas por controles de subir e descer, mantendo separadas as listas ativas e inativas.
- Confirmado o filtro de embalagens ativas nos formulários de registro e na promoção de avisos; itens inativos não são oferecidos e são recusados pela validação do servidor.
- Migração compatível adiciona a ordem persistente sem remover cadastros ou alterar encomendas existentes.

## 1.4.8
- Substituído o campo livre “Descrição do volume” pela combobox “Embalagem” nos formulários de registro da Portaria.
- Criado cadastro administrativo com sete opções iniciais, edição e ativação/desativação; somente embalagens ativas ficam disponíveis para novos registros.
- Adicionada migração para criar/preencher a tabela de embalagens sem alterar encomendas existentes.

## 1.4.7
- Disparo de notificações de gestão agora considera somente a permissão explícita `encomendas.manage` do PWL Core.
- O papel `administrator`/capacidade `manage_options` do WordPress não concede destinatário automaticamente.
- Mantido o bloqueio para usuários sem acesso ativo na plataforma.

## 1.4.6
- Painel de notificações mantém altura fixa de 414 px, com rolagem vertical interna para listar até 200 avisos sem expandir o cartão.
- Atualizada a versão dos assets para renovar o cache do navegador.

## 1.4.5
- Foto nas notificações de colaboradores agora usa o mesmo recorte circular das fotos nas encomendas registradas.
- Atualizada a versão dos assets para renovar o cache do navegador.

## 1.4.4
- Unificado o texto de hover, foco e acionamento dos botões de todas as páginas do módulo para branco (`#ffffff`) sobre o fundo laranja escuro.
- Atualizada a versão dos assets para renovar o cache.

## 1.4.3
- Corrigida duplicação de HTML que renderizava a foto do colaborador fora do selo e desorganizava a lista.
- Consolidado o CSS do layout da Portal UI Gestão e atualizada a versão dos assets para invalidar o cache.
- Preservadas permissões e fluxos de registro.

## 1.4.2
- Refeito o layout da Portal UI (gestão) para seguir a referência `portaluigestao.png`: indicadores compactos no topo da coluna esquerda, formulário abaixo e notificações na coluna direita, estendendo-se ao lado dos dois blocos.
- Notificações organizadas em linha com foto do colaborador, nome em negrito, detalhes em duas linhas e botão de promoção.
- Preservadas as permissões e os fluxos da versão 1.4.1.

## 1.4.1
- Compactados os cards de indicadores da Portal UI (gestão).
- Mantidos o formulário de registro e o painel de notificações lado a lado em telas amplas.
- Notificações agora mostram foto e nome destacado do colaborador, com transportadora/código na primeira linha e descrição/palavra passe na segunda.
- Preservadas permissões e fluxos da versão 1.4.0.

# Changelog

## 1.3.0

- Adicionada a aba Registrar aviso na Admin UI, entre Encomendas e Relatórios.
- O formulário registra o aviso para o colaborador vinculado à conta conectada e retorna à aba com o resultado.
- Reutilizadas as validações, notificações e o armazenamento protegido da palavra-passe já usados pela Portal UI.


## 1.2.2

- Corrigido o token de dimensão inexistente no popup, que impedia o limite do logo de ser aplicado.
- Logo da transportadora agora fica dentro de um selo circular compacto, usando o token oficial de controle do PWL Design System.
- Mantidos o cadastro de avisos para usuários comuns e o layout da Portal UI de gestão sem abas e sem formulário.

## 1.2.1

- Removidas da Portal UI de gestão a barra de abas Gestão e Registrar e a área de aviso antecipado de encomenda.
- A Portal UI de gestão voltou ao layout anterior: indicadores, registro de encomenda, notificações de colaboradores e encomendas registradas.
- A área Avisar encomenda segue disponível para os usuários comuns, sem alteração.

## 1.2.0

- Adicionada barra de abas à gestão da Portal UI: Gestão e Registrar.
- Gestão mantém o fluxo administrativo de encomendas; Registrar permite ao administrador enviar aviso antecipado e consultar somente os próprios avisos.
- Corrigido o tamanho do logo no popup para selo circular compacto.

## 1.1.9

- Exibido o logo da transportadora em selo circular no popup.
- Botões de confirmação e cancelamento do popup em caixa alta.

## 1.1.8

- Ampliada a janela de confirmação de promoção para evitar corte e quebra excessiva dos dados.
- Incluído no modal o logotipo da transportadora cadastrado no módulo, com fallback automático para marcas reconhecidas.

## 1.1.7

- Corrigido o seletor do nome do colaborador usado ao preencher a confirmação de promoção após a mudança do layout dos avisos.

## 1.1.6

- Reorganizados os dados de cada aviso no formato do print: colaborador na primeira linha, transportadora/rastreio/descrição na segunda e palavra-passe na terceira.

## 1.1.5

- Removido o campo para vincular avisos antecipados no formulário de registro.
- Cada aviso pendente agora tem ação própria para abrir confirmação central com os dados e promover diretamente a encomenda.
- Após a promoção, o aviso sai das listas de avisos; permanece associado ao registro somente para manter a palavra-passe disponível à Portaria até a retirada.

## 1.1.4

- Bloqueio visual do botão de registro e indicador “Registrando…” durante o envio.
- Proteção idempotente no servidor por token único: reenvios do mesmo formulário não criam encomendas duplicadas; o operador recebe aviso para conferir a lista.

## 1.1.3

- Corrigida a renderização dos cards de indicadores chamada pelo novo layout da Portal UI de gestão.

## 1.1.2

- Reorganizado o topo da gestão na Portal UI para seguir o HTML de referência: indicadores e cadastro à esquerda, avisos antecipados à direita e encomendas registradas abaixo.
- Avisos de colaboradores exibidos como lista compacta com iniciais, detalhes, palavra-passe protegida e ação para iniciar o registro da chegada.
- Ajustado o layout responsivo para empilhar as colunas em telas menores e manter o Design System por meio dos tokens PWL.
- Esclarecido que a notificação de gestão é interna ao Portal; o PWL Notificações também pode enviar push a usuários inscritos, sem envio de e-mail ou WhatsApp por este fluxo.

## 1.1.1

- Ao registrar um aviso antecipado, envia uma notificação interna do Portal para cada usuário com a permissão `encomendas.manage`.
- A notificação informa quem enviou o aviso e a transportadora, sem incluir a palavra-passe; não dispara e-mail ou WhatsApp.

## 1.1.0

- Criado o fluxo de aviso antecipado: colaboradores informam no Portal a transportadora, detalhes e palavra-passe antes da chegada do volume.
- A Portaria consulta os avisos pendentes no Portal UI de gestão e pode iniciar o registro de chegada com os dados do aviso já preenchidos.
- O vínculo da chegada mantém o aviso associado ao código interno da encomenda; a palavra-passe só aparece para o colaborador responsável e para usuários de gestão.
- As palavras-passe são criptografadas no banco e apagadas após a retirada ou o cancelamento do aviso.
- A área do colaborador passou a listar somente encomendas associadas ao próprio usuário e exibe seu histórico de avisos antecipados.
- Atualizado o schema do banco para os avisos antecipados sem executar novamente a limpeza de dados de pré-produção.

## 1.0.29

- Invertida a ordem das duas primeiras colunas da Admin UI: Código interno e Data recebida. A lista continua iniciando pela encomenda mais recente.
- Adicionados filtros de período: Hoje, Ontem, Esta semana, Este mês, Mês passado, Últimos 6 meses, Último ano e data personalizada.
- Adicionado filtro por Transportadora na Admin UI. Os filtros de período, transportadora, status e busca podem ser combinados.


## 1.0.28

- Recalculadas as larguras das colunas da tabela administrativa para acomodar o código interno e os dois botões de ação. Os botões ficam na mesma linha e os dados longos podem quebrar dentro de suas colunas.


## 1.0.27

- Botões Registrar retirada e Reenviar avisos alinhados lado a lado na coluna Ações da tabela administrativa.


## 1.0.26

- Reduzidos a altura, o espaçamento horizontal e a fonte dos botões de ação dentro da tabela da aba Encomendas na Admin UI.


## 1.0.25

- Reduzido em 1,5 px o tamanho da tipografia da tabela de encomendas da Admin UI, usando o token tipográfico do Design System como referência.


## 1.0.24

- Adicionada à tabela da Admin UI a coluna ordenável **Código interno**, com o identificador ENC-AAAA-NNNNNN.


## 1.0.23

- A tabela de encomendas registradas na Admin UI agora ocupa toda a largura disponível. Os filtros e a área da tabela deixam a coluna estreita do layout lateral.


## 1.0.22

- Indicadores da Admin UI organizados em uma linha em telas largas; formulário duplicado de registro removido dessa tela.
- Navegação da Admin UI reorganizada nas abas Encomendas, Relatórios, Integrações (WhatsApp e Teams), Cadastros (Grupos de Recebimento e Transportadoras) e Sobre.
- Aba Sobre apresenta o histórico de alterações recolhido e expansível.
- Navegação interna das páginas administrativas mantida na página principal do módulo, preservando uma instalação e atualização única.

## 1.0.21

- Rótulo voltou para **Descrição:**; somente o conteúdo da descrição é exibido em caixa alta.

## 1.0.20

- Rótulo **DESCRIÇÃO** exibido em caixa alta nos cards de encomendas.

## 1.0.19

- Alterado para `#efefef` o fundo do bloco de detalhes nos cards de encomendas.

## 1.0.18

- Aumentado o espaçamento vertical entre as linhas de detalhes da encomenda com o token de espaçamento do Design System.

## 1.0.17

- Reposicionado o selo da transportadora para a linha do colaborador, alinhado à direita; detalhes da encomenda permanecem em linhas separadas dentro do bloco destacado.

## 1.0.16

- Remetente, código interno, código de rastreio e descrição agora exibem os rótulos seguidos dos respectivos valores em negrito.

## 1.0.15

- Ajustada a linha do recebimento para exibir primeiro a data e depois o horário (`dd/mm/aaaa HH:mm`).

## 1.0.14

- Data do recebimento no card agora inclui o horário antes da data, no formato `HH:mm - dd/mm/aaaa`.

## 1.0.13

- Aplicado aos cards da Portal UI o layout da referência: status e reenvio no topo, colaborador e ramal, bloco de detalhes com data de recebimento, remetente e selo da transportadora, código interno e descrição, seguido dos canais, operador e retirada.
- A data de recebimento aparece no bloco da encomenda, conforme a referência visual.

## 1.0.12

- A Portal UI passa a abrir com o filtro **Aguardando retirada**; o filtro **Todos** continua disponível e a Admin UI mantém sua listagem sem filtro inicial.
- Reposicionado **Reenviar avisos** para a faixa superior do card.
- Substituído o tempo relativo por **Recebimento em dd/mm/aa hh:mm**, logo abaixo do nome e da identificação do colaborador/ramal.

## 1.0.11

- Adicionado Microsoft Teams como canal privado individual via webhook do Power Automate e ação Flow bot em Chat com Flow bot.
- Grupos enviam uma chamada por colaborador, usando o e-mail corporativo registrado no snapshot do recebimento.
- Adicionados status Teams, eventos de envio, suporte a reenvio e configuração administrativa protegida para URL HTTPS da Microsoft.
- Atualizado DB_VERSION para 1.6.0 sem apagar encomendas existentes.

## 1.0.10

- Logo da transportadora passa a preencher todo o selo circular no card, com recorte proporcional semelhante ao avatar do colaborador.

## 1.0.9

- A abertura da Biblioteca de Mídia agora está diretamente no botão “Escolher imagem”, para funcionar mesmo quando a Plataforma insere o formulário por AJAX e não executa scripts da resposta.
- Os assets da Biblioteca de Mídia também são enfileirados no callback da tela de Transportadoras, independentemente do hook identificado pelo shell administrativo.
- A seleção atualiza o ID do anexo e a prévia antes de salvar a transportadora.

## 1.0.8

- Corrigido o carregamento dos scripts administrativos também quando Encomendas é exibido dentro da tela principal do PWL Platform/Plataforma.
- Mantidas as dependências explícitas do modal da Biblioteca de Mídia e a mensagem de diagnóstico caso a API do WordPress não esteja disponível.

## 1.0.7

- Corrigido o carregamento do seletor da Biblioteca de Mídia ao escolher o logo de uma transportadora; a tela agora declara explicitamente as dependências `media-views` e `media-editor`.
- Adicionado aviso quando o WordPress não disponibilizar a API da Biblioteca de Mídia na tela.

## 1.0.6

- Adicionada seleção de imagem pela Biblioteca de Mídia do WordPress ao cadastrar ou editar transportadoras na Admin UI.
- Imagem personalizada da transportadora é armazenada na tabela do módulo e usada prioritariamente no selo circular dos cards do Portal.
- Incrementado DB_VERSION para 1.5.0 para adicionar `logo_attachment_id` à tabela de transportadoras sem remover os cadastros existentes.

## 1.0.5

- Reposicionado o logo da transportadora para um selo circular de 42 px no lado direito da linha Remetente nos cards do Portal, alinhado ao avatar do colaborador.
- Removido o logo do campo de seleção de transportadora.

## 1.0.4

- Incluídos assets locais para AliExpress, Shopee, Mercado Livre, Amazon, Magalu, SHEIN, DHL, Correios e Jadlog.

## 1.0.3

- Destacado o card **Registrar encomenda** com tratamento azul-marinho Air Glass inspirado na referência, preservando contraste dos textos e legibilidade dos campos.

## 1.0.2

- Exibida nos cards da Portal UI a foto do colaborador destinatário obtida pelo PWL Core; as iniciais permanecem como fallback sem foto ou para destinatários em grupo.

## 1.0.1

- Incluído nos cards da Portal UI o nome de exibição do usuário que registrou a encomenda, abaixo dos status dos canais.
- O rótulo **Operador:** é exibido em negrito; o nome é obtido pelo usuário registrado no campo `received_by`.

## 1.0.0

- Declarado o módulo como **Em produção** no registro do Core, na interface e na documentação.
- Iniciada a versão estável 1.0.0.
- Na primeira atualização para esta versão, removidos os registros de encomendas com ID de 1 a 37, junto dos eventos e destinatários vinculados; cadastros de pessoas, grupos e transportadoras são mantidos.
- Criada sequência anual independente do ID do banco, reiniciada em 1 para o ano atual, com verificação de códigos já existentes.

## 0.8.3

- Adicionado campo de busca em **Colaboradores membros** para filtrar a lista por nome, sem limpar as seleções marcadas.

## 0.8.2

- Reorganizado o cadastro de grupos para exibir o formulário e a listagem em colunas lado a lado.
- Empilhados os campos Nome do grupo e Colaboradores membros no formulário.
- Corrigida a largura dos checkboxes e dos nomes dos colaboradores na seleção de membros.
- Em telas estreitas, as duas colunas retornam ao empilhamento para manter a leitura.

## 0.8.1

- Ajustada a lista de colaboradores membros do grupo para exibir uma pessoa por linha.
- Aplicados aos tabs administrativos os tokens de fundo, seleção laranja e hover laranja escuro do Design System.
- Organizada a tela Transportadoras em duas colunas, com o botão Cadastrar na mesma linha e proporção do campo.
- Carregamento em segundo plano e troca de abas sem recarregar a página; os conteúdos ficam em cache no navegador enquanto a tela está aberta.

## 0.8.0

- Criada a aba administrativa **Grupos de Recebimento**, com criação, edição, ativação e composição por colaboradores ativos.
- Grupos ativos aparecem antes dos colaboradores no destinatário; grupos e pessoas são ordenados alfabeticamente.
- No registro por grupo, o módulo salva a lista de membros daquele momento e cria notificações do Portal/Windows e e-mails individuais para cada membro com dados disponíveis.
- Os reenvios usam a mesma lista de membros salva no registro original; cada resultado por canal e membro fica registrado.
- Criadas as tabelas de grupos, membros e destinatários por encomenda; a atualização do módulo instala o esquema automaticamente.

## 0.7.23

- Exclusão em lote de encomendas pela Admin UI, com seleção individual ou seleção da lista filtrada.
- A confirmação exige digitar exatamente `EXCLUIR`; o servidor valida nonce e acesso de super administrador do WordPress.
- A exclusão remove os eventos relacionados; nenhuma opção de exclusão é exibida na Portal UI.

## 0.7.21

- Identificador exibido como “Código interno da Encomenda” nos cards do Portal e nos avisos.
- Adicionado “Ramal da Portaria: 285 / 286” como último item do aviso e às instruções do WhatsApp.

## 0.7.20

- Removido o identificador interno da unidade das mensagens do Portal/e-mail e WhatsApp.
- Corrigida a integração do template central de e-mail para encomendas: sem categoria/prioridade, botão ou prévia duplicada; rótulos em negrito e instrução de retirada sem código interno.
- Assunto de encomenda não inclui mais a prioridade “Importante”.

## 0.7.19

- Criado código único permanente `ENC-AAAA-000001` para cada encomenda, com preenchimento automático dos registros existentes.
- Incluído o código abaixo do remetente no cartão do Portal e no e-mail; busca e relatórios também exibem o identificador.
- Removidas do e-mail as etiquetas internas “Geral” e “Importante”.

## 0.7.18

- Removido o botão “Acessar conteúdo” do e-mail de encomendas no template central e no fallback HTML.

## 0.7.17

- Organizado o corpo do e-mail de encomendas em linhas separadas, com remetente, descrição e código de rastreio rotulados em negrito e instrução de retirada destacada.
- Centralizado e arredondado o botão “Acessar conteúdo”, com suporte a Outlook; removida a repetição do título antes do cartão.

## 0.7.16

- Reforçado o limite do logotipo por atributo HTML `width="300"` além do CSS responsivo, para clientes de e-mail que descartam estilos.

## 0.7.15

- Limitado o logotipo dos e-mails a 300 px de largura e 90 px de altura, com proporção preservada, tanto no template central como no envio de contingência.

## 0.7.14

- Corrigido o envio de e-mail em texto simples: destinatários com conta vinculada usam a fila HTML centralizada de PWL Notificações.
- E-mails para destinatários sem conta vinculada também usam HTML, com identidade visual, logo, rodapé e botão do Portal.

## 0.7.13

- No botão “Reenviar avisos”, adiciona “Lembrete:” ao título e ao corpo da notificação do Portal e ao assunto e corpo do e-mail, preservando o conteúdo completo da mensagem.
- O primeiro envio e a mensagem do WhatsApp permanecem inalterados.

## 0.7.12

- Igualadas altura, tipografia e espaçamento dos botões Registrar retirada e Reenviar avisos nos cards, com tokens oficiais do Design System.
- Os dois rótulos são exibidos em caixa alta para corresponder ao padrão visual da imagem de referência.

## 0.7.11

- Corrigida a diferença visual entre os textos dos botões de retirada e reenvio: ambos usam explicitamente o mesmo token tipográfico de controle em Portal e Admin UI.

## 0.7.10

- Mantido “REGISTRAR RETIRADA” em caixa alta e fixada sua tipografia no mesmo token de tamanho usado pelos botões “Reenviar avisos”.

## 0.7.9

- Texto do botão “Registrar retirada” exibido em caixa alta.
- Reordenado o formulário expandido: “Confirmar retirada” vem antes de “Quem retirou”, seguido do botão “Reenviar avisos”.

## 0.7.8

- O campo “Quem retirou” e a combobox ficam recolhidos até o operador clicar em “Registrar retirada”.
- Após a seleção do colaborador ou preenchimento do nome manual, o operador confirma a retirada em uma segunda ação.
- Interação aplicada aos cards da Portal UI e à tabela da Admin UI.

## 0.7.7

- Removido o identificador técnico da unidade da lista de colaboradores destinatários.

## 0.7.6

- Comboboxes da Portal UI e da Admin UI passam a usar a cor de texto escura/clara conforme o tema, pelos tokens do Design System.
- Aplicado também às opções exibidas ao abrir as listas, evitando a cor vermelha imposta pelo tema.

## 0.7.5

- Corrigida a borda dos campos Código de rastreio e Descrição do volume para corresponder aos seletores do formulário.
- Borda, raio, hover e foco usam tokens oficiais do Design System e resistem aos estilos globais do tema.

## 0.7.4

- Adicionados os filtros de usuário destinatário e transportadora à linha de filtros da Portal UI.
- Os filtros podem ser combinados com a busca livre e o status e mantêm seleção após aplicar.
- Filtro de usuário consulta pessoas do Core; filtro de transportadora inclui também cadastros inativos para localizar encomendas anteriores.

## 0.7.3

- Ajustado o campo de retirada para iniciar com a instrução “Selecione quem retirou”. “Outra pessoa” é a primeira opção disponível na lista e, ao selecioná-la, abre o campo de nome manual.
- Campo manual inicia oculto e desabilitado; selecionar uma pessoa cadastrada mantém o nome ligado ao respectivo cadastro.

## 0.7.2

- Corrigido o reenvio manual: o botão agora inicia nova tentativa nos três canais disponíveis, mesmo quando a tentativa anterior foi aceita.
- O botão “Reenviar avisos” fica disponível enquanto a encomenda aguarda retirada; cada canal continua registrando o resultado no relatório.
- Botão “Filtrar encomendas” movido para a mesma linha da busca e do filtro de status, alinhado aos campos.

## 0.7.1

- Adicionado espaço após o botão “Filtrar encomendas” antes dos cards.
- Hover dos botões da Portal UI aponta para o token oficial `--pwl-color-primary-hover` do Design System.
- “Nome de quem retirou” agora oferece colaboradores ativos do PWL Core e “Outra pessoa” como primeira opção, com campo manual contextual.
- Retiradas selecionadas na lista são validadas no servidor e gravam o ID da pessoa junto ao nome.

## 0.7.0

- Removidos os links e a tela de histórico individual das encomendas; os eventos seguem registrados e agora são consultados na aba administrativa Relatórios, com busca, filtros por período e paginação.
- Criada a tabela `pwl_encomendas_carriers` e a tela administrativa Transportadoras para cadastrar e ativar/desativar opções.
- Remetentes existentes foram importados como opções ativas na migração do banco.
- O campo Remetente ou transportadora passou a ser uma lista suspensa com as transportadoras ativas, validada também no servidor.
- A ação de retirada usa o botão primário com hover pelo token oficial de ação.
- Removida a informação de unidade dos cards e ampliado o espaçamento das mensagens após ações.

## 0.6.1

- Retornada a listagem da Portal UI para cards responsivos, no lugar da tabela de encomendas.
- Cards mostram destinatário, remetente, rastreio, descrição, tempo desde o recebimento, status dos avisos e ações de retirada/histórico.
- Mantidos os tokens do PWL Design System e o layout em quatro colunas, adaptado para telas menores.
- A tabela administrativa permanece disponível na Admin UI.

## 0.6.0

- Interface do shortcode voltou a usar os dados e ações operacionais do módulo.
- Aplicado o carregamento público do PWL Design System 1.3.1 no contexto `portal`.
- Canvas em largura total, cabeçalho do título em card branco oficial e header/footer globais removidos da saída do módulo.
- CSS de composição mantém cores, tipografia, espaçamentos e dimensões vinculados aos tokens `--pwl-*`.

## 0.5.0

- Shortcode passa a carregar o documento Stitch original completo via iframe `srcdoc`.
- Isolados os estilos do tema WordPress sem modificar o HTML, Tailwind, fontes, ícones ou scripts recebidos.
- Dados do Stitch permanecem estáticos porque não foram indicados campos para substituição dinâmica.
- Mantido o cabeçalho e o rodapé que constam no HTML fornecido.

## 0.4.1

- Adicionado espaçamento entre o cabeçalho e os cards de indicadores.
- Alinhados os quatro campos do cadastro e o botão; removida a orientação sobre origem dos dados de contato.
- A tabela agora carrega todos os registros, ordena no navegador sem atualizar a página e não mostra Unidade, Portal, E-mail ou WhatsApp como colunas.

## 0.4.0

- Substituído “Resumo da Portaria” por quatro indicadores: aguardando retirada, recebidas hoje, retiradas hoje e avisos pendentes.
- Removida a confirmação de WhatsApp do formulário; o envio é tentado quando há API configurada e número válido no cadastro.
- Reorganizado o cadastro em fluxo horizontal com destinatário, remetente, rastreio, descrição e ação de envio.
- Colocada a lista abaixo do cadastro, com ordenação nos campos de dados e busca livre em todos os dados, datas e status.

## 0.3.3

- Removido o card “Sobre o módulo” da Portal UI.
- Aplicado ao título da página o componente oficial `.pwl-portal-page-header`, com fundo branco e tokens do Design System.

## 0.3.2

- Impedida a renderização repetida do módulo na mesma requisição, mesmo quando o construtor avalia o shortcode mais de uma vez.
- Removida da interface a referência textual ao shortcode, eliminando uma possível fonte de reprocessamento.

## 0.3.1

- Corrigida a expansão recursiva do shortcode na seção “Sobre o módulo”, que podia duplicar a interface em construtores de página que reprocessam a saída.

## 0.3.0

- Removido o iframe Stitch para permitir herança direta dos tokens centrais.
- Portal UI passou a carregar o PWL Design System 1.3.1 no contexto `portal` e a usar componentes oficiais.
- Admin UI carrega `pwl_core_admin_ui_enqueue()` apenas no hook confirmado `plataforma_page_pwl-encomendas`.
- Composição Portal organizada em colunas com CSS estrutural e espaçamentos oficiais, sem cores ou tipografia locais.
- Shortcode voltou a renderizar os dados e ações conectados ao backend do módulo.
- Documentadas as versões oficiais inspecionadas e as validações de integração ainda necessárias.

## 0.2.1

- Removido o cabeçalho superior de navegação e o rodapé corporativo do HTML Stitch.
- Removido o espaçamento superior reservado ao cabeçalho fixo removido.
- Mantidos o cabeçalho de título e a faixa de resumo que pertencem ao conteúdo do módulo.

## 0.2.0

- Transplantado o HTML Stitch para o shortcode usando iframe `srcdoc`.
# 0.7.24
- Adicionada aba administrativa WhatsApp restrita a administradores para guardar Meta App ID e Embedded Signup Configuration ID.
- Adicionado botão de teste do Embedded Signup para onboarding de WhatsApp Business App e registro de retorno diagnóstico protegido por nonce.
- Definido o domínio do portal como referência de configuração do SDK; nenhum token de acesso é coletado ou armazenado por esta etapa.
- A interface informa que o diagnóstico não conclui a conexão de produção nem substitui troca do código OAuth, registro do número ou webhook.

# 0.8.0
- Adicionadas tabelas para grupos de recebimento, composição dos grupos e snapshot de destinatários por encomenda.
- Admin UI ganhou a aba **Grupos de Recebimento** para criar, editar e ativar/desativar grupos compostos por colaboradores ativos.
- Grupos ativos aparecem no topo do campo **Colaborador destinatário**, ordenados alfabeticamente antes dos colaboradores também ordenados alfabeticamente.
- Encomendas para grupos geram notificações individuais para cada membro no Portal/Windows e e-mail, com status por pessoa e agregado no registro da encomenda. O snapshot preserva os destinatários para reenvios, mesmo após alterações no grupo.

# 0.7.27
- O diagnóstico distingue a autorização OAuth do início e conclusão do evento WA_EMBEDDED_SIGNUP.
- Quando só há callback OAuth, a tela informa que o Embedded Signup não foi confirmado e direciona a conferência para a configuração associada ao Configuration ID.

# 0.7.26
- Atualizado o lançamento do Embedded Signup para remover `sessionInfoVersion` legado e manter o onboarding de WhatsApp Business App.
- Diagnóstico agora reconhece o evento de conclusão `FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING`.

# 0.7.25
- Diagnóstico do Embedded Signup agora diferencia autorização, cancelamento e evento de conclusão recebido da Meta.
- Retorno de callback mostra o status OAuth e se houve código, sem salvar ou expor o código/token.



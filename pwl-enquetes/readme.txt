=== PWL Enquetes ===
Contributors: pwl
Tags: enquete, intranet, votacao
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.16.11

Plugin para criar e publicar enquetes internas na intranet PWL.

== Instalacao ==
1. No WordPress, acesse Plugins > Adicionar plugin > Enviar plugin.
2. Selecione o arquivo pwl-enquetes-v2.16.11.zip.
3. Instale e ative o plugin.
4. No menu Plataforma > Enquetes, crie uma nova enquete e publique.
5. Copie o shortcode exibido na lateral.
6. Cole o shortcode em um widget Shortcode do Elementor ou no editor da pagina.

Exemplo:
[pwl_enquete id="123"]

== Recursos ==
* Uma ou multiplas alternativas por enquete.
* Alternativas aceitam texto, HTML permitido e shortcodes de widgets.
* Um voto por usuario WordPress.
* Resultados sempre visiveis, depois do voto ou somente para administradores.
* Encerramento manual ou automatico por data e horario.
* Resultados no painel administrativo.
* Layout responsivo alinhado a identidade visual da PWL.
* Migracao automatica de enquetes, configuracoes e votos de instalacoes anteriores.
* Exclusao individual de votos com justificativa obrigatoria de no minimo 15 caracteres.
* Confirmacao em duas etapas antes da exclusao definitiva.
* Historico permanente com voto original, colaborador, responsavel, justificativa, data, IP e navegador.
* Exclusao restrita por padrao a manage_options e personalizavel pelo filtro pwl_enquetes_delete_votes_capability.

== Atualizacao ==
Antes de instalar esta versao, desative o plugin anterior. Depois, instale e ative o PWL Enquetes. A migracao ocorre automaticamente e nao exclui os dados de origem.

== Privacidade ==
Os votos sao vinculados internamente ao ID do usuario WordPress para impedir participacoes repetidas. O nome do usuario nao e exibido nos resultados.


== Changelog ==
= 2.6.0 =
* Adicionada etapa de revisao antes do envio do voto no frontend React.
* Adicionado indicador de progresso e contador de alternativas.
* Mantidos endpoint, nonce, resultados e fallback PHP.

= 2.4.0 =
* Tela administrativa registrada dentro de Plataforma, preservando capacidades, dados e shortcodes.

= 2.3.0 =
* Adicionada exclusao individual e auditada de votos nos relatorios.
* Adicionada justificativa obrigatoria com no minimo 15 caracteres.
* Adicionada confirmacao em duas etapas e historico permanente da acao.

= 1.5.0 =
* Adicionado suporte a shortcodes e conteudo de widgets nas alternativas.
* Preservadas as chaves das alternativas e a compatibilidade com votos existentes.
* Relatorios e CSV usam uma representacao textual segura das alternativas.

== Changelog ==
= 2.10.0 =
* Editor React: emojis, imagens, reordenacao, exclusao e previa de alternativas integrados aos controles existentes.
* Preservados o formulario PHP e os contratos de persistencia.

== Log ==
* 2026-10-27 | 2.10.0 | Evolucao incremental do editor React administrativo.

2.11.0: Consolidação visual do editor React, com backend PHP preservado.


2.15.0 (2026-10-27)
- Refinamento visual do editor React com tokens oficiais do PWL Design System.
- Contador de alternativas e orientacao contextual no editor.
- Ajustes de responsividade, estados de foco e hierarquia dos cards.
- Preservacao do formulario PHP e contratos de salvamento.

2.15.0: Confirmacao de exclusao de alternativas no editor React.

== 2.16.0 — Editor experimental ==
Status: Em Testes. Requer PWL Design System 1.3.1 para disponibilizar o editor React.
Use Experimentar editor React; Usar editor PHP restaura o formulario. Descricao formatada, imagem principal e publicacao continuam no PHP.
Build estatico incluido: Node.js nao e necessario no servidor WordPress.

= 2.16.0 =
* Adicoes: frontend React, Vite, TypeScript, Tailwind e dez componentes oficiais shadcn/ui.
* Alteracoes: editor React opt-in, classes/tokens do DS e portais Radix no escopo PWL.
* Correcoes: controles de disponibilidade e fallback preservados.
* Remocoes: nenhuma funcionalidade removida.
* Migracoes: nenhuma; schema e identificadores persistentes preservados.

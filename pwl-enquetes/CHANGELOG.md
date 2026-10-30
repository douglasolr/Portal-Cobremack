## 2.16.15 (2026-10-30)
- Abrir relatório expande e posiciona o detalhe escolhido após a navegação.
- Um refresh volta a deixar o detalhe recolhido; Histórico de Exclusões segue expansível e fechado por padrão.

## 2.16.14 (2026-10-30)
- Mantém somente a ação Nova enquete no cabeçalho e remove a ação duplicada da lista.
- Histórico de exclusões e detalhes de relatório começam recolhidos e podem ser expandidos.

## 2.16.13 (2026-10-30)
- Remove titulos e descricoes redundantes das abas Enquetes, Relatorios e Sobre.
- Remove a aba Nova enquete; criacao continua acessivel pelos botoes de acao.
- Adiciona espaco entre os cards de resumo e compatibilidade na aba Sobre.

## 2.16.12 (2026-10-30)
- Corrige os cantos da aba selecionada, mantendo o destaque laranja arredondado.

## 2.16.11 (2026-10-30)
- Remove o contorno destacado dos botoes da barra de abas administrativas.
- Remove o cartao externo em volta do formulario, mantendo os cartoes internos.

## 2.16.10 (2026-10-30)
- Remove o bloco redundante "Nova enquete / Criar enquete" acima do formulario.
- Incrementa a versao do plugin; preserva as demais telas e o frontend publico.

## 2.16.6 (2026-10-30)
- Admin UI: alinhamento de cabeçalho, abas, cartões, formulários e espaçamentos ao PWL Encomendas v1.5.7, com tokens oficiais.
- Sem alterações no Portal UI, na votação, nos contratos ou nos dados.

## 2.16.5 (2026-10-30)
- Alterada exclusivamente a Admin UI para seguir a referencia visual de Controle de Encomendas.
- Preservados Portal UI, endpoints, permissões, votacao e contratos.


## 2.16.4
- Aumentado o espaçamento entre linhas da descrição no layout de duas colunas.
## 2.16.3 | 2026-10-29
- Correção de layout em duas colunas para enquete React.

2.16.2: Layout responsivo em colunas inspirado no Stitch, sem etapas ou progresso; preserva fluxo de confirmacao.
# Changelog

## 2.16.0 — 2026-10-27
- Adições: frontend React/Vite/TypeScript/Tailwind e Button, Card, Input, Textarea, Tabs, Select, Checkbox, RadioGroup, AlertDialog e Table obtidos pelo CLI oficial shadcn/ui, base Radix.
- Alterações: editor React experimental opt-in; gravação no formulário PHP existente; classes oficiais do DS 1.3.1; portais no escopo do tema global.
- Correções: disponibilidade preservada na interface paralela; seletor de emoji usa a biblioteca existente.
- Remoções: nenhuma funcionalidade removida; assets legados mantidos.
- Migrações: nenhuma alteração de schema, options ou metadados.

O histórico anterior permanece integral no cabeçalho PHP e no readme original. Este arquivo começa nesta entrega.

## 2.16.1 (2026-10-29)
- Votacao em etapa unica, sem barra de progresso.
- Confirmacao em dialogo antes de registrar, com cancelamento preservando selecao.

# Validação 2.16.0

- TypeScript e Vite: aprovados. Bundle IIFE aproximadamente 382 kB e CSS 4,66 kB.
- PHP 7.4: sintaxe aprovada.
- npm audit --omit=dev: zero vulnerabilidades. Auditoria completa: três apontamentos baixos em dependências de desenvolvimento, sem correção forçada incompatível.
- Navegador Edge headless: formulário renderizado pelo PHP real do plugin com auxiliares WordPress simulados, sem acesso a banco.
- Aprovados: payload/nonce, título, chaves estáveis, inclusão/reordenação/remoção de alternativas, cancelar/confirmar AlertDialog e retenção de foco, tipo de votação, visibilidade, status, persistência, data/hora, emojis, eventos de seleção/remoção de imagem, prévia sem voto e alternância React/PHP preservando campos.
- Inspeção de seis capturas: 1440, 768 e 390 px, claro e escuro. Tokens de superfície dos campos comparados computacionalmente com o DS; nenhum transbordamento horizontal da página. Tabela usa rolagem interna oficial.

Limites: o stub de wp_editor é apenas um textarea na página isolada; TinyMCE, fontes remotas e aparência do wp-admin real não estão homologados. Os handlers PHP e assets públicos foram preservados por comparação com o ZIP original. Gravação no banco, permissões efetivas do Core, CSV real, popups publicados e picker completo devem ser homologados em WordPress de testes. Não declarar Produção nem equivalência integral da migração antes dessa homologação.

Roteiro WordPress: instalar o ZIP em homologação mantendo DS 1.3.1 ativo; testar usuário autorizado e negado; criar e reabrir enquete com descrição/imagem; editar em ambos os modos; votar uma vez por usuário nos shortcodes; conferir resultados, CSV, auditoria e popup; testar o fallback com JavaScript bloqueado. Não há migração nova de dados.

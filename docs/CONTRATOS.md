# Inspeção e mapa do módulo

Nome: PWL Enquetes. Área: Recursos Corporativos. Tipo: plugin WordPress. Origem: pwl-enquetes-2.15.0-dialog-react.zip. Entrega: 2.16.0. Status: Em Testes; implantação não confirmada. Referência visual inspecionada: pwl-design-system-1.3.1.zip, tokens.css, components.css e class-pwl-ds-assets.php.

## Contratos preservados
- Menu Plataforma (pwl-platform), página pwl-enquetes e redirecionamentos legados de relatórios/Sobre.
- Shortcodes: pwl_enquete, pwl_poll, cobremack_enquete, cobremack_poll.
- admin-post: pwl_salvar_enquete_admin, pwl_excluir_enquete_admin, pwl_exportar_relatorio_enquete, pwl_excluir_voto_enquete.
- AJAX autenticado: pwl_registrar_voto e cm_registrar_voto. Nenhuma nova rota REST/AJAX.
- Gestão: filtro pwl_enquetes_admin_capability, padrão read. Resultados: pwl_enquetes_results_admin_capability, padrão manage_options. Exclusão de votos: pwl_enquetes_delete_votes_capability, padrão manage_options. Permissões existentes mantidas sem ampliação.
- Nonce do formulário: pwl_salvar_enquete_admin / pwl_enquete_admin_nonce. Verificações de servidor, sanitização e escape preservados.
- CPT pwl_enquete; tabelas com sufixos pwl_enquete_votos e pwl_enquete_votos_log; DB_VERSION 1.1.0; option pwl_enquetes_db_version. Migração legada existente mantida.
- Metadados _pwl_options, _pwl_type, _pwl_results_visibility, _pwl_status, _pwl_closes_at, _pwl_image_id, _pwl_persistent_until_vote permanecem iguais. Chaves das alternativas permanecem estáveis.
- Imagens: eventos pwl:media-picker:open e pwl:media-picker:selected da PWL Biblioteca de Mídia; IDs existentes. Nenhuma biblioteca paralela.

## Design System
Usa pwl_ds_enqueue(array('all'), 'admin'), pwl-ds-components e escopo .pwl-ds. Classes oficiais pwl-button, pwl-card, pwl-input, pwl-textarea, pwl-select, pwl-tabs, pwl-choice e pwl-table são aplicadas à estrutura upstream shadcn. CSS adicional restrito a .pwle-shadcn para layout, portais Select/AlertDialog e indicadores Radix sem componente oficial equivalente. Cores, fontes, espaços e raios usam variáveis --pwl-*. Sem preflight Tailwind, tema próprio ou cópia de tokens. O DS não é redistribuído nem modificado.

## Escopo e dependências
WordPress >=6.0, PHP >=7.4; PWL Core é dono de Plataforma e políticas externas; DS >=1.3.1 necessário para oferecer o React; biblioteca de mídia existente para seleção de imagens. Sem DS/build, formulário PHP continua disponível. A descrição com wp_editor, imagem principal, publicação e botão salvar permanecem no PHP mesmo no modo React. Navegação geral, votação pública, relatórios, CSV e popups mantêm os arquivos e funções originais.

## Operação
Abrir Plataforma > Enquetes > editor e usar Experimentar editor React. Usar editor PHP retorna sem descartar campos. O modo inicial é PHP; nenhuma preferência persistente nova. O React é melhoria progressiva e possui boundary para restaurar o formulário em falha de renderização.

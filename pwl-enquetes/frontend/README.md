# Frontend PWL Enquetes 2.16.0

Node de desenvolvimento validado: 24.21.0; npm 11.19.0; npm ping respondeu PONG. Na pasta frontend: npm ci e npm run build. O lockfile fixa as versões resolvidas. Os arquivos de assets/editor-dist já estão no ZIP e são carregados como script clássico IIFE e CSS pelo WordPress. Node não é necessário no servidor. React/ReactDOM são empacotados e isolados do wp.element usado pelos scripts legados.

Componentes instalados via npx shadcn add button card input textarea tabs select checkbox radio-group alert-dialog table --yes, registro oficial https://ui.shadcn.com. Estrutura/behavior Radix preservados; classes visuais adaptadas ao DS e container de portal explícito. frontend/upstream preserva os arquivos recebidos antes das adaptações. Não executar novamente shadcn add com overwrite sem reaplicar e revisar as adaptações PWL. Referência: https://ui.shadcn.com/docs/installation/vite.

Tailwind v4 usa source(none) e fonte explícita main.tsx; nenhum preflight ou tema global é emitido. Diretivas use client (RSC) foram removidas dos componentes locais, pois este bundle roda no browser; avisos equivalentes em dependências são filtrados especificamente no Rollup. Demais avisos continuam visíveis.

Os testes de integração local são entregues no pacote de validação separado. Não simulam um banco WordPress em produção.

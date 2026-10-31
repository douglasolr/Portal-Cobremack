# Convenções do repositório

- Mantenha cada plugin em uma pasta própria na raiz do repositório, por exemplo `pwl-enquetes/`.
- Ao adicionar outro plugin, crie uma pasta irmã com o slug do plugin; não misture código, assets ou documentação entre plugins.
- Salve os pacotes instaláveis em `<pasta-do-plugin>/releases/`, separados dos arquivos-fonte.
- Nomeie cada pacote com o plugin, a versão e um resumo curto da mudança: `<plugin>-v<versao>-<resumo-em-kebab-case>.zip`.
- O resumo deve identificar a mudança principal, sem acentos ou espaços. Exemplo: `pwl-enquetes-v2.16.16-relatorio-aba-selecionada.zip`.
- Preserve no pacote a pasta do plugin como diretório raiz para instalação pelo WordPress.

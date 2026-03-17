# VETTRYX WP Architect

> ⚠️ **Atenção:** Este repositório atua exclusivamente como um **Submódulo** do ecossistema principal `VETTRYX WP Core`. Ele não deve ser instalado como um plugin standalone (isolado) nos clientes.

Este submódulo é um **Motor Dinâmico de Entidades** projetado para substituir plugins rígidos de terceiros (como CPT UI e ACF) por uma única interface inteligente, nativa e de alta performance. Ele permite a criação visual de Custom Post Types (CPTs), Taxonomias e Campos Personalizados (Meta Boxes) diretamente pelo painel do WordPress, isolando os dados da camada de design e garantindo 100% de compatibilidade com construtores visuais como o Elementor.

## 🚀 Funcionalidades

* **Construtor Visual Integrado:** Interface limpa no painel do administrador para estruturar CPTs, Categorias, Tags e múltiplos campos personalizados (Texto, Texto Longo, URL, Imagem Única e Galeria de Fotos).
* **Gerador de Shortcodes Inteligentes:** O sistema gera shortcodes de resgate em tempo real enquanto você digita (ex: `[vtx_projetos_link_url]`). O motor de renderização formata automaticamente o HTML para links, tags `<img>` e grids CSS responsivos para galerias, com suporte a consultas globais via atributo `id`.
* **Motor de Migração Silenciosa (SQL):** Proteção total contra perda de dados. Ao renomear um Slug de CPT ou ID de campo, o motor executa consultas SQL em background (`wp_posts`, `wp_postmeta`, `wp_term_taxonomy`) para atualizar as chaves de todos os registros existentes.
* **Importadores e Rastreadores Globais:** Ferramentas de 1-clique para importar estruturas legadas (Fast Gallery, Portfolio) ou rastrear o banco de dados em busca de CPTs de outros plugins ativos, permitindo uma transição indolor.
* **Seletor de Ícones Nativo (Icon Picker):** Modal embutido contendo a biblioteca completa com mais de 340 Dashicons oficiais do WordPress.
* **Taxonomias e Arquivos Turbinados:** Injeção de suporte a "Imagem Representativa" para Categorias (ideal para Loop Grids) e filtros que removem o prefixo "Archives:" padrão do WP, substituindo-os por Títulos e Descrições customizáveis para SEO.

## ⚙️ Arquitetura e Deploy (CI/CD)

Este repositório não gera mais arquivos `.zip` para instalação manual. O fluxo de deploy é 100% automatizado:

1. Qualquer push na branch `main` deste repositório dispara um webhook (Repository Dispatch) para o repositório principal do Core.
2. O repositório do Core puxa este código atualizado para dentro da pasta `/modules/architect/`.
3. O GitHub Actions do Core empacota tudo e gera uma única Release oficial.

## 📖 Como Usar

Uma vez que o **VETTRYX WP Core** esteja instalado e o módulo Architect ativado no painel da agência:

1. **Construção da Entidade:** Acesse o menu **Architect** no painel. Clique em "+ Adicionar Nova Entidade" ou utilize o importador dinâmico para clonar um CPT existente.
2. **Configuração de Campos:** Defina os nomes, taxonomias, selecione o ícone e crie os Meta Boxes desejados. Role a página e clique em "Salvar Estruturas".
3. **Preenchimento:** O novo menu do seu CPT (ex: "Projetos") aparecerá na barra lateral. O cliente final acessa, cadastra o post e preenche os campos personalizados de forma limpa, sem quebrar o layout.
4. **Exibição no Front-end (Elementor):**
   * Copie os shortcodes gerados em vermelho na tela do Architect.
   * No Elementor (Single Post Template ou página comum), utilize o widget de **Shortcode** e cole os códigos (ex: `[vtx_projetos_categoria_capa]`, `[vtx_projetos_galeria]`).
   * O Architect injeta o conteúdo formatado automaticamente na tela.

---

**VETTRYX Tech**
*Transformando ideias em experiências digitais.*

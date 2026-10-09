# Lumen — versão PHP + MySQL para XAMPP

Esta versão converte o protótipo original para PHP com persistência em MySQL, mantendo os assets e o `styles.css` original. O arquivo `public/assets/styles.css` é uma cópia do estilo enviado; os complementos estão em `public/assets/app.css`.

## Instalação no XAMPP

1. Copie a pasta `lumen` inteira para `C:\xampp\htdocs\lumen`.
2. Abra o XAMPP e inicie **Apache** e **MySQL**.
3. Entre no phpMyAdmin (`http://localhost/phpmyadmin`), clique em **Importar** e importe `database.sql`.
4. Confira `config/config.php`:
   - `DB_USER` normalmente é `root`;
   - `DB_PASS` normalmente fica vazio em instalações locais;
   - `APP_URL` deve ser `http://localhost/lumen/public`.
5. Instale e inicie o [Ollama](https://ollama.com/) e baixe o modelo configurado: `ollama pull qwen2.5:3b`. O serviço precisa estar disponível em `http://127.0.0.1:11434`; ajuste `OLLAMA_URL` ou `OLLAMA_MODEL` em `config/config.php` se necessário.
6. Abra `http://localhost/lumen/public/`.
7. Dê permissão de escrita para `storage/uploads` se o Windows pedir.

## Acessos de demonstração

- Administradora: `admin@lumen.org` / `Lumen@123`
- Beneficiária: `ana.silva@email.com` / `Lumen@123`

Altere as senhas após a instalação.

## O que foi implementado

- Login por perfil: beneficiária e equipe (admin, apoiador ou profissional).
- Cadastro com nome, CPF, RG, endereço, CEP, telefone, e-mail e confirmação de senha.
- Recuperação de senha com token expirável. O envio usa `mail()` do PHP; para receber e-mails no Windows, configure SMTP no `php.ini` ou utilize um relay SMTP.
- Cadastro de contatos de emergência com nome, e-mail, telefone e endereço.
- Convite por e-mail com link `convite.php?token=...` para o contato criar a conta.
- Notificações de SOS por e-mail e links de WhatsApp prontos. O WhatsApp precisa de uma conta/provedor oficial para envio automático; sem credenciais, o sistema deixa a mensagem preparada e clicável em `Notificações`.
- Solicitação de atendimento com as categorias solicitadas.
- Denúncia com categoria, quando aconteceu, segurança, local, relato e anexos PDF/JPG/PNG de até 10 MB.
- Triagem administrativa: abrir o caso, aprovar e encaminhar ao órgão de apoio, ou desaprovar com justificativa.
- Recurso da beneficiária para incluir novas informações e provas em denúncias desaprovadas.
- Histórico, perfil, rede de apoio, configurações, SOS, tela “Sobre o Lumen”, central de ajuda e acessibilidade.
- Exportação CSV da tabela de denúncias.

## Produção

Antes de publicar, altere `APP_DEBUG`, `APP_URL`, as credenciais do banco, configure HTTPS, SMTP, backups e um provedor oficial de WhatsApp. Nunca mantenha as credenciais de demonstração em produção.

## Painel administrativo LUMEN

A área de equipe oferece navegação por Dashboard, Relatórios e estatísticas, Notificações, Denúncias, Solicitações, Beneficiárias, Rede de apoio, Segurança e auditoria e Configurações. O layout estende os estilos do projeto original e é responsivo para telas menores.

### Banco de dados

Importe o `database.sql` atualizado em uma instalação nova. Para uma base existente, execute as definições `CREATE TABLE` de `audit_logs` e `support_request_history` que estão ao final do arquivo. As tabelas de auditoria não devem ser apagadas como parte do arquivamento de casos.

### Privacidade e acompanhamento

- Consultar os detalhes de uma denúncia ou perfil de beneficiária cria evento na trilha de auditoria (autor, tipo de ação, registro e horário; IP capturado pelo servidor).
- Alterações de status de denúncias, solicitações, beneficiárias e acessos de equipe geram eventos auditáveis. Mudanças de status de solicitações mantêm histórico separado.
- O log detalhado e gestão da equipe são restritos ao papel `admin`; ativar/desativar contas preserva os registros históricos.
- O cadastro público cria somente perfil de beneficiária. Contas de equipe são criadas dentro da área restrita a administradoras.
- Os relatórios estatísticos apresentam agregados e evitam exportar relato, endereço ou contato pessoal.
- Encaminhamento e encerramento administrativo não devem ser interpretados como confirmação de que a situação de violência terminou. O acompanhamento requer fluxo humano e protocolo local adequado.

Esta implementação é uma base funcional de demonstração. Antes de uso real com dados de violência doméstica, realize revisão de segurança independente, defina matriz formal de acesso por função, política de retenção, controles de sessão, backups protegidos, HTTPS e plano de resposta a incidentes. Os dados e credenciais de demonstração não são adequados para produção.


### Login da beneficiária e acessibilidade

- “Lembrar de mim” cria um token revogável por 14 dias em cookie `HttpOnly`; o servidor guarda somente o hash do token. O e-mail pode ficar lembrado no dispositivo, mas a senha nunca é armazenada. Sair da conta revoga o token e limpa o e-mail local. Não habilite essa opção em aparelhos compartilhados.
- Em uma instalação existente, importe `database-migration-remember.sql` ou execute a criação da tabela `remember_tokens` no `database.sql` atualizado antes de usar o checkbox.
- No topo da área da beneficiária, o menu de perfil oferece Perfil, Editar dados, Notificações, Sair da conta, Privacidade e Sobre. O menu de acessibilidade tem controles de tamanho da letra, tema claro/escuro e alto contraste; as preferências são guardadas no navegador.
- O formulário de entrada permite mostrar/ocultar a senha.

### Tema escuro, ações e relatórios em PDF

- O tema escuro e o alto contraste aplicam cores próprias também às telas públicas, login, área da beneficiária, painel, formulários, tabelas, chat e modais. Tema, fonte e contraste permanecem salvos no navegador.
- Os toggles de preferências do perfil ficam guardados localmente neste navegador. O botão “Ver mais” dos registros recentes do dashboard abre o detalhe da denúncia, que mantém a auditoria já existente.
- Nas telas administrativas de relatórios e denúncias, “Exportar PDF” abre a impressão do navegador. Escolha “Salvar como PDF”. A exportação de denúncias respeita os filtros e omite nomes, contatos, relatos e anexos.
- Foram removidos os botões de login Google/Facebook de demonstração porque não havia provedor OAuth conectado; os controles exibidos agora correspondem a ações disponíveis.

## Área do apoiador (profissional, instituição/ONG e voluntário)

A rede de apoio tem uma área própria, com menu lateral enxuto: **Dashboard, Solicitações recebidas, Atendimentos, Agenda, Mensagens, Serviços oferecidos, Meu perfil, Documentos e verificação, Segurança e orientações, Central de ajuda** e **Sair**. O detalhamento de cada tela, das regras de privacidade e da trilha de auditoria está em [`docs/AREA-DO-APOIADOR.md`](docs/AREA-DO-APOIADOR.md).

### Três tipos de perfil, uma só área

| Tipo | Campos específicos | Documentos exigidos |
| --- | --- | --- |
| Profissional autônomo | Conselho profissional, número e UF do registro | Identidade, comprovante do registro, comprovante de endereço |
| Instituição / ONG | Razão social, pessoa responsável, CPF do responsável, equipe | Identidade do responsável, cartão CNPJ/estatuto, comprovante da sede |
| Voluntário(a) | Escopo supervisionado pela rede, sem registro obrigatório | Identidade e comprovante de endereço |

Todos os tipos compartilham especialidades, disponibilidade, locais de atendimento, serviços oferecidos e limite de atendimentos simultâneos.

### Fluxo de cadastro e verificação

1. Na tela de entrada, **“Quero ser apoiador(a)”** abre o cadastro público com os três tipos de perfil; os campos e a lista de documentos mudam conforme o tipo escolhido.
2. A conta é criada com situação **em análise**. Enquanto isso, o apoiador acessa a tela de verificação e o próprio cadastro, mas não recebe solicitações nem vê dados de beneficiárias.
3. O apoiador envia os documentos em **Documentos e verificação** (PDF, JPG ou PNG, até 10 MB, gravados em `storage/uploads` com nome aleatório).
4. A equipe analisa em **Apoiadores e verificação**: confere cada documento, aprova ou recusa com justificativa e decide sobre o cadastro (aprovar, não aprovar ou suspender).
5. Somente cadastros aprovados veem **Solicitações recebidas**, **Atendimentos**, **Agenda** e **Mensagens**.

### Privacidade das beneficiárias

- Antes do aceite, a solicitação mostra apenas nome abreviado (`Ana S.`), região aproximada (cidade/UF) e um resumo do relato. Telefone, e-mail, endereço completo e preferência de contato ficam ocultos.
- Depois do aceite, o apoiador passa a ver somente os dados necessários ao atendimento daquele caso, e as conversas são restritas ao vínculo criado pelo aceite.
- Consultas a solicitações, atendimentos, conversas e documentos geram evento na trilha de auditoria, visível em **Segurança e orientações**.
- O apoiador não acessa denúncias, lista de beneficiárias, relatórios estatísticos nem configurações administrativas (respostas 403).

### Banco de dados

Em instalações novas, basta importar o `database.sql` atualizado. Em bases já existentes, execute o conteúdo de `database-supporter.sql`, que cria `supporter_profiles`, `supporter_specialties`, `supporter_availability`, `supporter_locations`, `supporter_services`, `supporter_documents`, `supporter_time_off`, `support_assignments`, `support_assignment_history`, `support_conversations`, `support_messages`, `supporter_risk_reports` e `supporter_notifications`, além das contas de demonstração.

### Acessos de demonstração da rede de apoio

- Profissional autônomo verificado: `apoiador@lumen.org` / `Lumen@123`
- Instituição/ONG verificada: `ong@lumen.org` / `Lumen@123`
- Voluntária em análise: `voluntaria@lumen.org` / `Lumen@123`

Os documentos de demonstração apontam para `storage/uploads/demo-documento-verificacao.pdf`, um PDF fictício usado apenas para ilustrar a conferência.

# Área do apoiador — LUMEN

Documento de referência da área destinada à rede de apoio: **profissional autônomo**, **instituição/ONG** e **voluntário(a)**. Os três tipos usam a mesma área, com campos, documentos exigidos e permissões ajustados automaticamente.

## 1. Mapa das telas solicitadas

| # | Item solicitado | Onde está |
| --- | --- | --- |
| 1 | Cadastro e acesso | Tela de entrada, aba **Apoiador / profissional** |
| 2 | Cadastro de apoiador/profissional | Tela pública “Quero ser apoiador(a)” (`supporterOnboarding`) |
| 3 | Login do apoiador | Login com perfil `supporter`/`professional` |
| 4 | Recuperação de senha | Fluxo já existente de token expirável, disponível para todos os perfis |
| 5 | Cadastro de dados profissionais | Cadastro público (etapa 2) e **Meu perfil** |
| 6 | Envio de documentos para verificação | **Documentos e verificação** |
| 7 | Tela de aprovação do cadastro | **Verificação do cadastro** (checklist, pendências e documentos) |
| 8 | Painel do apoiador | Menu lateral exclusivo da rede de apoio |
| 9 | Dashboard do apoiador | **Dashboard** (indicadores, próximos atendimentos, mensagens, notificações) |
| 10 | Notificações | **Notificações** (`supporter-notificacoes`) e atalho do topo |
| 11 | Resumo de solicitações recebidas | Cartões de indicadores do **Dashboard** |
| 12 | Histórico de atendimentos | **Atendimentos** → aba **Histórico** |
| 13 | Solicitações de ajuda | **Solicitações recebidas** |
| 14 | Lista de solicitações recebidas | Tabela com busca, filtro de compatibilidade e recusas anteriores |
| 15 | Detalhes da solicitação | Modal de detalhes (dados restritos antes do aceite) |
| 16 | Aceitar ou recusar solicitação | Botões na lista e no detalhe; recusa exige motivo |
| 17 | Agendar atendimento | **Agenda** → “+ Agendar atendimento”, com horários livres |
| 18 | Atualizar status do atendimento | Detalhe do atendimento (em atendimento, cancelar, encerrar) |
| 19 | Histórico e detalhes dos atendimentos | Detalhe do atendimento com agendamentos e trilha do caso |
| 20 | Comunicação e atendimento | **Mensagens** |
| 21 | Chat com a beneficiária | Coluna de conversa com histórico completo |
| 22 | Detalhes da conversa | Cabeçalho da conversa (quem é, categoria, contato quando autorizado, agendamentos) |
| 23 | Agendamento de retorno | Tipo **Retorno** no agendamento e botão “Reagendar” |
| 24 | Encerramento do atendimento | Modal “Encerrar atendimento” com resultado e resumo |
| 25 | Perfil profissional | **Meu perfil** |
| 26 | Perfil do apoiador | **Meu perfil** (tipo, contatos, apresentação) |
| 27 | Editar dados pessoais e profissionais | Formulário de **Meu perfil** |
| 28 | Gerenciar especialidades | Bloco **Especialidades** em **Meu perfil** |
| 29 | Gerenciar disponibilidade e horários | Bloco **Disponibilidade e horários** |
| 30 | Gerenciar local de atendimento | Bloco **Locais de atendimento** |
| 31 | Alterar senha e configurações de segurança | **Segurança e orientações** |
| 32 | Serviços oferecidos | **Serviços oferecidos** |
| 33 | Lista de serviços oferecidos | Tabela de serviços com situação |
| 34 | Cadastro de serviço | Modal “Novo serviço” |
| 35 | Edição de serviço | Modal “Editar serviço” (com pausar, reativar e remover) |
| 36 | Gerenciamento de horários disponíveis | **Meu perfil** + **Agenda** (bloqueios de período) |
| 37 | Segurança e suporte | **Segurança e orientações** |
| 38 | Orientações para atendimento seguro | Cartões de orientação em **Segurança e orientações** |
| 39 | Canal para reportar risco ou abuso | Formulário “Reportar risco ou abuso” + acompanhamento dos relatos |
| 40 | Central de ajuda | **Central de ajuda** |
| 41 | Perguntas frequentes | Lista de perguntas e respostas na **Central de ajuda** |
| 42 | Sair da conta | Botão **Sair** no rodapé do menu |

## 2. Regras de acesso

- `supporter` e `professional` entram na área do apoiador; `admin` mantém a área da equipe; `beneficiary` mantém a área da beneficiária.
- `guard_supporter_working()` exige cadastro aprovado para **Solicitações recebidas**, **Atendimentos**, **Agenda** e **Mensagens**. Cadastros em análise recebem `403` com a orientação de concluir a verificação.
- O aceite cria o vínculo (`support_assignments`) e a conversa (`support_conversations`). Todo o restante da área parte desse vínculo.
- Atendimentos de outros apoiadores retornam `404`, e conversas de terceiros também.
- Papéis de apoiador não acessam denúncias, beneficiárias, relatórios nem configurações (`403`).

## 3. Privacidade e minimização de dados

| Momento | O que o apoiador vê |
| --- | --- |
| Antes do aceite | Nome abreviado, região aproximada (cidade/UF), categoria e resumo do relato |
| Depois do aceite | Nome completo, contato, preferência de contato, relato integral, agendamentos e conversa |
| Outros apoiadores | Nada do caso |
| Equipe administrativa | Cadastro, documentos e trilha de auditoria, para fins de verificação |

O auxiliar `mask_region()` extrai apenas a cidade/UF do endereço e devolve “Não informada” quando não é possível identificar a região com segurança.

## 4. Auditoria

Eventos registrados em `audit_logs` (visíveis em **Segurança e orientações**):

- `supporter.request_viewed`, `supporter.assignment_viewed`, `supporter.conversation_viewed`
- `supporter.assignment_accepted`, `supporter.assignment_status_changed`, `supporter.assignment_closed`
- `supporter.appointment_saved`, `supporter.appointment_status_changed`, `supporter.timeoff_added`
- `supporter.profile_updated`, `supporter.specialty_added`, `supporter.availability_updated`, `supporter.location_saved`
- `supporter.service_created`, `supporter.service_updated`, `supporter.service_deleted`
- `supporter.document_uploaded`, `supporter.document_deleted`, `supporter.password_changed`
- `supporter.risk_reported`

A equipe registra `admin.supporter_decided`, `admin.supporter_document_reviewed` e `admin.risk_report_updated`.

## 5. Documentos e arquivos

- Formatos aceitos: PDF, JPG e PNG, até 10 MB.
- Os arquivos ficam em `storage/uploads` com nome aleatório; o nome original é preservado apenas no banco.
- O download é feito por `public/documento.php?id=...`, restrito à equipe, com registro de auditoria a cada consulta.

## 6. Encerramento e continuidade

- O encerramento exige resultado e resumo do atendimento; a conversa passa a somente leitura e a solicitação é marcada como resolvida.
- O histórico do caso permanece disponível em **Atendimentos → Histórico**, com os agendamentos e as movimentações.
- Relatos de risco continuam acompanháveis pelo apoiador, que recebe retorno da equipe na mesma tela.

# 🔧 Plugin de Manutenção Preventiva para GLPI

[![Licença GPLv2+](https://img.shields.io/badge/license-GPLv2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Versão GLPI](https://img.shields.io/badge/GLPI-11.0.x-success)](https://glpi-project.org)
[![GitHub Release](https://img.shields.io/github/v/release/willoliveiras/glpi-preventive-maintenance)](https://github.com/willoliveiras/glpi-preventive-maintenance/releases)
[![GitHub Stars](https://img.shields.io/github/stars/willoliveiras/glpi-preventive-maintenance?style=social)](https://github.com/willoliveiras/glpi-preventive-maintenance/stargazers)

Plugin para gestão automatizada de manutenções preventivas no GLPI

---

## 🌟 Recursos Principais

✔ Cadastro de planos de manutenção por entidade

✔ Alertas visuais 

✔ Geração automática de tickets para manutenções urgentes

✔ Atualização automatica da data de manutenção assim que o chamado é resolvido

✔ Lista de manutenção separada por entidade


🔧 Funcionalidades Principais
--


1. Agendamento Inteligente
    Defina intervalos personalizados (diário, semanal, mensal, anual)

2. Calendário interativo com cálculo automático de datas

3. Automatização
    Tickets automáticos para manutenções atrasadas (configurável)

4. Atualização da data de manutenção automatica se Ticketsautomaticos estiver habilitado

5. Status visual por cores (✅ Em dia / ⚠️ Atenção / ❌ Urgente)

6. Filtros por técnico, entidade ou data

7. Progresso em porcentagem para cada item

8. Auto Ticket: Habilite/desative a criação automática

9. Permissões: Controle acesso por perfil (leitura, edição, exclusão)


 🚀 Instalação
--

**Requisitos:** GLPI 11.0.x e PHP 8.2+ (para GLPI 9.x/10.x use a versão 1.x do plugin)

1. Baixe a última versão [aqui](https://github.com/willoliveiras/glpi-preventive-maintenance/releases)
2. Extraia para: `/glpi/plugins/preventivemaintenance`
3. Instale e ative em: `Configurar > Plugins`
   - Atualizando da 1.x: substitua os arquivos e clique em **Atualizar**; as tabelas existentes são migradas automaticamente
4. Configure: Administração > Perfis > [seu usuario]> Manutenção Preventiva


📸 Demonstração
--

Painel Principal	
![principal](https://github.com/user-attachments/assets/039e387d-f9f8-4fb2-a325-9a34970950e3)

Chamados automaticos
![chamados](https://github.com/user-attachments/assets/35cbd741-942a-4aa9-90bf-4ebfd2f22c83)

Cadastro de Nova manutenção
![image](https://github.com/user-attachments/assets/0e2d4224-62ae-4b1c-846e-882feec4b375)

Configurações
![image](https://github.com/user-attachments/assets/15aec9dd-a8a1-4b79-b010-813d3f544902)


💰 Ajude Este Projeto
--

Se esse projeto te ajudou,
mantenha este plugin ativo com uma doação via PIX:

![IMG_20250528_212814 (1)](https://github.com/user-attachments/assets/0bceee5a-fbd5-4807-85e5-55222f45917d)



Ou, se preferir, você pode copiar o código PIX da imagem abaixo:

    
    00020126720014BR.GOV.BCB.PIX0136db70b7e3-711a-4774-8884-7275386521e40210Obrigado!!5204000053039865802BR5925William de Oliveira Santo6009SAO PAULO621405101SdXWYH7RP6304D3A2


📜 Licença
--
Licenciado sob GNU GPLv2+

Desenvolvido por:
© 2025 WIDA - Work Information Development Analytics

www.widatecnologia.com.br

🔧 Manutenção preventiva = Menos falhas + Mais produtividade!
--


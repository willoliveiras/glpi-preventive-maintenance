<?php

/**
 * -------------------------------------------------------------------------
 * Plugin de Manutenção Preventiva para GLPI
 * -------------------------------------------------------------------------
 *
 * LICENÇA
 *
 * Este arquivo é parte do Plugin de Manutenção Preventiva.
 *
 * Manutenção Preventiva é um software livre; você pode redistribuí-lo e/ou modificar
 * sob os termos da Licença Pública Geral GNU conforme publicada pela
 * Free Software Foundation; ou versão 2 da Licença, ou
 * (a seu critério) qualquer versão posterior.
 * 
 * Manutenção Preventiva é distribuído na esperança de que seja útil,
 * mas SEM QUALQUER GARANTIA; sem mesmo a garantia implícita de
 * COMERCIALIZAÇÃO ou ADEQUAÇÃO A UM DETERMINADO FIM. Veja o
 * GNU General Public License para mais detalhes.
 *
 * Você deve ter recebido uma cópia da Licença Pública Geral GNU
 * junto com o Manutenção Preventiva. Se não, veja <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 William Oliveira Santos / WIDA Work Information Development Analytics
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      [URL do seu plugin ou repositório GitHub]
 * -------------------------------------------------------------------------
 */

/**
 * -------------------------------------------------------------------------
 * Preventive Maintenance plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Preventive Maintenance.
 *
 * Preventive Maintenance is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * Preventive Maintenance is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Preventive Maintenance. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 William Oliveira Santos / WIDA Work Information Development Analytics
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      [Your Plugin URL or GitHub Repository]
 * -------------------------------------------------------------------------
 */
 
 
 

// Inclui arquivos necessários do GLPI e verifica permissões
// Includes required GLPI files and checks permissions
include('../../../inc/includes.php');
Session::checkRight('plugin_preventivemaintenance', CREATE);

// Conexão manual com o banco de dados
// Manual database connection
$DB = new DB();
$is_edit = isset($_GET['id']);
$pm = new PluginPreventivemaintenancePreventivemaintenance();
$item_data = [];
$selected_entity_id = 0;

// Se estiver editando, carrega os dados existentes
// If editing, loads existing data
if ($is_edit) {
    $id = (int)$_GET['id'];
    if (!$pm->getFromDB($id)) {
        Session::addMessageAfterRedirect(__('Registro não encontrado'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }
    $item_data = $pm->fields;
    $selected_entity_id = $item_data['entities_id'];
}

// Busca os técnicos responsáveis (usuários com perfil de técnico)
// Finds responsible technicians (users with technician profile)
$technicians = [];
$user = new User();
$profile = new Profile();
$profile_user = new Profile_User();

// Busca o ID do perfil de técnico
// Finds technician profile ID
$technician_profile = $profile->find(['name' => 'Technician']);
$technician_profile_id = key($technician_profile);

if ($technician_profile_id) {
    $profile_users = $profile_user->find(['profiles_id' => $technician_profile_id]);
    
    foreach ($profile_users as $pu) {
        $user->getFromDB($pu['users_id']);
        if ($user->fields['is_active']) {
            $technicians[$user->getID()] = $user->getName();
        }
    }
}

// Processamento do formulário quando enviado
// Form processing when submitted
if (isset($_POST['add'])) {
    try {
        // Verificação de segurança CSRF
        // CSRF security check
        if (!isset($_POST['_glpi_csrf_token'])) {
            throw new Exception(__('Token de segurança ausente. Recarregue a página e tente novamente.'));
        }

        // Validação da entidade
        // Entity validation
        if (!isset($_POST['entities_id']) || empty($_POST['entities_id'])) {
            throw new Exception(__('O campo entidade é obrigatório.'));
        }

        $selected_entity_id = (int)$_POST['entities_id'];
        error_log("[ENTIDADE] Valor recebido: " . $selected_entity_id);
        
        if ($selected_entity_id <= 0) {
            throw new Exception(__('Selecione uma entidade válida.'));
        }

        $entity = new Entity();
        if (!$entity->getFromDB($selected_entity_id)) {
            throw new Exception(__('A entidade selecionada não existe no sistema.'));
        }

        // Validação do computador
        // Computer validation
        if (!isset($_POST['items_id']) || empty($_POST['items_id'])) {
            throw new Exception(__('Selecione um computador válido.'));
        }

        $computer_id = (int)$_POST['items_id'];
        $computer = new Computer();
        if (!$computer->getFromDB($computer_id)) {
            throw new Exception(__('Computador selecionado não encontrado.'));
        }

        // Verifica se o computador pertence à entidade
        // Checks if computer belongs to entity
        if ($computer->fields['entities_id'] != $selected_entity_id) {
            throw new Exception(__('O computador selecionado não pertence à entidade escolhida.'));
        }

        // Verifica se já existe manutenção para este computador
        // Checks if maintenance already exists for this computer
        $existing = $pm->find([
            'items_id' => $computer_id,
            'itemtype' => 'Computer'
        ]);
        
        if ($is_edit) {
            unset($existing[$id]);
        }
        
        if (count($existing) > 0) {
            throw new Exception(sprintf(
                __('Já existe uma manutenção cadastrada para o computador %s (ID: %d)'),
                $computer->getName(),
                $computer_id
            ));
        }

        // Prepara os dados para gravação
        // Prepares data for saving
        $input = [
            'name' => $_POST['name'] ?? '',
            'entities_id' => $selected_entity_id,
            'is_recursive' => 0,
            'technician_id' => (int)$_POST['technician_id'],
            'items_id' => $computer_id,
            'itemtype' => 'Computer',
            'last_maintenance_date' => $_POST['last_maintenance_date'] ?? null,
            'next_maintenance_date' => $_POST['next_maintenance_date'],
            'maintenance_interval' => 30
        ];

        // Cálculo do intervalo de manutenção
        // Maintenance interval calculation
        if (!empty($_POST['last_maintenance_date']) && !empty($_POST['next_maintenance_date'])) {
            $last = new DateTime($_POST['last_maintenance_date']);
            $next = new DateTime($_POST['next_maintenance_date']);
            $interval = $last->diff($next)->days;
            $input['maintenance_interval'] = $interval;
        }

        error_log("[DADOS] Input preparado: " . print_r($input, true));

        // GRAVAÇÃO MANUAL NO BANCO DE DADOS
        // MANUAL DATABASE SAVE
        if ($is_edit) {
            $input['id'] = $id;
            
            // Atualização manual
            // Manual update
            $query = "UPDATE glpi_plugin_preventivemaintenance_preventivemaintenances SET
                      name = '".$DB->escape($input['name'])."',
                      entities_id = ".(int)$input['entities_id'].",
                      is_recursive = 0,
                      technician_id = ".(int)$input['technician_id'].",
                      items_id = ".(int)$input['items_id'].",
                      itemtype = 'Computer',
                      last_maintenance_date = ".(!empty($input['last_maintenance_date']) ? "'".$DB->escape($input['last_maintenance_date'])."'" : "NULL").",
                      next_maintenance_date = '".$DB->escape($input['next_maintenance_date'])."',
                      maintenance_interval = ".(int)$input['maintenance_interval']."
                      WHERE id = ".(int)$input['id'];
            
            error_log("[QUERY] Update: " . $query);
            $result = $DB->query($query);
            
            if (!$result) {
                error_log("[ERRO] Query falhou: " . $DB->error());
                throw new Exception(__('Erro ao atualizar no banco de dados.'));
            }
            
            Session::addMessageAfterRedirect(__('Manutenção atualizada com sucesso!'), true, INFO);
        } else {
            // Inserção manual
            // Manual insert
            $query = "INSERT INTO glpi_plugin_preventivemaintenance_preventivemaintenances
                     (name, entities_id, is_recursive, technician_id, items_id, itemtype, 
                      last_maintenance_date, next_maintenance_date, maintenance_interval)
                      VALUES (
                      '".$DB->escape($input['name'])."',
                      ".(int)$input['entities_id'].",
                      0,
                      ".(int)$input['technician_id'].",
                      ".(int)$input['items_id'].",
                      'Computer',
                      ".(!empty($input['last_maintenance_date']) ? "'".$DB->escape($input['last_maintenance_date'])."'" : "NULL").",
                      '".$DB->escape($input['next_maintenance_date'])."',
                      ".(int)$input['maintenance_interval']."
                      )";
            
            error_log("[QUERY] Insert: " . $query);
            $result = $DB->query($query);
            
            if (!$result) {
                error_log("[ERRO] Query falhou: " . $DB->error());
                throw new Exception(__('Erro ao gravar no banco de dados.'));
            }
            
            Session::addMessageAfterRedirect(__('Manutenção criada com sucesso!'), true, INFO);
        }

        Html::redirect('preventivemaintenance.php');
        
    } catch (Exception $e) {
        error_log("[ERRO] Processamento: " . $e->getMessage());
        Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
        Html::back();
    }
}

// Configuração do formulário
// Form configuration
$entity = new Entity();
$entities = $entity->find(['id' => $_SESSION['glpiactiveentities']]);

$computer = new Computer();
$all_computers = $computer->find(['is_deleted' => 0], "name ASC");

$existing_maintenances = $pm->find(['itemtype' => 'Computer']);
$blocked_computers = [];
foreach ($existing_maintenances as $maintenance) {
    if ($is_edit && $maintenance['id'] == $item_data['id']) continue;
    $blocked_computers[] = $maintenance['items_id'];
}

$available_computers = array_filter($all_computers, function($comp) use ($blocked_computers, $is_edit, $item_data) {
    if ($is_edit && $comp['id'] == $item_data['items_id']) {
        return true;
    }
    return !in_array($comp['id'], $blocked_computers);
});

$token = Session::getNewCSRFToken();

// Exibe o cabeçalho do GLPI
// Displays GLPI header
Html::header(
    __('Manutenção Preventiva', 'preventivemaintenance'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'preventivemaintenance'
);
?>

<!-- Estilos CSS para a interface -->
<!-- CSS styles for interface -->
<style>
    body {
        background-color: #cacccf !important;
    }
    
    #step2 {
        display: none;
    }
    .form-section {
        margin-bottom: 15px;
    }
    .form-section label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .form-control, .form-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    /* Estilo específico para o dropdown de técnico */
    /* Specific style for technician dropdown */
    select[name='technician_id'] {
        width: 100% !important;
    }
    .required {
        color: #dc3545;
    }
    .btn {
        padding: 8px 16px;
        border-radius: 4px;
    }
    .entity-info {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    .custom-footer {
        text-align: center;
        padding: 20px;
        margin-top: 40px;
        color: #6c757d;
        font-size: 0.9rem;
        border-top: 1px solid #e0e0e0;
        background-color: #f8f9fa;
    }
    /* Estilos para o datepicker com intervalo */
    /* Styles for datepicker with interval */
    .ui-datepicker {
        width: 350px !important;
        padding: 10px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
    }
    .ui-datepicker-header {
        background: #f8f9fa;
        border-radius: 6px 6px 0 0;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ui-datepicker-title {
        font-weight: bold;
        display: flex;
        gap: 10px;
    }
    .ui-datepicker-month, .ui-datepicker-year {
        padding: 3px 5px;
        border-radius: 3px;
        border: 1px solid #ced4da;
    }
    .ui-datepicker-prev, .ui-datepicker-next {
        position: relative;
        top: auto;
        left: auto;
        right: auto;
        cursor: pointer;
        padding: 3px 8px;
        border-radius: 3px;
        background: #f0f0f0;
    }
    .ui-datepicker-prev:hover, .ui-datepicker-next:hover {
        background: #e0e0e0;
    }
    .ui-datepicker-calendar {
        width: 100%;
        margin-top: 10px;
    }
    .ui-datepicker-interval {
        padding: 10px;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin: -10px -10px 10px -10px;
        border-radius: 8px 8px 0 0;
    }
    .ui-datepicker-interval select {
        padding: 6px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background: white;
        flex-grow: 1;
    }
    .ui-datepicker-interval button {
        padding: 6px 12px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        flex-grow: 1;
    }
    .ui-datepicker-interval button:hover {
        background: #45a049;
    }
</style>

<!-- HTML principal do formulário -->
<!-- Main form HTML -->
<div class='plugin-preventive-maintenance-container'>
    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h2>
            <i class='fas fa-calendar-check me-2'></i>
            <?php echo $is_edit ? __('Editar Manutenção') : __('Nova Manutenção'); ?>
        </h2>
        <a href='preventivemaintenance.php' class='btn btn-outline-primary'>
            <i class='fas fa-arrow-left me-2'></i><?php echo __('Voltar'); ?>
        </a>
    </div>

    <div class='card'>
        <div class='card-body'>
            <form method='post' id='preventive_maintenance_form'>
                <?php echo Html::hidden('_glpi_csrf_token', ['value' => $token]); ?>
                <input type='hidden' name='add' value='1'>
                
                <!-- STEP 1 - Somente seleção da entidade -->
                <!-- STEP 1 - Only entity selection -->
                <div id='step1'>
                    <div class='form-section'>
                        <label for='entities_id_select'><?php echo __('Entidade'); ?> <span class='required'>*</span></label>
                        <select name='entities_id_select' id='entities_id_select' class='form-select' required>
                            <option value=''><?php echo __('Selecione uma entidade'); ?></option>
                            <?php foreach ($entities as $ent) {
                                $selected = ($is_edit && $item_data['entities_id'] == $ent['id']) ? 'selected' : '';
                                echo "<option value='{$ent['id']}' $selected>{$ent['completename']}</option>";
                            } ?>
                        </select>
                    </div>
                    
                    <div class='d-flex justify-content-end mt-4'>
                        <button type='button' class='btn btn-primary' id='nextButton'>
                            <i class='fas fa-arrow-right me-2'></i><?php echo __('Próximo'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- STEP 2 - Demais campos -->
                <!-- STEP 2 - Other fields -->
                <div id='step2'>
                    <input type='hidden' name='entities_id' id='entities_id' value='<?php echo $is_edit ? $item_data['entities_id'] : ''; ?>'>
                    
                    <div class='entity-info'>
                        <strong><?php echo __('Entidade selecionada:'); ?></strong>
                        <span id='selected-entity-name'></span>
                        (ID: <span id='selected-entity-id'></span>)
                    </div>
                    
                    <div class='form-section'>
                        <label for='name'><?php echo __('Nome da Manutenção'); ?> <span class='required'>*</span></label>
                        <input type='text' name='name' id='name' class='form-control' 
                               value="<?php echo $is_edit ? htmlspecialchars($item_data['name']) : ''; ?>" required>
                    </div>
                    
                    <div class='form-section'>
                        <label for='technician_id'><?php echo __('Técnico Responsável'); ?> <span class='required'>*</span></label>
                        <select name='technician_id' id='technician_id' class='form-select' required>
                            <option value=''><?php echo __('Selecione um técnico responsável'); ?></option>
                            <?php 
                            foreach ($technicians as $id => $name) {
                                $selected = ($is_edit && $item_data['technician_id'] == $id) ? 'selected' : '';
                                echo "<option value='{$id}' {$selected}>{$name}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class='form-section'>
                        <label for='items_id'><?php echo __('Computador'); ?> <span class='required'>*</span></label>
                        <select name='items_id' id='items_id' class='form-select' required>
                            <option value=''><?php echo __('Selecione um computador'); ?></option>
                            <?php 
                            if ($is_edit) {
                                $computer->getFromDB($item_data['items_id']);
                                echo "<option value='{$item_data['items_id']}' selected>{$computer->getName()}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class='form-section'>
                        <label for='last_maintenance_date'><?php echo __('Última Manutenção'); ?></label>
                        <input type='text' id='last_maintenance_date' name='last_maintenance_date' 
                               value="<?php echo $is_edit ? $item_data['last_maintenance_date'] : ''; ?>" 
                               class='form-control'>
                    </div>
                    
                    <div class='form-section'>
                        <label for='next_maintenance_date'><?php echo __('Próxima Manutenção'); ?> <span class='required'>*</span></label>
                        <input type='text' id='next_maintenance_date' name='next_maintenance_date' 
                               value="<?php echo $is_edit ? $item_data['next_maintenance_date'] : ''; ?>" 
                               class='form-control interval-field' required>
                    </div>
                    
                    <div class='d-flex justify-content-between mt-4'>
                        <button type='button' class='btn btn-secondary' id='backButton'>
                            <i class='fas fa-arrow-left me-2'></i><?php echo __('Voltar'); ?>
                        </button>
                        <button type='submit' class='btn btn-success'>
                            <i class='fas fa-save me-2'></i><?php echo $is_edit ? __('Atualizar') : __('Salvar'); ?>
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Inclusão de bibliotecas JavaScript -->
            <!-- JavaScript libraries inclusion -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-i18n/1.12.1/jquery-ui-i18n.min.js"></script>
            
            <!-- Script JavaScript para funcionalidades do formulário -->
            <!-- JavaScript script for form functionalities -->
            <script>
            const computersData = <?php echo json_encode(array_values($all_computers)); ?>;
            const blockedComputers = <?php echo json_encode($blocked_computers); ?>;
            
            $(document).ready(function() {
                // Configuração de localização para português
                // Portuguese localization setup
                $.datepicker.regional['pt-BR'] = {
                    closeText: 'Fechar',
                    prevText: '&#x3C;Anterior',
                    nextText: 'Próximo&#x3E;',
                    currentText: 'Hoje',
                    monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
                    monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun',
                    'Jul','Ago','Set','Out','Nov','Dez'],
                    dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
                    dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    dayNamesMin: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    weekHeader: 'Sm',
                    dateFormat: 'yy-mm-dd',
                    firstDay: 0,
                    isRTL: false,
                    showMonthAfterYear: false,
                    yearSuffix: ''};
                $.datepicker.setDefaults($.datepicker.regional['pt-BR']);

                // Inicializa o datepicker para a última manutenção
                // Initializes datepicker for last maintenance
                $("#last_maintenance_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    showAnim: 'fadeIn',
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true,
                    onSelect: function(dateText) {
                        $(this).val(dateText);
                    },
                    beforeShow: function(input, inst) {
                        setTimeout(function() {
                            var button = inst.dpDiv.find('.ui-datepicker-current');
                            button.unbind('click').click(function() {
                                var today = new Date();
                                var formattedDate = $.datepicker.formatDate('yy-mm-dd', today);
                                $(input).val(formattedDate);
                                inst.dpDiv.hide();
                            });
                        }, 1);
                    }
                });
                
                // Inicializa o datepicker para a próxima manutenção com intervalo
                // Initializes datepicker for next maintenance with interval
                $("#next_maintenance_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    showAnim: 'fadeIn',
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true,
                    onSelect: function(dateText) {
                        $(this).val(dateText);
                    },
                    beforeShow: function(input, inst) {
                        setTimeout(function() {
                            // Configura o botão "Hoje"
                            // Configures "Today" button
                            var button = inst.dpDiv.find('.ui-datepicker-current');
                            button.unbind('click').click(function() {
                                var today = new Date();
                                var formattedDate = $.datepicker.formatDate('yy-mm-dd', today);
                                $(input).val(formattedDate);
                                inst.dpDiv.hide();
                            });
                            
                            // Adiciona controles de intervalo
                            // Adds interval controls
                            var dpDiv = $(inst.dpDiv);
                            dpDiv.find('.ui-datepicker-interval').remove();
                            
                            var controls = $(
                                '<div class="ui-datepicker-interval">' +
                                '  <span><?php echo __("Intervalo"); ?>:</span>' +
                                '  <select class="interval-value">' +
                                '    <option value="1">1 mês</option>' +
                                '    <option value="2">2 meses</option>' +
                                '    <option value="3">3 meses</option>' +
                                '    <option value="4">4 meses</option>' +
                                '    <option value="5">5 meses</option>' +
                                '    <option value="6" selected>6 meses</option>' +
                                '    <option value="7">7 meses</option>' +
                                '    <option value="8">8 meses</option>' +
                                '    <option value="9">9 meses</option>' +
                                '    <option value="10">10 meses</option>' +
                                '    <option value="11">11 meses</option>' +
                                '    <option value="12">1 ano</option>' +
                                '  </select>' +
                                '  <button type="button" class="apply-interval"><?php echo __("Aplicar"); ?></button>' +
                                '</div>'
                            );
                            
                            dpDiv.prepend(controls);
                            
                            dpDiv.find('.apply-interval').click(function() {
                                var lastDate = $("#last_maintenance_date").val();
                                if (!lastDate) {
                                    alert('<?php echo __("Informe a data da última manutenção"); ?>');
                                    return;
                                }
                                
                                var months = parseInt(dpDiv.find('.interval-value').val());
                                var date = new Date(lastDate);
                                date.setMonth(date.getMonth() + months);
                                
                                // Ajusta para o final do mês se necessário
                                // Adjusts to end of month if needed
                                var originalDay = new Date(lastDate).getDate();
                                if (date.getDate() !== originalDay) {
                                    date.setDate(0);
                                }
                                
                                var formatted = $.datepicker.formatDate('yy-mm-dd', date);
                                $("#next_maintenance_date").val(formatted).datepicker('hide');
                            });
                        }, 1);
                    }
                });

                <?php if ($is_edit) { ?>
                    // Se estiver editando, configura os valores iniciais
                    // If editing, sets initial values
                    const entityId = <?php echo $item_data['entities_id']; ?>;
                    const entityName = $(`#entities_id_select option[value='${entityId}']`).text();
                    
                    $('#entities_id').val(entityId);
                    $('#selected-entity-name').text('Entidade: ' + entityName);
                    $('#selected-entity-id').text(entityId);
                    
                    $('#step1').hide();
                    $('#step2').show();
                <?php } ?>
                
                // Evento do botão Próximo
                // Next button event
                $('#nextButton').click(function() {
                    if (!$('#entities_id_select').val()) {
                        alert('<?php echo __("Selecione uma entidade"); ?>');
                        return;
                    }
                    
                    const entityId = $('#entities_id_select').val();
                    const entityName = $('#entities_id_select option:selected').text();
                    
                    $('#entities_id').val(entityId);
                    $('#selected-entity-name').text('Entidade: ' + entityName);
                    $('#selected-entity-id').text(entityId);
                    loadComputers(entityId);
                    
                    $('#step1').hide();
                    $('#step2').show();
                });
                
                // Evento do botão Voltar
                // Back button event
                $('#backButton').click(function() {
                    $('#step2').hide();
                    $('#step1').show();
                });
                
                // Carrega os computadores disponíveis para a entidade selecionada
                // Loads available computers for selected entity
                function loadComputers(entityId) {
                    const select = $('#items_id');
                    select.find('option').not(':first').remove();
                    
                    const filteredComputers = computersData.filter(comp => {
                        return comp.entities_id == entityId && 
                               (!blockedComputers.includes(comp.id) || <?php echo $is_edit ? 'comp.id == ' . $item_data['items_id'] : 'false'; ?>);
                    });
                    
                    if (filteredComputers.length > 0) {
                        filteredComputers.forEach(comp => {
                            select.append(new Option(comp.name, comp.id));
                        });
                    } else {
                        const option = new Option('<?php echo __("Nenhum computador disponível"); ?>', '');
                        option.disabled = true;
                        select.append(option);
                    }
                }
            });
            </script>
        </div>
        <!-- Rodapé personalizado -->
        <!-- Custom footer -->
        <div class="custom-footer">
            <i class="fas fa-code"></i> <?= __('Desenvolvido por WIDA - Work Information Developments and Analytics') ?>
        </div>
    </div>
</div>

<?php
// Exibe o rodapé do GLPI
// Displays GLPI footer
Html::footer();
?>
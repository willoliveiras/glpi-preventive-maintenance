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

// No GLPI 11 este script é carregado pelo roteador do GLPI (inc/includes.php não é mais necessário)
// On GLPI 11 this script is loaded by the GLPI router (inc/includes.php is no longer needed)

// Verifica permissões
// Checks permissions
Session::checkRight('plugin_preventivemaintenance', READ);

$pm       = new PluginPreventivemaintenancePreventivemaintenance();
$list_url = PluginPreventivemaintenancePreventivemaintenance::getSearchURL();
$form_url = PluginPreventivemaintenancePreventivemaintenance::getFormURL();

// Obtém configuração do Auto Ticket
// Gets Auto Ticket configuration
if (PluginPreventivemaintenancePreventivemaintenance::getConfigValue('auto_ticket') === null) {
    PluginPreventivemaintenancePreventivemaintenance::setConfigValue('auto_ticket', '0');
}
$auto_ticket_enabled = PluginPreventivemaintenancePreventivemaintenance::isAutoTicketEnabled();

// Processa exclusão (POST + token CSRF)
// Processes deletion (POST + CSRF token)
if (isset($_POST['delete'])) {
    $id = (int) $_POST['delete'];
    if ($id > 0 && $pm->can($id, PURGE)) {
        if ($pm->delete(['id' => $id], true)) {
            Session::addMessageAfterRedirect(__('Registro apagado com sucesso!'), true, INFO);
        } else {
            Session::addMessageAfterRedirect(__('Falha ao apagar registro!'), false, ERROR);
        }
    }
    Html::redirect($list_url);
}

// Processa toggle do Auto Ticket (POST + token CSRF)
// Processes Auto Ticket toggle (POST + CSRF token)
if (isset($_POST['toggle_auto_ticket'])) {
    Session::checkRight('plugin_preventivemaintenance', UPDATE);
    $new_value = $auto_ticket_enabled ? '0' : '1';
    if (PluginPreventivemaintenancePreventivemaintenance::setConfigValue('auto_ticket', $new_value)) {
        Session::addMessageAfterRedirect(
            $new_value === '1' ? __('Auto ticket ativado com sucesso!') : __('Auto ticket desativado com sucesso!'),
            true,
            INFO
        );
    } else {
        Session::addMessageAfterRedirect(__('Falha ao atualizar a configuração do auto ticket!'), false, ERROR);
    }
    Html::redirect($list_url);
}

// Inicializa filtros
// Initializes filters
$filters = [
    'status'     => in_array($_GET['status'] ?? '', ['ontime', 'warning', 'urgent', 'undefined'], true) ? $_GET['status'] : 'all',
    'date_from'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : '',
    'date_to'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'] ?? '') ? $_GET['date_to'] : '',
    'technician' => (int) ($_GET['technician'] ?? 0),
    'entity'     => (int) ($_GET['entity'] ?? 0),
];

// Atualiza manutenções cujos tickets foram solucionados e cria tickets automáticos
// Updates maintenances whose tickets were solved and creates automatic tickets
PluginPreventivemaintenancePreventivemaintenance::cleanResolvedMaintenanceTickets();
foreach (PluginPreventivemaintenancePreventivemaintenance::processAutoTickets() as $maintenance_name) {
    Session::addMessageAfterRedirect(
        htmlescape(sprintf(__('Ticket criado automaticamente para manutenção preventiva: %s'), $maintenance_name)),
        true,
        INFO
    );
}

// Prepara critérios de busca (restritos às entidades ativas do usuário)
// Prepares search criteria (restricted to user's active entities)
$table    = PluginPreventivemaintenancePreventivemaintenance::getTable();
$criteria = getEntitiesRestrictCriteria($table, '', '', true);
if (!empty($filters['technician'])) {
    $criteria['technician_id'] = $filters['technician'];
}
if (!empty($filters['entity'])) {
    $criteria['entities_id'] = $filters['entity'];
}
if (!empty($filters['date_from'])) {
    $criteria[] = ['next_maintenance_date' => ['>=', $filters['date_from']]];
}
if (!empty($filters['date_to'])) {
    $criteria[] = ['next_maintenance_date' => ['<=', $filters['date_to']]];
}

// Busca itens
// Finds items
$all_items = $pm->find($criteria, ['entities_id', 'next_maintenance_date ASC']);

// Carrega os dados dos técnicos cadastrados nas manutenções (com nome completo)
// Loads technicians used by maintenances (with full name)
$technicians_data = [];
$technician_ids   = array_filter(array_unique(array_column($all_items, 'technician_id')));
if (!empty($technician_ids)) {
    $dbu  = new DbUtils();
    $user = new User();
    foreach ($user->find(['id' => array_values($technician_ids)]) as $tech) {
        $technicians_data[$tech['id']] = $dbu->formatUserName($tech['id'], $tech['name'], $tech['realname'], $tech['firstname']);
    }
}
asort($technicians_data, SORT_NATURAL | SORT_FLAG_CASE);

// Agrupa itens por entidade (caminho completo)
// Groups items by entity (full path)
$items_by_entity = [];
foreach ($all_items as $item) {
    $entity_path = Dropdown::getDropdownName('glpi_entities', $item['entities_id'], false, true, false, '');
    if ($entity_path === '') {
        continue;
    }

    if ($filters['status'] !== 'all') {
        $percent = PluginPreventivemaintenancePreventivemaintenance::computeProgress($item);
        $include_item = match ($filters['status']) {
            'ontime'    => $percent !== null && $percent < 80,
            'warning'   => $percent !== null && $percent >= 80 && $percent < 98,
            'urgent'    => $percent !== null && $percent >= 98,
            'undefined' => $percent === null,
        };
        if (!$include_item) {
            continue;
        }
    }

    $items_by_entity[$entity_path]['name']    = $entity_path;
    $items_by_entity[$entity_path]['items'][] = $item;
}

// Ordena entidades
// Sorts entities
uksort($items_by_entity, 'strnatcasecmp');

$csrf_token = Session::getNewCSRFToken();

// Exibe cabeçalho
// Displays header
Html::header(
    PluginPreventivemaintenancePreventivemaintenance::getTypeName(Session::getPluralNumber()),
    '',
    'plugins',
    'PluginPreventivemaintenanceMenu'
);
?>

<style>
    body {
        background-color: #cacccf !important;
    }
    .plugin-preventive-maintenance-container {
        background-color: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin: 20px auto;
        max-width: 98%;
    }
    .entity-group {
        margin-bottom: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .entity-title {
        background: #cacccf;
        padding: 15px 25px;
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        align-items: center;
    }
    .entity-title i {
        margin-right: 12px;
        color: #6c757d;
        font-size: 1.2em;
    }
    .table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }
    .table thead th {
        vertical-align: middle;
        text-align: center;
        background-color: #f1f3f5;
        font-weight: 600;
        border-bottom-width: 2px;
        color: #495057;
        padding: 12px 8px;
    }
    .table td {
        vertical-align: middle;
        padding: 10px 8px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.03);
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
    .progress {
        height: 28px;
        border-radius: 6px;
        background-color: #e9ecef;
        overflow: hidden;
    }
    .progress-bar {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        color: white;
    }
    .action-buttons {
        display: flex;
        gap: 5px;
        justify-content: center;
    }
    .advanced-filters {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
        display: none;
    }
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
    }
    .filter-group {
        flex: 1;
        min-width: 180px;
    }
    .filter-group.small {
        flex: 0.5;
        min-width: 120px;
    }
    .filter-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #495057;
        font-size: 0.9rem;
    }
    .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 10px;
    }
    .form-control {
        width: 100%;
        padding: 6px 10px;
        font-size: 0.9rem;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    .toggle-btn {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-left: 10px;
        vertical-align: middle;
    }
    .toggle-btn .toggle-knob {
        position: absolute;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: white;
        top: 2px;
        left: 2px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .toggle-btn.on {
        background: #28a745;
    }
    .toggle-btn.off {
        background: #dc3545;
    }
    .toggle-btn.on .toggle-knob {
        left: 32px;
    }
    .toggle-btn.off .toggle-knob {
        left: 2px;
    }
    .toggle-container {
        display: inline-flex;
        align-items: center;
        margin-left: 15px;
    }
    .toggle-label {
        margin-right: 5px;
        font-weight: 500;
        color: #495057;
    }
    
    /* Donation button styles */
    .donation-button {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #28a745;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
    }
    .donation-button:hover {
        background-color: #218838;
        transform: scale(1.1);
    }
    .donation-qr-container {
        position: fixed;
        bottom: 100px;
        right: 30px;
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        z-index: 1001;
        display: none;
        flex-direction: column;
        align-items: center;
        max-width: 300px;
    }
    .donation-qr-container.show {
        display: flex;
    }
    .donation-qr-code {
        width: 200px;
        height: 200px;
        margin-bottom: 15px;
        background-color: white;
        padding: 10px;
        border: 1px solid #ddd;
    }
    .donation-message {
        text-align: center;
        font-size: 14px;
        color: #333;
        margin-top: 10px;
    }
    .pix-code-container {
        width: 100%;
        margin-top: 15px;
        position: relative;
    }
    .pix-code {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        word-break: break-all;
        background-color: #f8f9fa;
    }
    .copy-pix-btn {
        position: absolute;
        right: 5px;
        top: 5px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 12px;
        cursor: pointer;
    }
    .copy-pix-btn:hover {
        background: #218838;
    }
    .copy-notification {
        position: fixed;
        bottom: 180px;
        right: 30px;
        background: #28a745;
        color: white;
        padding: 8px 15px;
        border-radius: 4px;
        z-index: 1002;
        display: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    @media (max-width: 768px) {
        .donation-button {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        .donation-qr-container {
            bottom: 80px;
            right: 20px;
            max-width: 250px;
        }
        .donation-qr-code {
            width: 180px;
            height: 180px;
        }
        .copy-notification {
            bottom: 160px;
            right: 20px;
        }
    }
</style>

<div class="plugin-preventive-maintenance-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h2 style="margin: 0;"><i class="ti ti-calendar-check" style="margin-right: 10px;"></i><?= htmlescape(__('Preventive Maintenance')) ?></h2>
        <div style="display: flex; align-items: center;">
            <?php if ($pm->canCreate()): ?>
                <a href="<?= htmlescape($form_url) ?>" class="btn btn-primary" style="margin-right: 10px;">
                    <i class="ti ti-plus"></i>&nbsp;<?= htmlescape(__('Add')) ?>
                </a>
            <?php endif; ?>
            <button type="button" id="toggleFilters" class="btn btn-outline-info">
                <i class="ti ti-filter"></i>&nbsp;<?= htmlescape(__('Filters')) ?>
            </button>

            <div class="toggle-container">
                <span class="toggle-label"><?= htmlescape(__('Auto Ticket')) ?></span>
                <?php if (Session::haveRight('plugin_preventivemaintenance', UPDATE)): ?>
                    <form method="post" action="<?= htmlescape($list_url) ?>" style="display: inline; margin: 0;">
                        <input type="hidden" name="_glpi_csrf_token" value="<?= htmlescape($csrf_token) ?>">
                        <input type="hidden" name="toggle_auto_ticket" value="1">
                        <button type="submit"
                                class="toggle-btn border-0 p-0 <?= $auto_ticket_enabled ? 'on' : 'off' ?>"
                                title="<?= htmlescape($auto_ticket_enabled ? __('Disable Auto Ticket') : __('Enable Auto Ticket')) ?>">
                            <span class="toggle-knob"></span>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="toggle-btn <?= $auto_ticket_enabled ? 'on' : 'off' ?>" style="cursor: default;">
                        <span class="toggle-knob"></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="advancedFilters" class="advanced-filters" style="<?= isset($_GET['filter_applied']) ? '' : 'display: none;' ?>">
        <form method="get" action="<?= htmlescape($list_url) ?>">
            <input type="hidden" name="filter_applied" value="1">

            <div class="filter-row">
                <div class="filter-group small">
                    <div class="filter-title"><?= htmlescape(__('Status')) ?></div>
                    <select name="status" class="form-control">
                        <?php
                        $status_options = [
                            'all'       => __('Todos'),
                            'ontime'    => __('Em dia'),
                            'warning'   => __('Atenção'),
                            'urgent'    => __('Urgente'),
                            'undefined' => __('Indefinido'),
                        ];
                        foreach ($status_options as $value => $label) {
                            $selected = $filters['status'] === $value ? 'selected' : '';
                            echo '<option value="' . htmlescape($value) . '" ' . $selected . '>' . htmlescape($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group small">
                    <div class="filter-title"><?= htmlescape(__('De')) ?></div>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlescape($filters['date_from']) ?>">
                </div>

                <div class="filter-group small">
                    <div class="filter-title"><?= htmlescape(__('Até')) ?></div>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlescape($filters['date_to']) ?>">
                </div>

                <div class="filter-group">
                    <div class="filter-title"><?= htmlescape(__('Technician')) ?></div>
                    <select name="technician" class="form-control">
                        <option value="0"><?= htmlescape(__('Todos')) ?></option>
                        <?php
                        foreach ($technicians_data as $tech_id => $tech_name) {
                            $selected = ($filters['technician'] == $tech_id) ? 'selected' : '';
                            echo '<option value="' . (int) $tech_id . '" ' . $selected . '>' . htmlescape($tech_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <div class="filter-title"><?= htmlescape(__('Entity')) ?></div>
                    <?php
                    echo Entity::dropdown([
                        'name'    => 'entity',
                        'value'   => $filters['entity'],
                        'display' => false,
                        'width'   => '100%',
                    ]);
                    ?>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="ti ti-filter"></i> <?= htmlescape(__('Aplicar')) ?>
                </button>
                <a href="<?= htmlescape($list_url) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-x"></i> <?= htmlescape(__('Limpar')) ?>
                </a>
            </div>
        </form>
    </div>

    <?php if (empty($items_by_entity)): ?>
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle"></i> <?= htmlescape(__('Nenhum registro encontrado.')) ?>
        </div>
    <?php else: ?>
        <?php foreach ($items_by_entity as $entity): ?>
            <div class="entity-group">
                <div class="entity-title">
                    <i class="ti ti-building"></i> <?= htmlescape($entity['name']) ?>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="text-align: center"><?= htmlescape(__('ID')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Nome/descr.')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Computer')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Technician')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Ult. Man.')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Prox. Man')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Status')) ?></th>
                                <th style="text-align: center"><?= htmlescape(__('Actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entity['items'] as $item): ?>
                                <?php
                                $computer      = new Computer();
                                $computer_name = $computer->getFromDB($item['items_id']) ? $computer->getName() : __('N/A');
                                $technician_name = $technicians_data[$item['technician_id']] ?? '-';

                                $percent     = PluginPreventivemaintenancePreventivemaintenance::computeProgress($item);
                                $status_html = "<span class='badge bg-secondary'>" . htmlescape(__('Undefined')) . "</span>";
                                if ($percent !== null) {
                                    if ($percent < 80) {
                                        $status_class = 'bg-success';
                                        $status_text  = __('Em dia');
                                    } elseif ($percent < 98) {
                                        $status_class = 'bg-warning';
                                        $status_text  = __('Atenção');
                                    } else {
                                        $status_class = 'bg-danger';
                                        $status_text  = __('Urgente');
                                    }

                                    $status_html = "<div class='progress'>
                                        <div class='progress-bar $status_class' role='progressbar' style='width: $percent%'>
                                            <strong>$percent% - " . htmlescape($status_text) . "</strong>
                                        </div>
                                    </div>";
                                }
                                ?>
                                <tr>
                                    <td style="text-align: center"><?= (int) $item['id'] ?></td>
                                    <td style="text-align: center"><?= htmlescape($item['name']) ?></td>
                                    <td style="text-align: center"><?= htmlescape($computer_name) ?></td>
                                    <td style="text-align: center"><?= htmlescape($technician_name) ?></td>
                                    <td style="text-align: center"><?= !empty($item['last_maintenance_date']) ? htmlescape(Html::convDate($item['last_maintenance_date'])) : '-' ?></td>
                                    <td style="text-align: center"><?= !empty($item['next_maintenance_date']) ? htmlescape(Html::convDate($item['next_maintenance_date'])) : '-' ?></td>
                                    <td style="text-align: center"><?= $status_html ?></td>
                                    <td style="text-align: center">
                                        <div class="action-buttons">
                                            <?php if ($pm->canUpdate()): ?>
                                                <a href="<?= htmlescape($form_url . '?id=' . (int) $item['id']) ?>" class="btn btn-sm btn-primary" title="<?= htmlescape(__('Edit')) ?>">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($pm->canDelete()): ?>
                                                <form method="post" action="<?= htmlescape($list_url) ?>" style="display: inline; margin: 0;"
                                                      onsubmit="return confirm(<?= htmlescape(json_encode(__('Do you really want to delete this record?'))) ?>);">
                                                    <input type="hidden" name="_glpi_csrf_token" value="<?= htmlescape($csrf_token) ?>">
                                                    <input type="hidden" name="delete" value="<?= (int) $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="<?= htmlescape(__('Delete')) ?>">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="custom-footer">
        <i class="ti ti-code"></i> <?= htmlescape(__('Developed by WIDA - Work Information Developments and Analytics')) ?>
    </div>
</div>

<!-- Donation Button and QR Code -->
<div class="donation-button" id="donationButton">
    <i class="fas fa-heart"></i>
</div>

<div class="donation-qr-container" id="donationQrContainer">
    <div class="donation-qr-code">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=00020126720014BR.GOV.BCB.PIX0136db70b7e3-711a-4774-8884-7275386521e40210Obrigado!!5204000053039865802BR5925William%20de%20Oliveira%20Santo6009SAO%20PAULO621405101SdXWYH7RP6304D3A2" 
             alt="QR Code para doação via PIX">
    </div>
    <div class="pix-code-container">
        <div class="pix-code" id="pixCode">
            00020126720014BR.GOV.BCB.PIX0136db70b7e3-711a-4774-8884-7275386521e40210Obrigado!!5204000053039865802BR5925William de Oliveira Santo6009SAO PAULO621405101SdXWYH7RP6304D3A2
        </div>
        <button class="copy-pix-btn" id="copyPixBtn">
            <i class="fas fa-copy"></i> Copiar
        </button>
    </div>
    <div class="donation-message">
        Se esse projeto te ajudou, ajude também doando
    </div>
</div>

<div class="copy-notification" id="copyNotification">
    Código PIX copiado!
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleFiltersBtn = document.getElementById('toggleFilters');
    const advancedFilters = document.getElementById('advancedFilters');
    
    toggleFiltersBtn.addEventListener('click', function() {
        if (advancedFilters.style.display === 'none') {
            advancedFilters.style.display = 'block';
            toggleFiltersBtn.classList.remove('btn-outline-info');
            toggleFiltersBtn.classList.add('btn-info');
        } else {
            advancedFilters.style.display = 'none';
            toggleFiltersBtn.classList.remove('btn-info');
            toggleFiltersBtn.classList.add('btn-outline-info');
        }
    });
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_applied')) {
        advancedFilters.style.display = 'block';
        toggleFiltersBtn.classList.remove('btn-outline-info');
        toggleFiltersBtn.classList.add('btn-info');
    }
    
    function setupMobileView() {
        if (window.innerWidth < 768) {
            const headers = Array.from(document.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    cell.setAttribute('data-label', headers[index]);
                });
            });
        }
    }
    
    // Donation button functionality
    const donationButton = document.getElementById('donationButton');
    const donationQrContainer = document.getElementById('donationQrContainer');
    const copyPixBtn = document.getElementById('copyPixBtn');
    const pixCode = document.getElementById('pixCode');
    const copyNotification = document.getElementById('copyNotification');
    
    donationButton.addEventListener('click', function(e) {
        e.stopPropagation();
        donationQrContainer.classList.toggle('show');
    });
    
    // Copy PIX code functionality
    copyPixBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const textToCopy = pixCode.textContent;
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            // Show notification
            copyNotification.style.display = 'block';
            setTimeout(function() {
                copyNotification.style.display = 'none';
            }, 2000);
        }).catch(function(err) {
            console.error('Erro ao copiar texto: ', err);
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                // Show notification
                copyNotification.style.display = 'block';
                setTimeout(function() {
                    copyNotification.style.display = 'none';
                }, 2000);
            } catch (err) {
                console.error('Fallback erro ao copiar texto: ', err);
            }
            document.body.removeChild(textarea);
        });
    });
    
    // Close QR code when clicking outside
    document.addEventListener('click', function(event) {
        if (!donationButton.contains(event.target) && 
            !donationQrContainer.contains(event.target)) {
            donationQrContainer.classList.remove('show');
        }
    });
});
</script>

<?php
Html::footer();							   
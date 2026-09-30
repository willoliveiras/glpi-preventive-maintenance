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

$pm       = new PluginPreventivemaintenancePreventivemaintenance();
$list_url = PluginPreventivemaintenancePreventivemaintenance::getSearchURL();
$is_edit  = !empty($_GET['id']);
$id       = (int) ($_GET['id'] ?? 0);
$item_data = [];

// Verifica permissões (criar ou atualizar)
// Checks permissions (create or update)
Session::checkRight('plugin_preventivemaintenance', $is_edit ? UPDATE : CREATE);

// Se estiver editando, carrega os dados existentes
// If editing, loads existing data
if ($is_edit) {
    if (!$pm->getFromDB($id) || !Session::haveAccessToEntity($pm->fields['entities_id'])) {
        Session::addMessageAfterRedirect(__('Registro não encontrado'), false, ERROR);
        Html::redirect($list_url);
    }
    $item_data = $pm->fields;
}

// Processa a seleção de perfis técnicos se enviado
// Processes technician profiles selection if submitted
if (isset($_POST['save_selected_profiles'])) {
    $profiles = array_values(array_filter(array_map('strval', (array) ($_POST['profiles'] ?? []))));
    $_SESSION['plugin_preventivemaintenance_selected_profiles'] = $profiles ?: ['Technician'];
    Html::back();
}

// Processamento do formulário quando enviado
// Form processing when submitted
if (isset($_POST['add'])) {
    // O token CSRF é validado automaticamente pelo GLPI 11
    // CSRF token is automatically validated by GLPI 11
    $error = null;

    $selected_entity_id = (int) ($_POST['entities_id'] ?? -1);
    $computer_id        = (int) ($_POST['items_id'] ?? 0);
    $computer           = new Computer();
    $last_date          = trim((string) ($_POST['last_maintenance_date'] ?? ''));
    $next_date          = trim((string) ($_POST['next_maintenance_date'] ?? ''));
    $is_valid_date      = static function (string $date): bool {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    };

    if ($selected_entity_id < 0 || !(new Entity())->getFromDB($selected_entity_id)) {
        $error = __('A entidade selecionada não existe no sistema.');
    } elseif (!Session::haveAccessToEntity($selected_entity_id)) {
        $error = __('Selecione uma entidade válida.');
    } elseif ($computer_id <= 0) {
        $error = __('Selecione um computador válido.');
    } elseif (!$computer->getFromDB($computer_id)) {
        $error = __('Computador selecionado não encontrado.');
    } elseif ($computer->fields['entities_id'] != $selected_entity_id) {
        // Verifica se o computador pertence à entidade
        // Checks if computer belongs to entity
        $error = __('O computador selecionado não pertence à entidade escolhida.');
    } elseif (!$is_valid_date($next_date) || ($last_date !== '' && !$is_valid_date($last_date))) {
        $error = __('Informe datas válidas (AAAA-MM-DD).');
    } else {
        // Verifica se já existe manutenção para este computador
        // Checks if maintenance already exists for this computer
        $existing = $pm->find(['items_id' => $computer_id, 'itemtype' => 'Computer']);
        unset($existing[$id]);
        if (count($existing) > 0) {
            $error = sprintf(
                __('Já existe uma manutenção cadastrada para o computador %s (ID: %d)'),
                $computer->getName(),
                $computer_id
            );
        }
    }

    if ($error !== null) {
        Session::addMessageAfterRedirect(htmlescape($error), false, ERROR);
        Html::back();
    }

    // Prepara os dados para gravação
    // Prepares data for saving
    $input = [
        'name'                  => trim((string) ($_POST['name'] ?? '')),
        'entities_id'           => $selected_entity_id,
        'is_recursive'          => 0,
        'technician_id'         => (int) ($_POST['technician_id'] ?? 0),
        'items_id'              => $computer_id,
        'itemtype'              => 'Computer',
        'last_maintenance_date' => $last_date !== '' ? $last_date : 'NULL',
        'next_maintenance_date' => $next_date,
        'maintenance_interval'  => 30,
    ];

    // Cálculo do intervalo de manutenção
    // Maintenance interval calculation
    if ($last_date !== '') {
        $input['maintenance_interval'] = (new DateTime($last_date))->diff(new DateTime($next_date))->days;
    }

    if ($is_edit) {
        $input['id'] = $id;
        if ($pm->update($input)) {
            Session::addMessageAfterRedirect(__('Manutenção atualizada com sucesso!'), true, INFO);
            Html::redirect($list_url);
        }
        Session::addMessageAfterRedirect(__('Erro ao atualizar no banco de dados.'), false, ERROR);
    } else {
        if ($pm->add($input)) {
            Session::addMessageAfterRedirect(__('Manutenção criada com sucesso!'), true, INFO);
            Html::redirect($list_url);
        }
        Session::addMessageAfterRedirect(__('Erro ao gravar no banco de dados.'), false, ERROR);
    }
    Html::back();
}

// Busca todos os perfis disponíveis para seleção
// Finds all available profiles for selection
$profile      = new Profile();
$all_profiles = $profile->find([], 'name ASC');

// Busca os perfis técnicos selecionados (armazenados na sessão ou usa o padrão 'Technician')
// Finds selected technician profiles (stored in session or uses default 'Technician')
$selected_profiles = $_SESSION['plugin_preventivemaintenance_selected_profiles'] ?? ['Technician'];

// Busca os técnicos responsáveis (usuários ativos com perfil de técnico selecionado)
// Finds responsible technicians (active users with selected technician profile)
$technicians = [];
$profile_ids = array_keys($profile->find(['name' => $selected_profiles]));
if (!empty($profile_ids)) {
    $user         = new User();
    $profile_user = new Profile_User();
    $user_ids     = array_unique(array_column($profile_user->find(['profiles_id' => $profile_ids]), 'users_id'));
    if (!empty($user_ids)) {
        foreach ($user->find(['id' => $user_ids, 'is_active' => 1, 'is_deleted' => 0]) as $u) {
            $technicians[$u['id']] = (new DbUtils())->formatUserName($u['id'], $u['name'], $u['realname'], $u['firstname']);
        }
    }
}
asort($technicians, SORT_NATURAL | SORT_FLAG_CASE);

// Busca apenas as entidades ativas da sessão do usuário
// Finds only active entities from user session
$entity   = new Entity();
$entities = $entity->find(['id' => $_SESSION['glpiactiveentities']], 'completename ASC');

// Computadores das entidades ativas que ainda não possuem manutenção
// Computers from active entities that don't have a maintenance yet
$computer      = new Computer();
$all_computers = $computer->find(
    ['is_deleted' => 0, 'is_template' => 0] + getEntitiesRestrictCriteria('glpi_computers'),
    'name ASC'
);

$blocked_computers = [];
foreach ($pm->find(['itemtype' => 'Computer']) as $maintenance) {
    if ($is_edit && $maintenance['id'] == $id) {
        continue;
    }
    $blocked_computers[] = (int) $maintenance['items_id'];
}

$computers_data = [];
foreach ($all_computers as $comp) {
    if (in_array((int) $comp['id'], $blocked_computers, true)) {
        continue;
    }
    $computers_data[] = [
        'id'          => (int) $comp['id'],
        'name'        => $comp['name'] !== '' ? $comp['name'] : '(' . $comp['id'] . ')',
        'entities_id' => (int) $comp['entities_id'],
    ];
}

$token     = Session::getNewCSRFToken();
$json_opts = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

// Exibe o cabeçalho do GLPI
// Displays GLPI header
Html::header(
    PluginPreventivemaintenancePreventivemaintenance::getTypeName(1),
    '',
    'plugins',
    'PluginPreventivemaintenanceMenu'
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
    .required {
        color: #dc3545;
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
    .interval-helper {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-top: 8px;
    }
    .interval-helper select {
        max-width: 200px;
    }
    .select-profile-btn {
        margin-bottom: 15px;
    }
    .profile-checkboxes {
        max-height: 400px;
        overflow-y: auto;
    }
    .profile-checkbox-item {
        margin-bottom: 10px;
    }
</style>

<!-- HTML principal do formulário -->
<!-- Main form HTML -->
<div class='plugin-preventive-maintenance-container'>
    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h2>
            <i class='ti ti-calendar-check me-2'></i>
            <?= htmlescape($is_edit ? __('Editar Manutenção') : __('Nova Manutenção')) ?>
        </h2>
        <a href='<?= htmlescape($list_url) ?>' class='btn btn-outline-primary'>
            <i class='ti ti-arrow-left me-2'></i><?= htmlescape(__('Voltar')) ?>
        </a>
    </div>

    <div class='card'>
        <div class='card-body'>
            <form method='post' id='preventive_maintenance_form'>
                <input type='hidden' name='_glpi_csrf_token' value='<?= htmlescape($token) ?>'>
                <input type='hidden' name='add' value='1'>

                <!-- STEP 1 - Somente seleção da entidade -->
                <!-- STEP 1 - Only entity selection -->
                <div id='step1'>
                    <!-- Botão para selecionar perfis técnicos -->
                    <!-- Button to select technician profiles -->
                    <div class="select-profile-btn">
                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="ti ti-user-cog me-2"></i><?= htmlescape(__('Selecionar Perfis Técnicos')) ?>
                        </button>
                        <small class="text-muted d-block mt-1"><?= htmlescape(__('Perfis selecionados: ') . implode(', ', $selected_profiles)) ?></small>
                    </div>

                    <div class='form-section'>
                        <label for='entities_id_select'><?= htmlescape(__('Entidade')) ?> <span class='required'>*</span></label>
                        <select id='entities_id_select' class='form-select'>
                            <option value=''><?= htmlescape(__('Selecione uma entidade')) ?></option>
                            <?php foreach ($entities as $ent) {
                                $selected = ($is_edit && $item_data['entities_id'] == $ent['id']) ? 'selected' : '';
                                echo "<option value='" . (int) $ent['id'] . "' $selected>" . htmlescape($ent['completename']) . "</option>";
                            } ?>
                        </select>
                    </div>

                    <div class='d-flex justify-content-end mt-4'>
                        <button type='button' class='btn btn-primary' id='nextButton'>
                            <i class='ti ti-arrow-right me-2'></i><?= htmlescape(__('Próximo')) ?>
                        </button>
                    </div>
                </div>

                <!-- STEP 2 - Demais campos -->
                <!-- STEP 2 - Other fields -->
                <div id='step2'>
                    <input type='hidden' name='entities_id' id='entities_id' value='<?= $is_edit ? (int) $item_data['entities_id'] : '' ?>'>

                    <div class='entity-info'>
                        <strong><?= htmlescape(__('Entidade selecionada:')) ?></strong>
                        <span id='selected-entity-name'></span>
                        (ID: <span id='selected-entity-id'></span>)
                    </div>

                    <div class='form-section'>
                        <label for='name'><?= htmlescape(__('Nome da Manutenção')) ?> <span class='required'>*</span></label>
                        <input type='text' name='name' id='name' class='form-control'
                               value="<?= $is_edit ? htmlescape($item_data['name']) : '' ?>" required>
                    </div>

                    <div class='form-section'>
                        <label for='technician_id'><?= htmlescape(__('Técnico Responsável')) ?> <span class='required'>*</span></label>
                        <select name='technician_id' id='technician_id' class='form-select' required>
                            <option value=''><?= htmlescape(__('Selecione um técnico responsável')) ?></option>
                            <?php
                            foreach ($technicians as $tech_id => $tech_name) {
                                $selected = ($is_edit && $item_data['technician_id'] == $tech_id) ? 'selected' : '';
                                echo "<option value='" . (int) $tech_id . "' $selected>" . htmlescape($tech_name) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class='form-section'>
                        <label for='items_id'><?= htmlescape(__('Computador')) ?> <span class='required'>*</span></label>
                        <select name='items_id' id='items_id' class='form-select' required>
                            <option value=''><?= htmlescape(__('Selecione um computador')) ?></option>
                        </select>
                    </div>

                    <div class='form-section'>
                        <label for='last_maintenance_date'><?= htmlescape(__('Última Manutenção')) ?></label>
                        <input type='date' id='last_maintenance_date' name='last_maintenance_date'
                               value="<?= $is_edit ? htmlescape($item_data['last_maintenance_date'] ?? '') : '' ?>"
                               class='form-control'>
                    </div>

                    <div class='form-section'>
                        <label for='next_maintenance_date'><?= htmlescape(__('Próxima Manutenção')) ?> <span class='required'>*</span></label>
                        <input type='date' id='next_maintenance_date' name='next_maintenance_date'
                               value="<?= $is_edit ? htmlescape($item_data['next_maintenance_date'] ?? '') : '' ?>"
                               class='form-control' required>
                        <!-- Calcula a próxima data a partir da última + intervalo -->
                        <!-- Computes next date from last date + interval -->
                        <div class='interval-helper'>
                            <span><?= htmlescape(__('Intervalo')) ?>:</span>
                            <select class='form-select' id='interval_value'>
                                <?php for ($m = 1; $m <= 12; $m++) {
                                    $label = $m === 12 ? '1 ano' : ($m === 1 ? '1 mês' : "$m meses");
                                    echo "<option value='$m'" . ($m === 6 ? ' selected' : '') . ">" . htmlescape($label) . "</option>";
                                } ?>
                            </select>
                            <button type='button' class='btn btn-outline-success' id='apply_interval'>
                                <?= htmlescape(__('Aplicar')) ?>
                            </button>
                        </div>
                    </div>

                    <div class='d-flex justify-content-between mt-4'>
                        <button type='button' class='btn btn-secondary' id='backButton'>
                            <i class='ti ti-arrow-left me-2'></i><?= htmlescape(__('Voltar')) ?>
                        </button>
                        <button type='submit' class='btn btn-success'>
                            <i class='ti ti-device-floppy me-2'></i><?= htmlescape($is_edit ? __('Atualizar') : __('Salvar')) ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Modal para seleção de perfis técnicos -->
            <!-- Modal for technician profiles selection -->
            <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" id="profileSelectionForm">
                            <input type="hidden" name="_glpi_csrf_token" value="<?= htmlescape($token) ?>">
                            <input type="hidden" name="save_selected_profiles" value="1">
                            <div class="modal-header">
                                <h5 class="modal-title"><?= htmlescape(__('Selecionar Perfis Técnicos')) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body profile-checkboxes">
                                <?php foreach ($all_profiles as $prof): ?>
                                    <div class="profile-checkbox-item">
                                        <label>
                                            <input type="checkbox" name="profiles[]" value="<?= htmlescape($prof['name']) ?>"
                                                <?= in_array($prof['name'], $selected_profiles) ? 'checked' : '' ?>>
                                            <?= htmlescape($prof['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlescape(__('Cancelar')) ?></button>
                                <button type="submit" class="btn btn-primary"><?= htmlescape(__('Salvar Seleção')) ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Script JavaScript para funcionalidades do formulário (usa o jQuery do GLPI) -->
            <!-- JavaScript for form features (uses GLPI's bundled jQuery) -->
            <script>
            $(function() {
                const computersData = <?= json_encode($computers_data, $json_opts) ?>;
                const editComputerId = <?= $is_edit ? (int) $item_data['items_id'] : 0 ?>;
                const messages = <?= json_encode([
                    'select_entity'  => __('Selecione uma entidade'),
                    'no_computer'    => __('Nenhum computador disponível'),
                    'last_date'      => __('Informe a data da última manutenção'),
                    'select_profile' => __('Selecione pelo menos um perfil técnico'),
                    'entity'         => __('Entidade'),
                ], $json_opts) ?>;

                // Carrega os computadores disponíveis para a entidade selecionada
                // Loads available computers for selected entity
                function loadComputers(entityId) {
                    const select = $('#items_id');
                    select.find('option').not(':first').remove();

                    const filtered = computersData.filter(comp => comp.entities_id == entityId);
                    if (filtered.length > 0) {
                        filtered.forEach(comp => {
                            select.append(new Option(comp.name, comp.id, false, comp.id === editComputerId));
                        });
                    } else {
                        const option = new Option(messages.no_computer, '');
                        option.disabled = true;
                        select.append(option);
                    }
                }

                function goToStep2(entityId) {
                    const entityName = $('#entities_id_select option[value="' + entityId + '"]').text();
                    $('#entities_id').val(entityId);
                    $('#selected-entity-name').text(messages.entity + ': ' + entityName);
                    $('#selected-entity-id').text(entityId);
                    loadComputers(entityId);
                    $('#step1').hide();
                    $('#step2').show();
                }

                <?php if ($is_edit) { ?>
                    // Se estiver editando, vai direto para o passo 2
                    // If editing, goes straight to step 2
                    goToStep2(<?= (int) $item_data['entities_id'] ?>);
                <?php } ?>

                // Evento do botão Próximo
                // Next button event
                $('#nextButton').on('click', function() {
                    const entityId = $('#entities_id_select').val();
                    if (!entityId) {
                        alert(messages.select_entity);
                        return;
                    }
                    goToStep2(entityId);
                });

                // Evento do botão Voltar
                // Back button event
                $('#backButton').on('click', function() {
                    $('#step2').hide();
                    $('#step1').show();
                });

                // Aplica o intervalo (em meses) a partir da última manutenção
                // Applies the interval (in months) from the last maintenance
                $('#apply_interval').on('click', function() {
                    const lastDate = $('#last_maintenance_date').val();
                    if (!lastDate) {
                        alert(messages.last_date);
                        return;
                    }

                    const months = parseInt($('#interval_value').val(), 10);
                    const [y, m, d] = lastDate.split('-').map(Number);
                    const date = new Date(y, m - 1 + months, d);

                    // Ajusta para o final do mês se necessário
                    // Adjusts to end of month if needed
                    if (date.getDate() !== d) {
                        date.setDate(0);
                    }

                    const pad = n => String(n).padStart(2, '0');
                    $('#next_maintenance_date').val(date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()));
                });

                // Verifica se pelo menos um perfil foi selecionado
                // Checks if at least one profile was selected
                $('#profileSelectionForm').on('submit', function(e) {
                    if ($(this).find('input[name="profiles[]"]:checked').length === 0) {
                        e.preventDefault();
                        alert(messages.select_profile);
                    }
                });
            });
            </script>
        </div>
        <!-- Rodapé personalizado -->
        <!-- Custom footer -->
        <div class="custom-footer">
            <i class="ti ti-code"></i> <?= htmlescape(__('Desenvolvido por WIDA - Work Information Developments and Analytics')) ?>
        </div>
    </div>
</div>

<?php
// Exibe o rodapé do GLPI
// Displays GLPI footer
Html::footer();

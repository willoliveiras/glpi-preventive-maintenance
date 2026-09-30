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

use Glpi\DBAL\QuerySubQuery;

// Verifica se o arquivo está sendo acessado diretamente (segurança GLPI)
// Check if file is being accessed directly (GLPI security)
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Classe principal do plugin que estende CommonDBTM para gerenciar manutenções preventivas
 * Main plugin class extending CommonDBTM to manage preventive maintenances
 */
class PluginPreventivemaintenancePreventivemaintenance extends CommonDBTM
{
    // Ativa histórico de alterações
    // Enable change history
    public $dohistory = true;

    // Nome do direito de acesso
    // Access right name
    public static $rightname = 'plugin_preventivemaintenance';

    public const CONFIG_TABLE  = 'glpi_plugin_preventivemaintenance_config';
    public const TICKETS_TABLE = 'glpi_plugin_preventivemaintenance_tickets';

    // Percentual do intervalo a partir do qual um ticket automático é criado
    // Interval percentage from which an automatic ticket is created
    public const AUTO_TICKET_THRESHOLD = 99;

    /**
     * Retorna o nome do tipo do item (singular/plural)
     * Returns the type name of the item (singular/plural)
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Preventive Maintenance', 'Preventive Maintenances', $nb, 'preventivemaintenance');
    }

    public static function getIcon()
    {
        return 'ti ti-calendar-check';
    }

    /**
     * Retorna o nome da tabela no banco de dados
     * Returns the database table name
     */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_preventivemaintenance_preventivemaintenances';
    }

    /**
     * Verifica permissão de criação
     * Checks create permission
     */
    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, CREATE);
    }

    /**
     * Verifica permissão de visualização
     * Checks view permission
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Verifica permissão de atualização
     * Checks update permission
     */
    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    /**
     * Verifica permissão de exclusão (com tratamento especial para Super-Admin)
     * Checks delete permission (with special handling for Super-Admin)
     */
    public static function canDelete(): bool
    {
        // Permissão sempre verdadeira para Super-Admin
        // Always true for Super-Admin
        if (($_SESSION['glpiactiveprofile']['name'] ?? '') === 'Super-Admin') {
            return true;
        }
        return Session::haveRightsOr(self::$rightname, [DELETE, PURGE]);
    }

    public static function canPurge(): bool
    {
        return self::canDelete();
    }

    /**
     * Direitos exibidos na matriz de permissões do perfil
     * Rights displayed in the profile rights matrix
     */
    public function getRights($interface = 'central')
    {
        return [
            READ   => __('Read'),
            CREATE => __('Create'),
            UPDATE => __('Update'),
            DELETE => __('Delete'),
            PURGE  => ['short' => __('Purge'), 'long' => _x('button', 'Delete permanently')],
        ];
    }

    /**
     * Prepara os dados antes da exclusão (validações de segurança)
     * Prepares data before deletion (security validations)
     */
    public function prepareInputForDelete($input)
    {
        if (!isset($input['id'])) {
            Session::addMessageAfterRedirect(__('ID do registro não especificado'), false, ERROR);
            return false;
        }

        if (!$this->getFromDB($input['id'])) {
            Session::addMessageAfterRedirect(__('Registro não encontrado'), false, ERROR);
            return false;
        }

        return $input;
    }

    /**
     * Valida os dados antes de adicionar um novo registro
     * Validates data before adding a new record
     */
    public function prepareInputForAdd($input)
    {
        if (empty($input['items_id'])) {
            Session::addMessageAfterRedirect(
                __('You must select a valid computer', 'preventivemaintenance'),
                false,
                ERROR
            );
            return false;
        }

        if (!$this->checkDates($input)) {
            return false;
        }

        // Definir valores padrão
        // Set default values
        $input['itemtype']     = 'Computer';
        $input['entities_id']  = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);
        $input['is_recursive'] = 0;

        return $input;
    }

    /**
     * Valida os dados antes de atualizar um registro
     * Validates data before updating a record
     */
    public function prepareInputForUpdate($input)
    {
        $dates = [
            'last_maintenance_date' => $input['last_maintenance_date'] ?? $this->fields['last_maintenance_date'] ?? null,
            'next_maintenance_date' => $input['next_maintenance_date'] ?? $this->fields['next_maintenance_date'] ?? null,
        ];
        if (!$this->checkDates($dates)) {
            return false;
        }

        return $input;
    }

    /**
     * Garante que a próxima manutenção é posterior à última
     * Ensures next maintenance is after the last one
     */
    private function checkDates(array $input): bool
    {
        $last = $input['last_maintenance_date'] ?? null;
        $next = $input['next_maintenance_date'] ?? null;
        if (
            !empty($last) && strtoupper($last) !== 'NULL'
            && !empty($next) && strtoupper($next) !== 'NULL'
            && $next <= $last
        ) {
            Session::addMessageAfterRedirect(
                __('Next maintenance date must be after last maintenance date', 'preventivemaintenance'),
                false,
                ERROR
            );
            return false;
        }
        return true;
    }

    /**
     * Ajustes após carregar dados do banco
     * Adjustments after loading data from database
     */
    public function post_getFromDB()
    {
        // Corrige visualização de registros antigos
        // Fixes display for old records
        foreach (['last_maintenance_date', 'next_maintenance_date'] as $field) {
            if (($this->fields[$field] ?? null) === '0000-00-00') {
                $this->fields[$field] = null;
            }
        }
    }

    /**
     * Calcula o percentual decorrido do intervalo de manutenção (null se indefinido)
     * Computes elapsed percentage of the maintenance interval (null if undefined)
     */
    public static function computeProgress(array $item): ?int
    {
        if (empty($item['last_maintenance_date']) || empty($item['next_maintenance_date'])) {
            return null;
        }

        $last  = strtotime($item['last_maintenance_date']);
        $next  = strtotime($item['next_maintenance_date']);
        $total = $next - $last;
        if ($total <= 0) {
            return 0;
        }

        return (int) min(100, max(0, round(((time() - $last) / $total) * 100)));
    }

    /**
     * Exibe valores específicos para determinados campos (especialmente o status)
     * Displays specific values for certain fields (especially status)
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        if ($field == 'status') {
            if (empty($values['items_id'])) {
                return '<span class="badge bg-secondary"><i class="ti ti-help"></i> ' . __('No Computer') . '</span>';
            }

            if (empty($values['last_maintenance_date']) || empty($values['next_maintenance_date'])) {
                return '<span class="badge bg-secondary"><i class="ti ti-clock"></i> ' . __('Not Scheduled') . '</span>';
            }

            $days_remaining = (int) round((strtotime($values['next_maintenance_date']) - time()) / DAY_TIMESTAMP);

            if ($days_remaining < 0) {
                return '<span class="badge bg-danger"><i class="ti ti-alert-triangle"></i> ' . __('Overdue') . ' (' . abs($days_remaining) . ' ' . __('days') . ')</span>';
            } elseif ($days_remaining <= 7) {
                return '<span class="badge bg-warning"><i class="ti ti-clock"></i> ' . __('Due Soon') . ' (' . $days_remaining . ' ' . __('days') . ')</span>';
            }
            return '<span class="badge bg-success"><i class="ti ti-check"></i> ' . __('On Track') . ' (' . $days_remaining . ' ' . __('days') . ')</span>';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Define as opções de busca/pesquisa (usadas também pelo histórico)
     * Defines search options (also used by history)
     */
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'            => '2',
            'table'         => static::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
        ];

        $tab[] = [
            'id'            => '3',
            'table'         => 'glpi_computers',
            'field'         => 'name',
            'linkfield'     => 'items_id',
            'name'          => __('Computer'),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => '4',
            'table'         => 'glpi_users',
            'field'         => 'name',
            'linkfield'     => 'technician_id',
            'name'          => __('Technician'),
            'datatype'      => 'dropdown',
            'right'         => 'all',
        ];

        $tab[] = [
            'id'       => '5',
            'table'    => static::getTable(),
            'field'    => 'last_maintenance_date',
            'name'     => __('Last Maintenance'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'       => '6',
            'table'    => static::getTable(),
            'field'    => 'next_maintenance_date',
            'name'     => __('Next Maintenance'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'       => '7',
            'table'    => static::getTable(),
            'field'    => 'maintenance_interval',
            'name'     => __('Interval (days)'),
            'datatype' => 'integer',
        ];

        $tab[] = [
            'id'       => '80',
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => Entity::getTypeName(1),
            'datatype' => 'dropdown',
            'massiveaction' => false,
        ];

        return $tab;
    }

    // ------------------------------------------------------------------
    // Configuração / Configuration
    // ------------------------------------------------------------------

    /**
     * Obtém um valor de configuração (null se não existir)
     * Gets a configuration value (null if not set)
     */
    public static function getConfigValue(string $name): ?string
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => self::CONFIG_TABLE,
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1,
        ]);

        return count($iterator) ? $iterator->current()['value'] : null;
    }

    /**
     * Grava um valor de configuração
     * Stores a configuration value
     */
    public static function setConfigValue(string $name, string $value): bool
    {
        global $DB;

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        if (self::getConfigValue($name) !== null) {
            return (bool) $DB->update(self::CONFIG_TABLE, ['value' => $value, 'date_mod' => $now], ['name' => $name]);
        }
        return (bool) $DB->insert(self::CONFIG_TABLE, [
            'name'          => $name,
            'value'         => $value,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }

    public static function isAutoTicketEnabled(): bool
    {
        return self::getConfigValue('auto_ticket') === '1';
    }

    // ------------------------------------------------------------------
    // Tickets de manutenção / Maintenance tickets
    // ------------------------------------------------------------------

    /**
     * Verifica se existe um ticket gerado pelo plugin ainda em aberto para a manutenção
     * Checks whether a plugin generated ticket is still open for the maintenance
     */
    public static function hasOpenMaintenanceTicket(int $computer_id, string $maintenance_name): bool
    {
        global $DB;

        if ($computer_id <= 0 || $maintenance_name === '') {
            return false;
        }

        $iterator = $DB->request([
            'SELECT'     => ['glpi_tickets.id'],
            'FROM'       => self::TICKETS_TABLE,
            'INNER JOIN' => [
                'glpi_tickets' => [
                    'ON' => [
                        self::TICKETS_TABLE => 'ticket_id',
                        'glpi_tickets'      => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                self::TICKETS_TABLE . '.computer_id'      => $computer_id,
                self::TICKETS_TABLE . '.maintenance_name' => $maintenance_name,
                'glpi_tickets.is_deleted'                 => 0,
                'NOT'                                     => ['glpi_tickets.status' => [Ticket::SOLVED, Ticket::CLOSED]],
            ],
            'LIMIT' => 1,
        ]);

        return count($iterator) > 0;
    }

    /**
     * Cria um ticket de manutenção preventiva para o item informado
     * Creates a preventive maintenance ticket for the given item
     *
     * @return int|false ID do ticket / ticket ID
     */
    public static function createMaintenanceTicket(array $maintenance)
    {
        global $DB;

        $computer_id = (int) $maintenance['items_id'];
        $name        = (string) $maintenance['name'];

        if (self::hasOpenMaintenanceTicket($computer_id, $name)) {
            return false;
        }

        $input = [
            'name'        => sprintf(__('Preventive maintenance required: %s'), $name),
            'content'     => sprintf(__('Computer requires preventive maintenance for: %s'), $name),
            'entities_id' => (int) $maintenance['entities_id'],
            'type'        => Ticket::INCIDENT_TYPE,
            'urgency'     => 5,
            'impact'      => 5,
            'items_id'    => ['Computer' => [$computer_id]],
        ];
        if (!empty($maintenance['technician_id'])) {
            $input['_users_id_observer'] = [(int) $maintenance['technician_id']];
        }

        $ticket    = new Ticket();
        $ticket_id = $ticket->add($input);
        if (!$ticket_id) {
            return false;
        }

        $DB->insert(self::TICKETS_TABLE, [
            'ticket_id'        => (int) $ticket_id,
            'computer_id'      => $computer_id,
            'maintenance_name' => $name,
            'date_creation'    => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $ticket_id;
    }

    /**
     * Cria tickets para as manutenções que atingiram o limite (se Auto Ticket ativo)
     * Creates tickets for maintenances that reached the threshold (if Auto Ticket enabled)
     *
     * @return string[] Nomes das manutenções com ticket criado / Names of maintenances with a created ticket
     */
    public static function processAutoTickets(): array
    {
        $created = [];
        if (!self::isAutoTicketEnabled()) {
            return $created;
        }

        $pm = new self();
        $criteria = isset($_SESSION['glpiactiveentities'])
            ? getEntitiesRestrictCriteria(self::getTable(), '', '', true)
            : [];
        foreach ($pm->find($criteria, 'next_maintenance_date ASC') as $item) {
            $progress = self::computeProgress($item);
            if ($progress === null || $progress < self::AUTO_TICKET_THRESHOLD) {
                continue;
            }
            if (self::createMaintenanceTicket($item)) {
                $created[] = $item['name'];
            }
        }
        return $created;
    }

    /**
     * Atualiza as datas da manutenção a partir da solução do ticket
     * Updates maintenance dates from the ticket solution
     */
    public static function updateMaintenanceOnTicketResolution(int $ticket_id): bool
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['computer_id', 'maintenance_name'],
            'FROM'   => self::TICKETS_TABLE,
            'WHERE'  => ['ticket_id' => $ticket_id],
            'LIMIT'  => 1,
        ]);
        if (!count($iterator)) {
            return false;
        }
        $data = $iterator->current();

        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticket_id)) {
            return false;
        }
        $solvedate = $ticket->fields['solvedate'] ?: $ticket->fields['closedate'];
        if (empty($solvedate)) {
            return false;
        }

        $pm = new self();
        $maintenances = $pm->find([
            'items_id' => $data['computer_id'],
            'name'     => $data['maintenance_name'],
        ]);

        foreach ($maintenances as $maintenance) {
            // Mantém o mesmo intervalo entre a última e a próxima manutenção
            // Keeps the same interval between last and next maintenance
            if (!empty($maintenance['last_maintenance_date']) && !empty($maintenance['next_maintenance_date'])) {
                $interval = strtotime($maintenance['next_maintenance_date']) - strtotime($maintenance['last_maintenance_date']);
            } else {
                $interval = max(1, (int) $maintenance['maintenance_interval']) * DAY_TIMESTAMP;
            }

            $pm->update([
                'id'                    => $maintenance['id'],
                'last_maintenance_date' => date('Y-m-d', strtotime($solvedate)),
                'next_maintenance_date' => date('Y-m-d', strtotime($solvedate) + $interval),
            ]);
        }

        $DB->delete(self::TICKETS_TABLE, ['ticket_id' => $ticket_id]);
        return true;
    }

    /**
     * Processa tickets do plugin já solucionados/fechados (ex.: solucionados antes da ativação do hook)
     * Processes plugin tickets already solved/closed (e.g. solved before the hook was active)
     */
    public static function cleanResolvedMaintenanceTickets(): void
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'     => ['glpi_tickets.id'],
            'FROM'       => 'glpi_tickets',
            'INNER JOIN' => [
                self::TICKETS_TABLE => [
                    'ON' => [
                        self::TICKETS_TABLE => 'ticket_id',
                        'glpi_tickets'      => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_tickets.status' => [Ticket::SOLVED, Ticket::CLOSED],
            ],
        ]);

        foreach ($iterator as $data) {
            self::updateMaintenanceOnTicketResolution((int) $data['id']);
            $DB->delete(self::TICKETS_TABLE, ['ticket_id' => $data['id']]);
        }

        // Remove vínculos de tickets que foram excluídos definitivamente do GLPI
        // Removes links to tickets that were permanently deleted from GLPI
        $DB->delete(self::TICKETS_TABLE, [
            'NOT' => ['ticket_id' => new QuerySubQuery(['SELECT' => 'id', 'FROM' => 'glpi_tickets'])],
        ]);
    }

    /**
     * Hook 'item_update' de Ticket: atualiza a manutenção quando o ticket é solucionado/fechado
     * Ticket 'item_update' hook: updates the maintenance when the ticket is solved/closed
     */
    public static function updateMaintenanceAfterTicket(CommonDBTM $ticket): void
    {
        if (
            !in_array('status', $ticket->updates ?? [], true)
            || !in_array((int) $ticket->fields['status'], [Ticket::SOLVED, Ticket::CLOSED], true)
        ) {
            return;
        }

        self::updateMaintenanceOnTicketResolution((int) $ticket->getID());
    }
}

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

use Glpi\DBAL\QueryExpression;

//Função de instalação/atualização do plugin - cria/migra tabelas e configura permissões
//Plugin install/upgrade function - creates/migrates tables and configures permissions
function plugin_preventivemaintenance_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_PREVENTIVEMAINTENANCE_VERSION);

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    // 1. Criar tabelas se não existirem
    // 1. Create tables if they don't exist

    // Tabela principal de manutenções preventivas
    // Main preventive maintenances table
    $table = 'glpi_plugin_preventivemaintenance_preventivemaintenances';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `items_id` int {$sign} NOT NULL DEFAULT '0',
            `itemtype` varchar(255) DEFAULT 'Computer',
            `technician_id` int {$sign} NOT NULL DEFAULT '0',
            `last_maintenance_date` date DEFAULT NULL,
            `next_maintenance_date` date DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            `maintenance_interval` int NOT NULL DEFAULT '30',
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `item` (`itemtype`, `items_id`),
            KEY `technician_id` (`technician_id`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // Tabela de tickets de manutenção
    // Maintenance tickets table
    $table = 'glpi_plugin_preventivemaintenance_tickets';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `ticket_id` int {$sign} NOT NULL DEFAULT '0',
            `computer_id` int {$sign} NOT NULL DEFAULT '0',
            `maintenance_name` varchar(255) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_id` (`ticket_id`),
            KEY `computer_id` (`computer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // Tabela de configuração do plugin
    // Plugin configuration table
    $table = 'glpi_plugin_preventivemaintenance_config';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `value` text DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 2. Migrar tabelas criadas pela versão 1.x (GLPI 10)
    // 2. Migrate tables created by version 1.x (GLPI 10)
    $table = 'glpi_plugin_preventivemaintenance_preventivemaintenances';

    // Datas zeradas ('0000-00-00') da versão 1.x bloqueiam o ALTER em modo SQL estrito.
    // A comparação é feita como texto: o MySQL 8 rejeita até comparar com '0000-00-00' (erro 1525).
    // Zero dates ('0000-00-00') from version 1.x block the ALTER in strict SQL mode.
    // Compared as text: MySQL 8 rejects even comparing against '0000-00-00' (error 1525).
    if ($DB->tableExists($table)) {
        foreach (['last_maintenance_date', 'next_maintenance_date'] as $field) {
            $is_zero_date = new QueryExpression('CAST(' . $DB->quoteName($field) . " AS CHAR) = '0000-00-00'");
            if (countElementsInTable($table, [$is_zero_date]) > 0) {
                $sql_mode = $DB->doQuery('SELECT @@SESSION.sql_mode AS mode')->fetch_assoc()['mode'];
                $DB->doQuery("SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");
                $DB->doQuery("ALTER TABLE `$table` MODIFY `$field` date DEFAULT NULL");
                $DB->update($table, [$field => null], [$is_zero_date]);
                $DB->doQuery('SET SESSION sql_mode = ' . $DB->quoteValue($sql_mode));
            }
        }
    }
    $migration->changeField($table, 'technician_id', 'technician_id', "int {$sign} NOT NULL DEFAULT '0'");
    $migration->changeField($table, 'next_maintenance_date', 'next_maintenance_date', 'date DEFAULT NULL');
    $migration->changeField($table, 'date_creation', 'date_creation', 'timestamp NULL DEFAULT NULL');
    $migration->changeField($table, 'date_mod', 'date_mod', 'timestamp NULL DEFAULT NULL');

    $table = 'glpi_plugin_preventivemaintenance_tickets';
    $migration->changeField($table, 'id', 'id', "int {$sign} NOT NULL AUTO_INCREMENT");
    $migration->changeField($table, 'ticket_id', 'ticket_id', "int {$sign} NOT NULL DEFAULT '0'");
    $migration->changeField($table, 'computer_id', 'computer_id', "int {$sign} NOT NULL DEFAULT '0'");
    $migration->changeField($table, 'date_creation', 'date_creation', 'timestamp NULL DEFAULT NULL');

    $table = 'glpi_plugin_preventivemaintenance_config';
    $migration->changeField($table, 'id', 'id', "int {$sign} NOT NULL AUTO_INCREMENT");
    $migration->changeField($table, 'date_creation', 'date_creation', 'timestamp NULL DEFAULT NULL');
    $migration->changeField($table, 'date_mod', 'date_mod', 'timestamp NULL DEFAULT NULL');

    $migration->executeMigration();

    // 3. Configurar permissões (somente na primeira instalação)
    // 3. Configure permissions (first install only)
    $rightname = PluginPreventivemaintenancePreventivemaintenance::$rightname;
    $already_installed = countElementsInTable('glpi_profilerights', ['name' => $rightname]) > 0;
    if (!$already_installed) {
        ProfileRight::addProfileRights([$rightname]);

        foreach ($DB->request(['FROM' => 'glpi_profiles']) as $prof) {
            $rights = match ($prof['name']) {
                'Super-Admin'         => ALLSTANDARDRIGHT, // Todos direitos / All rights
                'Admin', 'Supervisor' => READ | CREATE | UPDATE,
                'Technician'          => READ | CREATE,
                default               => $prof['interface'] === 'central' ? READ : 0,
            };

            $DB->update(
                'glpi_profilerights',
                ['rights' => $rights],
                ['profiles_id' => $prof['id'], 'name' => $rightname]
            );

            // Atualiza a sessão atual para não precisar fazer logout/login
            // Refresh current session so no logout/login is needed
            if (($_SESSION['glpiactiveprofile']['id'] ?? null) == $prof['id']) {
                $_SESSION['glpiactiveprofile'][$rightname] = $rights;
            }
        }
    }

    return true;
}

//Função de desinstalação - remove tabelas e direitos
//Uninstallation function - removes tables and rights
function plugin_preventivemaintenance_uninstall()
{
    global $DB;

    $tables = [
        'glpi_plugin_preventivemaintenance_preventivemaintenances',
        'glpi_plugin_preventivemaintenance_tickets',
        'glpi_plugin_preventivemaintenance_config',
    ];
    foreach ($tables as $table) {
        $DB->dropTable($table, true);
    }

    ProfileRight::deleteProfileRights([PluginPreventivemaintenancePreventivemaintenance::$rightname]);

    // Remove o histórico e as preferências de exibição do plugin
    // Removes plugin history and display preferences
    $itemtype = 'PluginPreventivemaintenancePreventivemaintenance';
    $DB->delete('glpi_logs', ['itemtype' => $itemtype]);
    $DB->delete('glpi_displaypreferences', ['itemtype' => $itemtype]);

    return true;
}

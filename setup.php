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
 
 


//Define a versão e informações básicas do plugin
//Defines the plugin version and basic information
function plugin_version_preventivemaintenance() {
    return [
        'name'           => 'Preventive Maintenance',
        'version'        => '1.0.0',
        'author'         => 'WIDA',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://widatecnologia.com.br',
        'description'    => __('Gerencie manutenções preventivas de equipamentos.'),
        'minGlpiVersion' => '10.0.0',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '11.0.0'
            ]
        ]
    ];
}

//Verifica os pré-requisitos para instalação do plugin (versão do GLPI)
//Checks plugin installation prerequisites (GLPI version)
function plugin_preventivemaintenance_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '10.0.0', '<')) {
        echo "This plugin requires GLPI version 10.0.0 or higher";
        return false;
    }
    return true;
}

//Verifica a configuração do plugin (sempre retorna true neste caso)
//Checks plugin configuration (always returns true in this case)
function plugin_preventivemaintenance_check_config($verbose = false) {
    return true;
}

//Função de instalação do plugin - cria tabelas, registra plugin e configura permissões
//Plugin installation function - creates tables, registers plugin and configures permissions
function plugin_preventivemaintenance_install() {
    global $DB;

    // 1. Criar tabelas se não existirem
    // 1. Create tables if they don't exist
    
    // Tabela principal de manutenções preventivas
    if (!$DB->tableExists('glpi_plugin_preventivemaintenance_preventivemaintenances')) {
        $query = "CREATE TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `items_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Computer',
            `technician_id` int(10) UNSIGNED NOT NULL,
            `last_maintenance_date` date DEFAULT NULL,
            `next_maintenance_date` date NOT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `maintenance_interval` int(11) NOT NULL DEFAULT '30',
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `items_id` (`items_id`),
            KEY `technician_id` (`technician_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $DB->queryOrDie($query, $DB->error());
    }

    // Tabela de tickets de manutenção
    if (!$DB->tableExists('glpi_plugin_preventivemaintenance_tickets')) {
        $query = "CREATE TABLE `glpi_plugin_preventivemaintenance_tickets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ticket_id` int(11) NOT NULL,
            `computer_id` int(11) NOT NULL,
            `maintenance_name` varchar(255) NOT NULL,
            `date_creation` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_id` (`ticket_id`),
            KEY `computer_id` (`computer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $DB->queryOrDie($query, $DB->error());
    }

    // Tabela de configuração do plugin
    // Plugin configuration table
    if (!$DB->tableExists('glpi_plugin_preventivemaintenance_config')) {
        $query = "CREATE TABLE `glpi_plugin_preventivemaintenance_config` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `value` text DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $DB->queryOrDie($query, $DB->error());
    }

    // 2. Registrar o plugin
    // 2. Register the plugin
    $plugin = new Plugin();
    if (!$plugin->getFromDBbyDir('preventivemaintenance')) {
        $plugin_id = $plugin->add([
            'directory' => 'preventivemaintenance',
            'name'      => 'Preventive Maintenance',
            'state'     => Plugin::NOTINSTALLED
        ]);
    } else {
        $plugin_id = $plugin->getID();
    }

    // 3. Configurar permissões corretamente
    // 3. Configure permissions correctly
    $rightname = 'plugin_preventivemaintenance';
    ProfileRight::addProfileRights([$rightname]);

    // Definir níveis de permissão
    // Define permission levels
    $permission_mapping = [
        'Super-Admin' => 31,   // ALLSTANDARDRIGHT (1+2+4+8+16)
        'Admin'       => 7,    // READ+CREATE+UPDATE (1+2+4)
        'Supervisor'  => 7,
        'Technician'  => 3,    // READ+CREATE (1+2)
        'default'     => 1     // READ
    ];

    $profile = new Profile();
    foreach ($profile->find() as $prof) {
        $rights = match($prof['name']) {
            'Super-Admin' => 31,  // Todos direitos
            'Admin', 'Supervisor' => 7,  // Ler+Criar+Atualizar
            'Technician' => 3,    // Ler+Criar
            default => 1          // Ler
        };
        
        $profile->update([
            'id' => $prof['id'],
            $rightname => $rights
        ]);
    }

    return true;
}
//Função de desinstalação - remove tabelas, direitos e registro do plugin
//Uninstallation function - removes tables, rights and plugin registration
function plugin_preventivemaintenance_uninstall() {
    global $DB;
    
    // 1. Remover tabelas
    // 1. Remove tables
    $tables = [
        'glpi_plugin_preventivemaintenance_preventivemaintenances',
        'glpi_plugin_preventivemaintenance_tickets',
        'glpi_plugin_preventivemaintenance_config'
    ];
    
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->query("DROP TABLE IF EXISTS `$table`");
        }
    }
    
    // 2. Remover direitos
    // 2. Remove rights
    $rightname = 'plugin_preventivemaintenance';
    $DB->delete('glpi_profilerights', ['name' => $rightname]);
    
    // 3. Remover o plugin
    // 3. Remove the plugin
    $plugin = new Plugin();
    if ($plugin->getFromDBbyDir('preventivemaintenance')) {
        $plugin->delete(['id' => $plugin->getID()]);
    }
    
    return true;
}
//Inicializa o plugin e configura hooks (ganchos) para integração com o GLPI
//Initializes the plugin and configures hooks for GLPI integration
function plugin_init_preventivemaintenance() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['preventivemaintenance'] = true;
    $PLUGIN_HOOKS['config_page']['preventivemaintenance'] = 'front/preventivemaintenance.php';
    $PLUGIN_HOOKS["menu_toadd"]['preventivemaintenance'] = array('plugins'  => 'PluginPreventivemaintenanceMenu');
    $PLUGIN_HOOKS['rights']['preventivemaintenance'] = 'plugin_preventivemaintenance_getRights';
    $PLUGIN_HOOKS['menu_entry_icon']['preventivemaintenance'] = Plugin::getWebDir('preventivemaintenance', false) . '/pics/logopm.png';
    
    $PLUGIN_HOOKS['use_massive_action']['preventivemaintenance'] = true;
    $PLUGIN_HOOKS['search_options']['preventivemaintenance'] = true;

    $PLUGIN_HOOKS['item_update']['preventivemaintenance'] = [
        'Ticket' => ['PluginPreventivemaintenancePreventivemaintenance', 'updateMaintenanceAfterTicket']];
        
        
    $PLUGIN_HOOKS['post_init']['preventivemaintenance'] = 'plugin_preventivemaintenance_postinit';
    $PLUGIN_HOOKS['user_login']['preventivemaintenance'] = ['PluginPreventivemaintenanceProfile', 'userLoginHook'];

    Plugin::registerClass('PluginPreventivemaintenanceProfile', ['addtabon' => ['Profile']]);
    Plugin::registerClass('PluginPreventivemaintenanceMenu');
    Plugin::registerClass('PluginPreventivemaintenancePreventivemaintenance');
}

//Define os direitos/permissões disponíveis para o plugin
//Defines available rights/permissions for the plugin
function plugin_preventivemaintenance_getRights() {
    return [
        ['itemtype' => 'PluginPreventivemaintenancePreventivemaintenance',
         'label'    => __('Preventive Maintenance'),
         'field'    => 'plugin_preventivemaintenance',
         'rights'   => [
             READ    => __('Read'),
             CREATE  => __('Create'),
             UPDATE  => __('Update'),
             DELETE  => __('Delete'),
             PURGE   => __('Purge')
         ]]
    ];
}

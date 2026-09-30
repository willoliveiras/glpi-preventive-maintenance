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

use Glpi\Plugin\Hooks;

define('PLUGIN_PREVENTIVEMAINTENANCE_VERSION', '2.0.0');

// Versões mínima (incluída) e máxima (excluída) do GLPI suportadas
// Minimal (inclusive) and maximal (exclusive) supported GLPI versions
define('PLUGIN_PREVENTIVEMAINTENANCE_MIN_GLPI', '11.0.0');
define('PLUGIN_PREVENTIVEMAINTENANCE_MAX_GLPI', '11.99.99');

//Define a versão e informações básicas do plugin
//Defines the plugin version and basic information
function plugin_version_preventivemaintenance()
{
    return [
        'name'         => 'Preventive Maintenance',
        'version'      => PLUGIN_PREVENTIVEMAINTENANCE_VERSION,
        'author'       => 'WIDA',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://github.com/willoliveiras/glpi-preventive-maintenance',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PREVENTIVEMAINTENANCE_MIN_GLPI,
                'max' => PLUGIN_PREVENTIVEMAINTENANCE_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2',
            ],
        ],
    ];
}

//Verifica os pré-requisitos para instalação do plugin
//Checks plugin installation prerequisites
//(as versões do GLPI/PHP são verificadas pelo GLPI através de 'requirements')
//(GLPI/PHP versions are checked by GLPI itself through 'requirements')
function plugin_preventivemaintenance_check_prerequisites()
{
    return true;
}

//Verifica a configuração do plugin (sempre retorna true neste caso)
//Checks plugin configuration (always returns true in this case)
function plugin_preventivemaintenance_check_config($verbose = false)
{
    return true;
}

//Inicializa o plugin e configura hooks (ganchos) para integração com o GLPI
//Initializes the plugin and configures hooks for GLPI integration
function plugin_init_preventivemaintenance()
{
    global $PLUGIN_HOOKS;

    Plugin::registerClass('PluginPreventivemaintenanceProfile', ['addtabon' => ['Profile']]);

    $plugin = new Plugin();
    if (!$plugin->isActivated('preventivemaintenance')) {
        return;
    }

    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['preventivemaintenance'] = 'front/preventivemaintenance.php';
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['preventivemaintenance']  = ['plugins' => 'PluginPreventivemaintenanceMenu'];

    // Atualiza a manutenção quando o chamado gerado é solucionado/fechado
    // Updates the maintenance when the generated ticket is solved/closed
    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['preventivemaintenance'] = [
        'Ticket' => ['PluginPreventivemaintenancePreventivemaintenance', 'updateMaintenanceAfterTicket'],
    ];
}

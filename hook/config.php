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


//Verificação de segurança padrão do GLPI
//Standard GLPI security check
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

//Registra classes com comportamentos visuais (ex: abas em preferências)
//Registers classes with visual behaviors (e.g: tabs in preferences)
Plugin::registerClass('PluginPreventivemaintenanceMenu', [
   'addtabon' => ['Preference']
]);

//Registra a classe principal do plugin sem abas adicionais
//Registers main plugin class without additional tabs
Plugin::registerClass('PluginPreventivemaintenancePreventivemaintenance', [
   'addtabon' => [],
   'classname' => 'PluginPreventivemaintenancePreventivemaintenance'
]);

//Define o conteúdo do menu do plugin (visível para usuários com permissão READ)
//Defines plugin menu content (visible to users with READ permission)
function plugin_preventivemaintenance_getMenuContent() {
   if (Session::haveRight("plugin_preventivemaintenance", READ)) {
      return [
         'title' => __('Preventive Maintenance', 'preventivemaintenance'),
         'page'  => '/plugins/preventivemaintenance/front/preventivemaintenance.php',
         'icon'  => 'fas fa-tools'
      ];
   }
   return false;
}
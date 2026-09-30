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

//Verificação de segurança padrão
//Standard security check
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

//Classe que define o menu do plugin
//Class that defines the plugin menu
class PluginPreventivemaintenanceMenu extends CommonGLPI
{
    public static $rightname = 'plugin_preventivemaintenance';

    //Retorna o nome do menu
    //Returns menu name
    public static function getMenuName()
    {
        return __('Preventive Maintenance', 'preventivemaintenance');
    }

    public static function getIcon()
    {
        return PluginPreventivemaintenancePreventivemaintenance::getIcon();
    }

    //Define o conteúdo completo do menu
    //Defines complete menu content
    public static function getMenuContent()
    {
        $itemtype = PluginPreventivemaintenancePreventivemaintenance::class;

        $menu = [
            'title' => self::getMenuName(),
            'page'  => $itemtype::getSearchURL(false),
            'icon'  => self::getIcon(),
        ];

        //Adiciona opções se o usuário tiver permissão de visualização
        //Adds options if user has view permission
        if ($itemtype::canView()) {
            $menu['options']['preventivemaintenance'] = [
                'title' => $itemtype::getTypeName(Session::getPluralNumber()),
                'page'  => $itemtype::getSearchURL(false),
                'icon'  => self::getIcon(),
            ];
            if ($itemtype::canCreate()) {
                $menu['options']['preventivemaintenance']['links']['add'] = $itemtype::getFormURL(false);
            }
        }

        return $menu;
    }
}

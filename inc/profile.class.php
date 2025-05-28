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

// Verificação de segurança
// Security check
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

// Classe para gerenciar perfil/permissões do plugin
// Class to manage plugin profile/permissions
class PluginPreventivemaintenanceProfile extends Profile {
   
   public static $rightname = 'profile';

   // Define o nome da aba para perfis
   // Defines tab name for profiles
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Profile' && $item->getField('interface') != 'helpdesk') {
         return __('Preventive Maintenance', 'preventivemaintenance');
      }
      return '';
   }

    // Adicione esta função à sua classe
    public static function userLoginHook($user) {
        self::initProfile();
    }


   // Exibe o conteúdo da aba de perfil
   // Displays profile tab content
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == 'Profile') {
         $prof = new self();
         $prof->showForm($item->getID());
      }
      return true;
   }

   // Mostra formulário de direitos
   // Shows rights form
   public function showForm($profiles_id, $openform = true, $closeform = true) {
      global $CFG_GLPI;

      echo "<div class='firstbloc'>";
      $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE]);

      if ($canedit && $openform) {
         echo "<form method='post' action='" . Toolbox::getItemTypeFormURL('Profile') . "'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);

      $rights = [
         [
            'itemtype' => 'PluginPreventivemaintenancePreventivemaintenance',
            'label'    => __('Preventive Maintenance', 'preventivemaintenance'),
            'field'    => 'plugin_preventivemaintenance'
         ]
      ];

      $profile->displayRightsChoiceMatrix(
         $rights,
         [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('General')
         ]
      );

      echo "</table>";

      if ($canedit && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $profiles_id]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
         echo "</div>";
         Html::closeForm();
      }

      echo "</div>";
   }

   // Define os direitos padrão para novos perfis
   // Defines default rights for new profiles
   public static function initProfile() {
   global $DB;

   $profile = new self();
   $dbu = new DbUtils();

   $default_rights = ['plugin_preventivemaintenance'];

   // Adiciona direitos para todos os perfis se não existirem
   foreach ($default_rights as $right) {
      if ($dbu->countElementsInTable("glpi_profilerights", ["name" => $right]) == 0) {
         ProfileRight::addProfileRights([$right]);
         
         // Define permissões padrão para todos os perfis
         foreach ($DB->request('glpi_profiles') as $profile_data) {
            $rights = 0;
            // Define permissões padrão baseadas no tipo de perfil
            if ($profile_data['interface'] == 'central') {
               $rights = CREATE | READ | UPDATE | DELETE; // Ajuste conforme necessário
            }
            $DB->updateOrInsert('glpi_profilerights', [
               'rights' => $rights
            ], [
               'profiles_id' => $profile_data['id'],
               'name' => $right
            ]);
         }
      }
   }
}
}